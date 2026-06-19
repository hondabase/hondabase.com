#!/usr/bin/env python3
"""Rank Hondata help inventory topics for original Hondabase article conversion."""

from __future__ import annotations

import json
import re
from collections import Counter, defaultdict
from pathlib import Path


ROOT = Path("storage/app/imports/hondata-help")
MANIFEST = ROOT / "manifest.json"
OUT_JSON = ROOT / "article-candidates.json"
OUT_MD = ROOT / "article-candidates.md"

SKIP_TERMS = {
    "contents",
    "copyright",
    "privacy",
    "software license agreement",
    "vault terms of service",
    "send feedback",
    "quick support",
    "print",
    "email files",
    "check for updates",
    "update usb drivers",
    "language",
}

HIGH_VALUE_TERMS = {
    "install": 16,
    "installation": 16,
    "wiring": 18,
    "wideband": 16,
    "lambda": 12,
    "boost": 16,
    "traction": 14,
    "vtec": 14,
    "vtc": 12,
    "knock": 14,
    "fuel": 12,
    "ignition": 12,
    "injector": 14,
    "sensor": 12,
    "map": 12,
    "calibration": 10,
    "datalog": 10,
    "error": 12,
    "dtc": 14,
    "recovery": 14,
    "ecu": 10,
    "parameters": 8,
    "tables": 8,
    "tuning": 16,
}


def main() -> int:
    manifest = json.loads(MANIFEST.read_text())
    candidates = []

    for row in manifest:
        topic_path = ROOT / row["file"]
        topic = json.loads(topic_path.read_text())
        title = topic["title"]
        lower = title.lower()
        text = f"{topic.get('title', '')} {' '.join(topic.get('parents', []))} {topic.get('excerpt', '')}".lower()

        if any(term == lower or term in lower for term in SKIP_TERMS):
            continue

        score = 0
        reasons = []
        for signal in row.get("signals", []):
            score += {"how_to": 18, "troubleshooting": 16, "reference": 10}.get(signal, 0)
            reasons.append(signal)

        for term, weight in HIGH_VALUE_TERMS.items():
            if re.search(rf"\b{re.escape(term)}\b", text):
                score += weight
                reasons.append(term)

        word_count = int(row.get("word_count") or 0)
        if word_count >= 700:
            score += 12
            reasons.append("substantial")
        elif word_count >= 250:
            score += 6
            reasons.append("usable-length")
        elif word_count < 60:
            score -= 10
            reasons.append("thin")

        product = row["product"]
        article_type = classify_article_type(text, row.get("signals", []))
        category_hint = category_for(text)

        if score <= 10:
            continue

        candidates.append({
            "score": score,
            "product": product,
            "title": title,
            "source_url": row["url"],
            "topic_file": row["file"],
            "word_count": word_count,
            "article_type": article_type,
            "category_hint": category_hint,
            "reasons": sorted(set(reasons)),
            "source_parents": topic.get("parents", []),
        })

    candidates.sort(key=lambda item: (-item["score"], item["product"], item["title"]))
    OUT_JSON.write_text(json.dumps(candidates, indent=2, ensure_ascii=False) + "\n")
    OUT_MD.write_text(render_markdown(candidates))

    print(f"Wrote {len(candidates)} candidates to {OUT_JSON}")
    print("Top products:", dict(Counter(item["product"] for item in candidates).most_common()))
    return 0


def classify_article_type(text: str, signals: list[str]) -> str:
    if "troubleshooting" in signals or any(term in text for term in ["error", "dtc", "recovery", "reset"]):
        return "troubleshooting"
    if "how_to" in signals or any(term in text for term in ["install", "wiring", "setup", "configure"]):
        return "how-to"
    if any(term in text for term in ["parameters", "sensor", "table", "specification"]):
        return "reference"
    return "concept"


def category_for(text: str) -> str:
    if any(term in text for term in ["wiring", "sensor", "injector", "wideband", "lambda", "map"]):
        return "electronics"
    if any(term in text for term in ["tuning", "fuel", "ignition", "boost", "vtec", "vtc", "knock"]):
        return "engine-management"
    if any(term in text for term in ["datalog", "software", "manager", "calibration"]):
        return "software"
    return "reference"


def render_markdown(candidates: list[dict]) -> str:
    by_product: dict[str, list[dict]] = defaultdict(list)
    for candidate in candidates:
        by_product[candidate["product"]].append(candidate)

    lines = [
        "# Hondata Help Article Candidates",
        "",
        "Derived from public Hondata Support/help topic inventories. Use these as source links",
        "for original Hondabase articles; do not mirror the manuals verbatim.",
        "",
        f"Total candidates: {len(candidates)}",
        "",
    ]

    for product in sorted(by_product):
        rows = by_product[product]
        lines.extend([f"## {product}", "", f"Candidates: {len(rows)}", ""])
        for item in rows[:50]:
            parent = " > ".join(item["source_parents"])
            if parent:
                parent = f" ({parent})"
            lines.append(
                f"- **{item['score']}** [{item['title']}]({item['source_url']})"
                f"{parent} — {item['article_type']}, {item['category_hint']}; "
                f"{item['word_count']} words; reasons: {', '.join(item['reasons'][:8])}"
            )
        if len(rows) > 50:
            lines.append(f"- ... {len(rows) - 50} more in `article-candidates.json`")
        lines.append("")

    return "\n".join(lines)


if __name__ == "__main__":
    raise SystemExit(main())
