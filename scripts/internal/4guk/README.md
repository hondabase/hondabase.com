# scripts/internal/4guk

Supporting scripts for the dual-carb / 4GUK research pipeline.

Full runbook: [docs/internal/4guk-forum-research.md](../../../docs/internal/4guk-forum-research.md).

| Script | Role |
| --- | --- |
| `4guk_tor_scrape.py` | Login via Tor SOCKS; download topic HTML pages |
| `download_topic_images.py` | From scraped HTML, download post images (live + Wayback) |
| `remove_photobucket.py` | Gemini inpaint Photobucket overlays; Pillow poster watermark |

## Quick commands

```bash
# 1) Scrape (credentials: use env with leading-digit names via env(1), not bash export)
env "4GUK_USER=..." "4GUK_PASS=..." \
  python3 scripts/internal/4guk/4guk_tor_scrape.py \
    --socks 9101 --out /tmp/4guk_tor2 --topics 6353,28061

# 2) Images
python3 scripts/internal/4guk/download_topic_images.py \
  --html /tmp/4guk_tor2 --out /tmp/4guk_imgs/all

# 3) Clean + watermark
python3 scripts/internal/4guk/remove_photobucket.py \
  --src /tmp/4guk_imgs/publish_src \
  --out /tmp/4guk_imgs/cleaned \
  --wm /tmp/4guk_imgs/wm_pub \
  --authors /tmp/4guk_imgs/authors.json
```

Dependencies: `curl_cffi`, `beautifulsoup4`, `Pillow`, `google-genai`, system `curl`, optional Tor.
