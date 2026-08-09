#!/usr/bin/env python3
"""Download content images from scraped 4GUK HTML.

Uses direct curl (not Tor) for Photobucket / Flickr / Google / Wayback.
Forum HTML is assumed already on disk from 4guk_tor_scrape.py.

Example:
  python3 download_topic_images.py \\
    --html /tmp/4guk_tor --html /tmp/4guk_tor2 \\
    --out /tmp/4guk_imgs/all
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
import time
from pathlib import Path
from urllib.parse import unquote, urlparse, quote

from bs4 import BeautifulSoup

HTML_DIRS: list[Path] = []
OUT = Path("/tmp/4guk_imgs/all")
MANIFEST_DIR = Path("/tmp/4guk_imgs/manifest")

SKIP = re.compile(
    r"(smil|avatar|rank_|logo|icon_|button|pixel|spacer|styles/|forumsign|"
    r"4gukbanner|sigpic|siggy|Shuttle-Sig|SedanSig|thefinger|makeagif|"
    r"Teg-CRX001|ist2_2822343|banner|sig\.|/images/smilies/)",
    re.I,
)


def curl_get(url: str, timeout: int = 45) -> tuple[int, bytes, str]:
    """Return status, body, content-type via system curl (more reliable here)."""
    cmd = [
        "curl", "-sS", "-L", "--max-time", str(timeout),
        "-A", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
        "-H", "Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8",
        "-H", "Referer: https://4guk.co.uk/",
        "-w", "\n__HTTPSTATUS__%{http_code}\n__CTYPE__%{content_type}",
        url,
    ]
    try:
        p = subprocess.run(cmd, capture_output=True, timeout=timeout + 10)
    except subprocess.TimeoutExpired:
        return 0, b"", ""
    out = p.stdout
    # split trailer
    if b"__HTTPSTATUS__" not in out:
        return 0, out, ""
    body, _, trailer = out.rpartition(b"__HTTPSTATUS__")
    trailer = trailer.decode("utf-8", "replace")
    m = re.search(r"(\d{3})", trailer)
    status = int(m.group(1)) if m else 0
    cm = re.search(r"__CTYPE__(.*)$", trailer)
    ctype = (cm.group(1).strip() if cm else "")
    return status, body, ctype


def is_image(data: bytes) -> bool:
    if len(data) < 200:
        return False
    if data[:3] == b"\xff\xd8\xff":
        return True
    if data[:8] == b"\x89PNG\r\n\x1a\n":
        return True
    if data[:6] in (b"GIF87a", b"GIF89a"):
        return True
    if data[:4] == b"RIFF" and data[8:12] == b"WEBP":
        return True
    head = data[:300].lower()
    if b"<html" in head or b"<!doctype" in head:
        return False
    return False


def slug(a: str) -> str:
    return re.sub(r"[^A-Za-z0-9._-]+", "_", a.strip())[:40] or "unknown"


def owner_from_url(url: str):
    m = re.search(r"albums/[^/]+/([^/]+)/", url)
    return unquote(m.group(1)) if m else None


def normalize(url: str) -> str:
    url = url.strip().replace("&amp;", "&")
    url = re.sub(r"/th_", "/", url)
    url = re.sub(r"/thumbs/", "/", url)
    return url


def collect(html_dirs: list[Path]):
    by = {}
    for d in html_dirs:
        if not d.is_dir():
            continue
        for f in sorted(d.glob("t*.html")):
            if f.stat().st_size < 2000:
                continue
            tid_m = re.match(r"t(\d+)_", f.name)
            if not tid_m:
                continue
            tid = tid_m.group(1)
            soup = BeautifulSoup(f.read_text(errors="replace"), "html.parser")
            for post in soup.select(".post"):
                ae = post.select_one(".username, .username-coloured")
                author = ae.get_text(strip=True) if ae else "unknown"
                content = post.select_one(".content")
                if not content:
                    continue
                urls = []
                for img in content.select("img"):
                    if img.get("src"):
                        urls.append(img["src"])
                for a in content.select("a[href]"):
                    href = a.get("href") or ""
                    if re.search(r"\.(jpe?g|png|gif|webp)(\?|$)", href, re.I):
                        urls.append(href)
                for m in re.findall(
                    r"https?://\S+\.(?:jpe?g|png|gif|webp)",
                    content.get_text("\n"),
                    re.I,
                ):
                    urls.append(m.rstrip(").,]'\" "))
                for u in urls:
                    if u.startswith("./") or u.startswith("/styles"):
                        continue
                    if SKIP.search(u) or "download/file.php?avatar" in u:
                        continue
                    u = normalize(u)
                    if not u.startswith("http"):
                        continue
                    if u not in by:
                        by[u] = {"url": u, "topics": set(), "authors": set(), "owner_hint": owner_from_url(u)}
                    by[u]["topics"].add(tid)
                    by[u]["authors"].add(author)
    items = []
    for u, m in by.items():
        items.append(
            {
                "url": u,
                "topics": sorted(m["topics"]),
                "authors": sorted(m["authors"]),
                "owner_hint": m["owner_hint"],
            }
        )
    return items


def wayback_url(url: str) -> str | None:
    # CDX via curl
    api = (
        "https://web.archive.org/cdx/search/cdx?url="
        + quote(url, safe="")
        + "&output=json&filter=statuscode:200&limit=5&fl=timestamp,original"
    )
    status, body, _ = curl_get(api, timeout=30)
    if status != 200 or not body:
        # try https variant
        if url.startswith("http://"):
            return wayback_url("https://" + url[7:]) if "https://" not in url else None
        return None
    try:
        data = json.loads(body.decode("utf-8", "replace"))
    except Exception:
        return None
    if not data or len(data) < 2:
        return None
    ts, original = data[-1][0], data[-1][1]
    return f"https://web.archive.org/web/{ts}id_/{original}"


def fetch(url: str) -> tuple[bytes | None, str, str]:
    # live
    for candidate in [url, url.replace("http://", "https://") if url.startswith("http://") else None]:
        if not candidate:
            continue
        status, body, ctype = curl_get(candidate, timeout=40)
        if status == 200 and is_image(body):
            return body, ctype, "live"
    # wayback
    wb = wayback_url(url)
    if wb:
        status, body, ctype = curl_get(wb, timeout=60)
        if status == 200 and is_image(body):
            return body, ctype, "wayback"
        wb2 = wb.replace("id_/", "")
        status, body, ctype = curl_get(wb2, timeout=60)
        if status == 200 and is_image(body):
            return body, ctype, "wayback-im"
    # photobucket path-only search on wayback
    if "photobucket.com" in url:
        path = urlparse(url).path
        api = (
            "https://web.archive.org/cdx/search/cdx?url="
            + quote("*" + path, safe="*")
            + "&output=json&filter=statuscode:200&limit=5&fl=timestamp,original"
        )
        status, body, _ = curl_get(api, timeout=30)
        if status == 200 and body:
            try:
                data = json.loads(body.decode("utf-8", "replace"))
                if len(data) > 1:
                    ts, original = data[-1][0], data[-1][1]
                    wb = f"https://web.archive.org/web/{ts}id_/{original}"
                    status, body, ctype = curl_get(wb, timeout=60)
                    if status == 200 and is_image(body):
                        return body, ctype, "wayback-path"
            except Exception:
                pass
    return None, "", "fail"


def ext_for(url: str, ctype: str, data: bytes) -> str:
    path = unquote(urlparse(url).path)
    m = re.search(r"\.(jpe?g|png|gif|webp)$", path, re.I)
    if m:
        return "." + m.group(1).lower().replace("jpeg", "jpg")
    if data[:3] == b"\xff\xd8\xff":
        return ".jpg"
    if data[:8] == b"\x89PNG\r\n\x1a\n":
        return ".png"
    if data[:6] in (b"GIF87a", b"GIF89a"):
        return ".gif"
    if "png" in (ctype or ""):
        return ".png"
    return ".jpg"


def main() -> None:
    global OUT, MANIFEST_DIR
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument(
        "--html",
        type=Path,
        action="append",
        dest="html_dirs",
        help="Directory of scraped t*_s*.html (repeatable). Default: /tmp/4guk_tor and /tmp/4guk_tor2",
    )
    ap.add_argument("--out", type=Path, default=Path("/tmp/4guk_imgs/all"))
    ap.add_argument("--manifest", type=Path, default=None, help="Manifest dir (default: <out>/../manifest)")
    args = ap.parse_args()

    html_dirs = args.html_dirs or [Path("/tmp/4guk_tor"), Path("/tmp/4guk_tor2")]
    OUT = args.out
    OUT.mkdir(parents=True, exist_ok=True)
    MANIFEST_DIR = args.manifest or (OUT.parent / "manifest")
    MANIFEST_DIR.mkdir(parents=True, exist_ok=True)

    items = collect(html_dirs)
    print(f"Found {len(items)} unique content images from {html_dirs}", flush=True)
    (MANIFEST_DIR / "images_meta.json").write_text(json.dumps(items, indent=2))
    logf = (MANIFEST_DIR / "download_log.jsonl").open("w")
    ok = fail = skip = 0
    for i, item in enumerate(items, 1):
        url = item["url"]
        primary = item["authors"][0] if item["authors"] else (item["owner_hint"] or "unknown")
        h = hashlib.sha1(url.encode()).hexdigest()[:10]
        base = Path(unquote(urlparse(url).path)).name
        base = re.sub(r"[^A-Za-z0-9._-]+", "_", base)[:80] or "img"
        existing = [
            p
            for p in OUT.glob(f"{slug(primary)}__{h}__*")
            if p.suffix != ".json" and not p.name.endswith(".meta.json")
        ]
        if not existing:
            existing = [
                p
                for p in OUT.glob(f"*__{h}__*")
                if not p.name.endswith(".meta.json") and p.stat().st_size > 500
            ]
        if existing and existing[0].stat().st_size > 500:
            print(f"[{i}/{len(items)}] SKIP {existing[0].name}", flush=True)
            skip += 1
            logf.write(json.dumps({**item, "status": "exists", "path": str(existing[0])}) + "\n")
            continue
        print(f"[{i}/{len(items)}] GET {url[:110]}", flush=True)
        data, ctype, source = fetch(url)
        if not data:
            print("  FAIL", flush=True)
            fail += 1
            logf.write(json.dumps({**item, "status": "fail"}) + "\n")
            time.sleep(0.2)
            continue
        ext = ext_for(url, ctype, data)
        fname = f"{slug(primary)}__{h}__{base}"
        fname = re.sub(r"\.(jpe?g|png|gif|webp|bin)$", "", fname, flags=re.I) + ext
        path = OUT / fname
        path.write_bytes(data)
        path.with_suffix(path.suffix + ".meta.json").write_text(
            json.dumps(
                {
                    "url": url,
                    "authors": item["authors"],
                    "owner_hint": item["owner_hint"],
                    "topics": item["topics"],
                    "source": source,
                    "bytes": len(data),
                    "primary_author": primary,
                },
                indent=2,
            )
        )
        path.with_suffix(path.suffix + ".author").write_text(primary + "\n")
        print(f"  OK {source} {len(data)}B -> {path.name}", flush=True)
        ok += 1
        logf.write(json.dumps({**item, "status": "ok", "source": source, "path": str(path), "bytes": len(data)}) + "\n")
        time.sleep(0.35)
    logf.close()
    summary = {"total": len(items), "ok": ok, "fail": fail, "skip": skip, "out": str(OUT)}
    (MANIFEST_DIR / "summary.json").write_text(json.dumps(summary, indent=2))
    print("DONE", summary, flush=True)


if __name__ == "__main__":
    main()
