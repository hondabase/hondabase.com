#!/usr/bin/env python3
"""Remove Photobucket host watermarks with Gemini, then apply poster credit watermark.

Uses gemini-3.1-flash-image (sync). The pro image batch path hung in practice.

  export GEMINI_API_KEY=...
  python3 remove_photobucket.py --src raw/ --out cleaned/ --authors authors.json

authors.json maps filename stem -> poster username, e.g.:
  {"hh_064": "honda-hardy", "lee_0285": "Lee4391"}
"""

from __future__ import annotations

import argparse
import base64
import io
import json
import os
import time
from pathlib import Path

from google import genai
from google.genai import types
from PIL import Image, ImageDraw, ImageFont, ImageEnhance, ImageOps

PROMPT = """Inpaint and remove ALL Photobucket website watermarks from this photo.

Must fully erase:
1) The semi-transparent spiral Photobucket logo
2) The word "photobucket" (any opacity)
3) The horizontal gray bar reading "Join free. Share privately." (and similar Photobucket promo banners)

Fill those areas with realistic background matching surrounding texture so the overlays are completely gone - not faded, not partial.

Preserve everything else exactly: red circles or other technical annotations, tools, screws, subjects, focus, crop, lighting.
Do not add any new logos, text, circles, or watermarks.
Output only the cleaned photograph."""

MODEL = "gemini-3.1-flash-image"


def load_api_key() -> str:
    if os.environ.get("GEMINI_API_KEY"):
        return os.environ["GEMINI_API_KEY"].strip()
    for path in (
        Path("/var/www/hondabase/www/.gemini_api_key"),
        Path.home() / ".gemini_api_key",
    ):
        if path.is_file() and path.read_text().strip():
            return path.read_text().strip()
    raise SystemExit("GEMINI_API_KEY not found")


def load_font(size: int):
    for path in (
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
    ):
        if Path(path).is_file():
            return ImageFont.truetype(path, size=size)
    return ImageFont.load_default()


def apply_author_watermark(src: Path, dest: Path, author: str) -> None:
    im = ImageOps.exif_transpose(Image.open(src)).convert("RGBA")
    w, h = im.size
    rgb = ImageEnhance.Color(im.convert("RGB")).enhance(0.95).convert("RGBA")
    overlay = Image.new("RGBA", im.size, (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)
    text = f"© {author}"
    font_size = max(16, min(w, h) // 16)
    font = load_font(font_size)
    bbox = draw.textbbox((0, 0), text, font=font)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    diag = Image.new("RGBA", (w * 2, h * 2), (0, 0, 0, 0))
    ddraw = ImageDraw.Draw(diag)
    for y in range(0, h * 2, th * 5):
        for x in range(0, w * 2, tw + font_size * 3):
            ddraw.text((x, y), text, font=font, fill=(255, 255, 255, 70))
    diag = diag.rotate(30, expand=False, center=(w, h))
    left = (diag.width - w) // 2
    top = (diag.height - h) // 2
    diag = diag.crop((left, top, left + w, top + h))
    overlay = Image.alpha_composite(overlay, diag)
    banner_h = max(26, h // 16)
    draw = ImageDraw.Draw(overlay)
    draw.rectangle([(0, h - banner_h), (w, h)], fill=(15, 15, 15, 160))
    small = load_font(max(12, banner_h // 2 - 2))
    draw.text(
        (10, h - banner_h + max(4, (banner_h - (banner_h // 2)) // 2)),
        f"Photo: {author}"[:80],
        font=small,
        fill=(255, 220, 120, 235),
    )
    out = Image.alpha_composite(rgb, overlay).convert("RGB")
    if out.width > 1600:
        nh = int(out.height * 1600 / out.width)
        out = out.resize((1600, nh), Image.Resampling.LANCZOS)
    dest.parent.mkdir(parents=True, exist_ok=True)
    out.save(dest, "JPEG", quality=88, optimize=True)


def clean_one(client: genai.Client, src: Path, dest: Path) -> None:
    t0 = time.time()
    resp = client.models.generate_content(
        model=MODEL,
        contents=[
            types.Content(
                role="user",
                parts=[
                    types.Part.from_text(text=PROMPT),
                    types.Part.from_bytes(data=src.read_bytes(), mime_type="image/jpeg"),
                ],
            )
        ],
        config=types.GenerateContentConfig(
            response_modalities=["TEXT", "IMAGE"],
            temperature=0.1,
        ),
    )
    parts = resp.candidates[0].content.parts if resp and resp.candidates else []
    for part in parts or []:
        inline = getattr(part, "inline_data", None)
        if not inline:
            continue
        data = inline.data
        raw = base64.b64decode(data) if isinstance(data, str) else data
        im = Image.open(io.BytesIO(raw)).convert("RGB")
        dest.parent.mkdir(parents=True, exist_ok=True)
        im.save(dest, "JPEG", quality=92, optimize=True)
        print(f"cleaned {dest.name} {im.size} {time.time() - t0:.1f}s")
        return
    raise RuntimeError(f"No image returned for {src.name}")


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--src", type=Path, required=True, help="Directory of source JPGs")
    ap.add_argument("--out", type=Path, required=True, help="Cleaned JPGs (no Photobucket)")
    ap.add_argument("--wm", type=Path, required=True, help="Poster-watermarked JPGs")
    ap.add_argument("--authors", type=Path, required=True, help="JSON stem->username map")
    args = ap.parse_args()

    authors = json.loads(args.authors.read_text())
    client = genai.Client(api_key=load_api_key())

    for src in sorted(args.src.glob("*.jpg")):
        stem = src.stem
        author = authors.get(stem)
        if not author:
            print(f"skip {stem}: no author mapping")
            continue
        cleaned = args.out / f"{stem}.jpg"
        clean_one(client, src, cleaned)
        apply_author_watermark(cleaned, args.wm / f"wm-{author.lower().replace(' ', '-')}-{stem}.jpg", author)


if __name__ == "__main__":
    main()
