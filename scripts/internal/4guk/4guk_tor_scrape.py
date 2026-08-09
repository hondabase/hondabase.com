#!/usr/bin/env python3
"""Scrape 4GUK phpBB topics via Tor after the VPS IP is BitNinja-banned.

Prereqs:
  - Tor listening on 127.0.0.1:9050
  - pip install curl_cffi
  - env 4GUK_USER / 4GUK_PASS (do not hardcode secrets in git)

Example (env names start with a digit: use `env`, not bash `export`):
  env "4GUK_USER=viruxe" "4GUK_PASS=..." python3 4guk_tor_scrape.py \\
    --socks 9101 --out /tmp/4guk_out --topics 6353,28061,6493
"""

from __future__ import annotations

import argparse
import os
import re
import sys
import time
from pathlib import Path

from curl_cffi import requests


def make_proxies(port: int) -> dict:
    url = f"socks5h://127.0.0.1:{port}"
    return {"http": url, "https": url}


def login(session: requests.Session, user: str, password: str, proxies: dict) -> None:
    r = session.get(
        "https://4guk.co.uk/ucp.php?mode=login",
        impersonate="chrome124",
        timeout=60,
        proxies=proxies,
    )
    r.raise_for_status()
    html = r.text
    sid = re.search(r'name="sid" value="([^"]*)"', html)
    ct = re.search(r'name="creation_time" value="([^"]*)"', html)
    ft = re.search(r'name="form_token" value="([^"]*)"', html)
    if not (sid and ct and ft):
        raise SystemExit("Login form tokens missing (blocked or layout change)")

    data = {
        "username": user,
        "password": password,
        "autologin": "on",
        "redirect": "index.php",
        "creation_time": ct.group(1),
        "form_token": ft.group(1),
        "sid": sid.group(1),
        "login": "Login",
    }
    r2 = session.post(
        f"https://4guk.co.uk/ucp.php?mode=login&sid={sid.group(1)}",
        data=data,
        headers={
            "Referer": "https://4guk.co.uk/ucp.php?mode=login",
            "Origin": "https://4guk.co.uk",
        },
        impersonate="chrome124",
        timeout=60,
        proxies=proxies,
        allow_redirects=True,
    )
    r2.raise_for_status()
    uid = session.cookies.get("phpbb3_kl9jj_u")
    if not uid or uid == "1":
        raise SystemExit(f"Login failed (user cookie={uid!r})")
    print(f"Logged in uid={uid} page_len={len(r2.text)}")


def fetch_topic(session: requests.Session, topic_id: int, start: int, out: Path, proxies: dict) -> int:
    url = f"https://4guk.co.uk/viewtopic.php?t={topic_id}&start={start}"
    r = session.get(url, impersonate="chrome124", timeout=60, proxies=proxies)
    path = out / f"t{topic_id}_s{start}.html"
    path.write_text(r.text, encoding="utf-8", errors="replace")
    title_m = re.search(r"<title>([^<]+)", r.text)
    title = title_m.group(1) if title_m else "?"
    posts = len(re.findall(r'class="content"', r.text))
    print(f"t={topic_id} start={start} status={r.status_code} len={len(r.text)} posts~{posts} {title[:70]}")
    if r.status_code == 403 or "403 Forbidden" in r.text[:500]:
        return -1
    if "Login" in title and posts < 2:
        return 0
    return posts


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--out", type=Path, default=Path("/tmp/4guk_out"))
    ap.add_argument("--topics", default="6353,28061,6493,22185,14808", help="comma-separated topic ids")
    ap.add_argument("--pages", type=int, default=4, help="max pages per topic (15 posts/page)")
    ap.add_argument("--delay", type=float, default=1.2)
    ap.add_argument("--socks", type=int, default=9050, help="local Tor SocksPort")
    args = ap.parse_args()

    user = os.environ.get("4GUK_USER")
    password = os.environ.get("4GUK_PASS")
    if not user or not password:
        print("Set 4GUK_USER and 4GUK_PASS", file=sys.stderr)
        sys.exit(1)

    args.out.mkdir(parents=True, exist_ok=True)
    proxies = make_proxies(args.socks)
    print(f"Using SOCKS port {args.socks}")
    session = requests.Session()
    login(session, user, password, proxies)

    for raw in args.topics.split(","):
        tid = int(raw.strip())
        for pnum in range(args.pages):
            start = pnum * 15
            posts = fetch_topic(session, tid, start, args.out, proxies)
            if posts < 0:
                print("Exit blocked (403). Rotate Tor circuit and retry.")
                break
            if posts == 0 and pnum == 0:
                print("Login wall or empty; stop topic.")
                break
            if posts < 3 and pnum > 0:
                break
            time.sleep(args.delay)
        time.sleep(args.delay)


if __name__ == "__main__":
    main()
