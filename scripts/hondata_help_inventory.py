#!/usr/bin/env python3
"""
Inventory public Hondata Support/help documentation.

Writes one JSON metadata file per help topic. By default it stores title, URL, hierarchy,
small excerpts, and conversion signals, not full manual text.
"""

from __future__ import annotations

import argparse
import html
import json
import re
import subprocess
import time
from dataclasses import dataclass
from html.parser import HTMLParser
from pathlib import Path
from typing import Iterable
from urllib.parse import urljoin, urlparse
from urllib.request import Request, urlopen


USER_AGENT = (
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/125.0 Safari/537.36"
)


@dataclass
class SupportLink:
    label: str
    url: str


@dataclass
class TocEntry:
    section_id: str
    level: int
    title: str
    url: str | None
    parents: list[str]


class SupportParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.in_h3 = False
        self.h3_text = ""
        self.in_support = False
        self.capture_depth = 0
        self.current_href: str | None = None
        self.current_text: list[str] = []
        self.links: list[SupportLink] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attr = dict(attrs)
        if tag == "h3":
            self.in_h3 = True
            self.h3_text = ""
        elif self.in_support:
            self.capture_depth += 1
            if tag == "a" and attr.get("href"):
                self.current_href = attr["href"]
                self.current_text = []

    def handle_endtag(self, tag: str) -> None:
        if tag == "h3" and self.in_h3:
            self.in_h3 = False
            self.in_support = normalize(self.h3_text) == "Support"
            self.capture_depth = 0
            return

        if self.current_href and tag == "a":
            label = normalize(" ".join(self.current_text))
            if label:
                self.links.append(SupportLink(label=label, url=self.current_href))
            self.current_href = None
            self.current_text = []

        if self.in_support and tag == "div":
            self.capture_depth -= 1
            if self.capture_depth <= -1:
                self.in_support = False

    def handle_data(self, data: str) -> None:
        if self.in_h3:
            self.h3_text += data
        if self.current_href:
            self.current_text.append(data)


class TocParser(HTMLParser):
    def __init__(self, base_url: str) -> None:
        super().__init__(convert_charrefs=True)
        self.base_url = base_url
        self.current_href: str | None = None
        self.current_id: str | None = None
        self.current_class: str | None = None
        self.current_text: list[str] = []
        self.entries: list[tuple[str, int, str, str | None]] = []
        self.heading_stack: dict[int, str] = {}

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attr = dict(attrs)
        if tag == "a" and attr.get("href"):
            self.current_href = urljoin(self.base_url, attr["href"])
        if tag == "span" and attr.get("id", "").startswith("s"):
            self.current_id = attr["id"]
            self.current_class = attr.get("class")
            self.current_text = []

    def handle_endtag(self, tag: str) -> None:
        if tag == "span" and self.current_id:
            title = normalize(" ".join(self.current_text))
            level = heading_level(self.current_class or "", self.current_id)
            if title:
                self.entries.append((self.current_id, level, title, self.current_href))
            self.current_id = None
            self.current_class = None
            self.current_text = []
        if tag == "a":
            self.current_href = None

    def handle_data(self, data: str) -> None:
        if self.current_id:
            self.current_text.append(data)

    def toc_entries(self) -> list[TocEntry]:
        out: list[TocEntry] = []
        parents: dict[int, str] = {}
        for section_id, level, title, url in self.entries:
            parent_titles = [parents[i] for i in sorted(parents) if i < level]
            out.append(TocEntry(section_id, level, title, url, parent_titles))
            parents[level] = title
            for existing in list(parents):
                if existing > level:
                    del parents[existing]
        return out


class TextParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.skip_depth = 0
        self.parts: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        if tag in {"script", "style", "noscript"}:
            self.skip_depth += 1
        elif tag in {"p", "div", "br", "li", "tr", "h1", "h2", "h3", "h4"}:
            self.parts.append("\n")

    def handle_endtag(self, tag: str) -> None:
        if tag in {"script", "style", "noscript"} and self.skip_depth:
            self.skip_depth -= 1
        elif tag in {"p", "div", "li", "tr", "h1", "h2", "h3", "h4"}:
            self.parts.append("\n")

    def handle_data(self, data: str) -> None:
        if not self.skip_depth:
            self.parts.append(data)

    def text(self) -> str:
        return normalize("\n".join(self.parts))


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--start-url", default="https://www.hondata.com/help/")
    parser.add_argument("--out", default="storage/app/imports/hondata-help")
    parser.add_argument("--delay-ms", type=int, default=500)
    parser.add_argument("--max-topics", type=int)
    args = parser.parse_args()

    out_dir = Path(args.out)
    topics_dir = out_dir / "topics"
    topics_dir.mkdir(parents=True, exist_ok=True)

    try:
        support_html = fetch(args.start_url)
        support_links = parse_support_links(support_html, args.start_url)
    except Exception:
        support_links = fallback_support_links(args.start_url)
    topic_count = 0
    manifest = []

    for support in support_links:
        if support.label.lower() in {"forum", "downloads"}:
            continue
        if support.label.lower() == "tech":
            continue

        index_url = support.url if support.url.endswith("/") else support.url
        index_html = fetch(index_url)
        toc_url = find_toc_url(index_html, index_url)
        if not toc_url:
            continue

        sleep(args.delay_ms)
        toc_html = fetch(toc_url)
        toc_entries = parse_toc(toc_html, toc_url)
        for entry in toc_entries:
            if not entry.url:
                continue
            sleep(args.delay_ms)
            topic_html = fetch(entry.url)
            text = html_to_text(topic_html)
            record = {
                "source": "hondata.com/help",
                "product": support.label,
                "section_id": entry.section_id,
                "level": entry.level,
                "parents": entry.parents,
                "title": entry.title,
                "url": entry.url,
                "fetched_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
                "excerpt": text[:900],
                "word_count": len(text.split()),
                "convertibility_signals": classify(entry.title + " " + text[:2000]),
            }
            filename = f"{slugify(support.label)}-{slugify(entry.section_id)}-{slugify(entry.title)}.json"
            (topics_dir / filename).write_text(json.dumps(record, indent=2, ensure_ascii=False) + "\n")
            manifest.append({
                "product": support.label,
                "title": entry.title,
                "url": entry.url,
                "file": f"topics/{filename}",
                "signals": record["convertibility_signals"],
                "word_count": record["word_count"],
            })
            topic_count += 1
            if args.max_topics and topic_count >= args.max_topics:
                break
        if args.max_topics and topic_count >= args.max_topics:
            break

    (out_dir / "manifest.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n")
    (out_dir / "run.json").write_text(json.dumps({
        "start_url": args.start_url,
        "generated_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        "support_links": [link.__dict__ for link in support_links],
        "topics_written": topic_count,
        "content_mode": "metadata_and_short_excerpts",
    }, indent=2, ensure_ascii=False) + "\n")
    print(f"Wrote {topic_count} help topic inventories to {topics_dir}")
    return 0


def fetch(url: str) -> str:
    request = Request(url, headers={"User-Agent": USER_AGENT, "Accept": "text/html,application/xhtml+xml"})
    try:
        with urlopen(request, timeout=45) as response:
            raw = response.read()
            charset = response.headers.get_content_charset() or "utf-8"
            return raw.decode(charset, errors="replace")
    except Exception:
        result = subprocess.run(["curl", "-fsSL", url], check=True, capture_output=True)
        return result.stdout.decode("utf-8", errors="replace")


def parse_support_links(source: str, base_url: str) -> list[SupportLink]:
    parser = SupportParser()
    parser.feed(source)
    seen = set()
    links = []
    for link in parser.links:
        absolute = urljoin(base_url, link.url)
        key = (link.label, absolute)
        if key not in seen:
            seen.add(key)
            links.append(SupportLink(link.label, absolute))
    return links


def fallback_support_links(base_url: str) -> list[SupportLink]:
    parsed = urlparse(base_url)
    origin = f"{parsed.scheme}://{parsed.netloc}/" if parsed.scheme and parsed.netloc else base_url
    seeded = [
        ("s300 Help", "help/smanager/index.html"),
        ("K-Pro Help", "help/kmanager/index.html"),
        ("FlashPro Help", "help/flashpro/index.html"),
        ("Traction Control", "help/tractioncontrol/index.html"),
        ("CPR Help", "help/cpr/index.html"),
        ("Injector Driver Help", "help/injectordriver/index.html"),
        ("Strain Gauge Help", "help/strain/index.html"),
    ]
    return [SupportLink(label, urljoin(origin, url)) for label, url in seeded]


def find_toc_url(index_html: str, index_url: str) -> str | None:
    matches = re.findall(r'["\']([^"\']*content_static\.html)["\']', index_html, re.I)
    if matches:
        return urljoin(index_url, html.unescape(matches[0]))
    path = urlparse(index_url).path.rstrip("/")
    name = path.rsplit("/", 1)[-1]
    if name:
        return urljoin(index_url, f"{name}_content_static.html")
    return None


def parse_toc(source: str, toc_url: str) -> list[TocEntry]:
    parser = TocParser(toc_url)
    parser.feed(source)
    return parser.toc_entries()


def html_to_text(source: str) -> str:
    parser = TextParser()
    parser.feed(source)
    return parser.text()


def classify(text: str) -> list[str]:
    lower = text.lower()
    signals = []
    needles = {
        "how_to": ["install", "setup", "configure", "calibrate", "tuning", "wiring", "uploading", "downloading"],
        "troubleshooting": ["error", "dtc", "recovery", "reset", "warning", "problem", "fail"],
        "reference": ["sensor", "parameters", "table", "specification", "lambda", "boost", "vtec", "ecu"],
    }
    for signal, words in needles.items():
        if any(word in lower for word in words):
            signals.append(signal)
    return signals


def heading_level(class_name: str, section_id: str) -> int:
    match = re.search(r"heading(\d+)", class_name)
    if match:
        return int(match.group(1))
    return section_id.count(".") + 1


def normalize(text: str) -> str:
    return re.sub(r"[ \t\r\f\v]+", " ", re.sub(r"\n\s*", "\n", html.unescape(text))).strip()


def slugify(text: str) -> str:
    slug = re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")
    return slug or "topic"


def sleep(delay_ms: int) -> None:
    if delay_ms > 0:
        time.sleep(delay_ms / 1000)


if __name__ == "__main__":
    raise SystemExit(main())
