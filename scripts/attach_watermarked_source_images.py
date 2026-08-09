#!/usr/bin/env python3
"""Attach original private source-archive images to Viruxe drafts (preserve site watermarks; no Hondabase overlay).

- Reads human-readable inventory under storage/app/private/source-image-archive/by-article/
- Writes watermarked copies into storage/app/draft-assets/{draft_id}/
- Updates article_drafts.assets and replaces placeholder image markdown with real filenames

Watermark text marks images as draft-only / not publication-cleared.
"""

from __future__ import annotations

import argparse
import json
import re
import subprocess
from collections import defaultdict
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont, ImageEnhance, ImageOps

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE = ROOT / "storage/app/private/source-image-archive"
BY_ARTICLE = ARCHIVE / "by-article"
DRAFT_ASSETS = ROOT / "storage/app/draft-assets"
WATERMARK = "HONDABASE DRAFT · NOT CLEARED FOR PUBLICATION"
WATERMARK_SUB = "Source archive copy · all rights reserved"


def slugify(s: str) -> str:
    s = s.lower()
    s = re.sub(r"[^a-z0-9]+", "-", s)
    return (s.strip("-")[:48] or "image")


def base_key(name: str) -> str:
    stem = Path(name).stem.lower()
    stem = re.sub(r"-(?:960|1920)\b", "", stem)
    stem = re.sub(r"~mv2.*$", "", stem)
    stem = re.sub(r"_d_\d+_\d+.*$", "", stem)
    stem = re.sub(r"^(?:\d{3}-)+", "", stem)
    return stem


def human_alt(img: dict) -> str:
    for key in ("alt", "title", "caption"):
        v = (img.get(key) or "").strip()
        if v and "бортжурнал" not in v.lower() and not v.lower().startswith("фото"):
            return v[:120]
    name = img.get("filename") or img.get("source_original_filename") or "Source photo"
    stem = Path(name).stem
    stem = re.sub(r"^(?:\d{3}-)+", "", stem)
    stem = re.sub(r"[-_]+", " ", stem).strip()
    if re.fullmatch(r"[a-f0-9 ]{8,}", stem.lower()) or len(stem) < 2:
        return "Source photo"
    return stem[:1].upper() + stem[1:]


def load_font(size: int) -> ImageFont.ImageFont:
    for path in (
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
    ):
        if Path(path).is_file():
            return ImageFont.truetype(path, size=size)
    return ImageFont.load_default()


def watermark_image(src: Path, dest: Path, site: str | None = None) -> None:
    im = Image.open(src)
    im = ImageOps.exif_transpose(im)
    if im.mode not in ("RGB", "RGBA"):
        im = im.convert("RGBA")
    elif im.mode == "RGB":
        im = im.convert("RGBA")

    w, h = im.size
    # Slightly desaturate so watermark reads clearly
    rgb = im.convert("RGB")
    rgb = ImageEnhance.Color(rgb).enhance(0.92)
    im = rgb.convert("RGBA")

    overlay = Image.new("RGBA", im.size, (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)

    # Diagonal repeated watermark
    font_size = max(18, min(w, h) // 18)
    font = load_font(font_size)
    text = WATERMARK
    # measure
    bbox = draw.textbbox((0, 0), text, font=font)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]

    # tile diagonals
    step_y = th * 4
    step_x = tw + font_size * 2
    # draw on a larger canvas then rotate
    diag = Image.new("RGBA", (w * 2, h * 2), (0, 0, 0, 0))
    ddraw = ImageDraw.Draw(diag)
    for y in range(0, h * 2, step_y):
        for x in range(0, w * 2, step_x):
            ddraw.text((x, y), text, font=font, fill=(255, 255, 255, 70))
    diag = diag.rotate(30, expand=False, center=(w, h))
    # crop center to original
    left = (diag.width - w) // 2
    top = (diag.height - h) // 2
    diag = diag.crop((left, top, left + w, top + h))
    overlay = Image.alpha_composite(overlay, diag)

    # Bottom banner
    banner_h = max(28, h // 14)
    draw = ImageDraw.Draw(overlay)
    draw.rectangle([(0, h - banner_h), (w, h)], fill=(15, 15, 15, 170))
    small = load_font(max(12, banner_h // 3))
    line1 = WATERMARK
    line2 = WATERMARK_SUB if not site else f"{WATERMARK_SUB} · {site}"
    draw.text((10, h - banner_h + 4), line1, font=small, fill=(255, 200, 80, 230))
    if banner_h >= 36:
        draw.text((10, h - banner_h // 2), line2[:80], font=small, fill=(220, 220, 220, 210))

    out = Image.alpha_composite(im, overlay).convert("RGB")
    dest.parent.mkdir(parents=True, exist_ok=True)
    # Prefer jpeg for photos; keep png for graphics with transparency source
    ext = dest.suffix.lower()
    if ext in (".png", ".webp", ".gif"):
        out.save(dest, quality=90, optimize=True)
    else:
        if ext not in (".jpg", ".jpeg"):
            dest = dest.with_suffix(".jpg")
        out.save(dest, format="JPEG", quality=88, optimize=True)


def collect_images() -> dict[int, list[dict]]:
    by: dict[int, list[dict]] = defaultdict(list)
    seen: dict[int, set[str]] = defaultdict(set)
    for index in sorted(BY_ARTICLE.rglob("index.json")):
        data = json.loads(index.read_text(encoding="utf-8"))
        did = data.get("draft_id")
        if not did:
            continue
        site = data.get("site")
        for img in data.get("images") or []:
            sha = img.get("sha256")
            op = img.get("object_path")
            if not sha or not op:
                continue
            if sha in seen[did]:
                continue
            path = ARCHIVE / op
            if not path.is_file():
                continue
            w, h = img.get("width") or 0, img.get("height") or 0
            if w and h and (w < 120 or h < 80):
                continue
            seen[did].add(sha)
            by[did].append(
                {
                    **img,
                    "site": site,
                    "abs_path": str(path),
                    "article_path": data.get("article_path"),
                    "draft_id": did,
                }
            )
    return by


def markdown_for_images(slug: str, files: list[tuple[str, str]]) -> str:
    """files: list of (filename, alt)."""
    if not files:
        return ""
    if len(files) == 1:
        name, alt = files[0]
        return (
            f"![{alt}]({name})\n\n"
            f"*{alt} — watermarked draft asset (not publication-cleared).*\n"
        )
    # Multiple: one carousel if 2–12, else sequential groups of carousels
    chunks: list[list[tuple[str, str]]] = []
    size = 8
    for i in range(0, len(files), size):
        chunks.append(files[i : i + size])

    parts = []
    for chunk in chunks:
        if len(chunk) == 1:
            name, alt = chunk[0]
            parts.append(
                f"![{alt}]({name})\n\n*{alt} — watermarked draft asset (not publication-cleared).*\n"
            )
            continue
        slides = []
        for name, alt in chunk:
            slides.append(
                f"![{alt}]({name})\n*{alt} — watermarked draft asset (not publication-cleared)*"
            )
        parts.append("```carousel\n" + "\n<!-- slide -->\n".join(slides) + "\n```\n")
    return "\n".join(parts)


def strip_existing_image_blocks(body: str) -> str:
    body = re.sub(r"```carousel\n.*?```\n*", "", body, flags=re.S)
    body = re.sub(r"!\[[^\]]*\]\([^)]+\)\n(?:\*[^\n]+\*\n)?", "", body)
    body = re.sub(r"\n{3,}", "\n\n", body)
    return body


def insert_after_intro(body: str, block: str) -> str:
    m = re.search(r"^(# .+)\n+", body, re.M)
    if not m:
        return body.rstrip() + "\n\n" + block
    start = m.end()
    rest = body[start:]
    sec = re.search(r"\n## ", rest)
    if sec:
        at = start + sec.start()
        return body[:at].rstrip() + "\n\n" + block + "\n" + body[at:].lstrip("\n")
    return body[:start] + block + "\n" + rest


def load_drafts() -> dict[int, dict]:
    raw = subprocess.check_output(
        [
            "php",
            "-r",
            r"""
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$u = App\Models\User::whereRaw("LOWER(discord_username)=?", ["viruxe"])->sole();
$out = [];
foreach (App\Models\ArticleDraft::where("user_id", $u->id)->orderBy("id")->get() as $d) {
  $out[] = ["id"=>$d->id,"slug"=>$d->slug,"title"=>$d->title,"document"=>$d->document,"assets"=>$d->assets];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
""",
        ],
        cwd=str(ROOT),
        text=True,
    )
    return {d["id"]: d for d in json.loads(raw)}


def apply_updates(updates: list[dict]) -> None:
    payload = ROOT / "scratch/draft-bodies/apply_watermarked.json"
    payload.parent.mkdir(parents=True, exist_ok=True)
    payload.write_text(json.dumps(updates, ensure_ascii=False), encoding="utf-8")
    php = ROOT / "scratch/draft-bodies/apply_watermarked.php"
    php.write_text(
        r"""<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\ArticleDraft;
use App\Models\User;
$payload = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$user = User::query()->whereRaw('LOWER(discord_username) = ?', ['viruxe'])->sole();
foreach ($payload as $item) {
    $draft = ArticleDraft::query()->where('user_id', $user->id)->whereKey($item['draft_id'])->first();
    if (!$draft) {
        echo "missing {$item['draft_id']}\n";
        continue;
    }
    $draft->document = $item['document'];
    $draft->assets = $item['assets'];
    $draft->note = 'Full draft copy with watermarked source-archive images (not publication-cleared). Verify claims and replace/clear images before submit.';
    $draft->save();
    echo "updated #{$draft->id} assets=".count($item['assets'])."\n";
}
echo "DONE\n";
""",
        encoding="utf-8",
    )
    r = subprocess.run(["php", str(php), str(payload)], cwd=str(ROOT), capture_output=True, text=True)
    print(r.stdout)
    if r.returncode != 0:
        print(r.stderr)
        raise SystemExit(r.returncode)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--limit-drafts", type=int, default=None)
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()

    images_by_draft = collect_images()
    drafts = load_drafts()
    draft_ids = sorted(set(images_by_draft) & set(drafts))
    if args.limit_drafts:
        draft_ids = draft_ids[: args.limit_drafts]

    print(f"Drafts with source images: {len(draft_ids)}")
    updates = []
    total_imgs = 0

    for did in draft_ids:
        d = drafts[did]
        imgs = images_by_draft[did]
        # stable order by original filename / order in index
        out_dir = DRAFT_ASSETS / str(did)
        if not args.dry_run:
            out_dir.mkdir(parents=True, exist_ok=True)
            # clear previous watermarked assets for this draft (only wm-*)
            for old in out_dir.glob("wm-*"):
                old.unlink()

        files: list[tuple[str, str]] = []
        used_names: set[str] = set()
        for i, img in enumerate(imgs, 1):
            src = Path(img["abs_path"])
            alt = human_alt(img)
            base = slugify(Path(img.get("filename") or img.get("source_original_filename") or f"image-{i}").stem)
            if not base or base == "image":
                base = f"image-{i:03d}"
            name = f"wm-{i:03d}-{base}.jpg"
            n = 1
            while name in used_names:
                n += 1
                name = f"wm-{i:03d}-{base}-{n}.jpg"
            used_names.add(name)
            dest = out_dir / name
            if args.dry_run:
                print(f"  would watermark #{did} {src.name} -> {name}")
            else:
                try:
                    watermark_image(src, dest, site=img.get("site"))
                except Exception as e:
                    print(f"  FAIL #{did} {src}: {e}")
                    continue
            files.append((name, alt))
            total_imgs += 1

        if not files:
            continue

        # rebuild body image section
        doc = d["document"]
        if not doc.startswith("---"):
            continue
        parts = doc.split("---", 2)
        if len(parts) < 3:
            continue
        fm, body = parts[1], parts[2].lstrip("\n")
        body = strip_existing_image_blocks(body)
        block = markdown_for_images(d["slug"], files)
        body = insert_after_intro(body, block)
        new_doc = "---" + fm + "---\n\n" + body.lstrip("\n")
        if not new_doc.endswith("\n"):
            new_doc += "\n"

        asset_names = [f for f, _ in files]
        updates.append(
            {
                "draft_id": did,
                "document": new_doc,
                "assets": asset_names,
            }
        )
        print(f"#{did:03d} {len(files):2d} images  {d['slug']}")

    print(f"Total watermarked images: {total_imgs}")
    print(f"Drafts to update: {len(updates)}")
    if args.dry_run:
        return
    apply_updates(updates)
    # ownership for web
    subprocess.run(["chown", "-R", "www-data:www-data", str(DRAFT_ASSETS)], check=False)
    print("Owned by www-data:", DRAFT_ASSETS)


if __name__ == "__main__":
    main()
