#!/usr/bin/env python3
"""Extract text from private source-image-archive content images via Gemini Batch API.

Reads content-addressed images under storage/app/private/source-image-archive/,
submits an inline Gemini batch job, and writes per-image + per-draft text packs
for article drafting.

Usage:
  python3 scripts/batch_ocr_source_images.py --submit
  python3 scripts/batch_ocr_source_images.py --poll batches/...
  python3 scripts/batch_ocr_source_images.py --collect batches/...
"""

from __future__ import annotations

import argparse
import json
import mimetypes
import os
import re
import time
from collections import defaultdict
from pathlib import Path

from google import genai
from google.genai import types

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE = ROOT / "storage/app/private/source-image-archive"
MANIFEST = ARCHIVE / "manifests/latest.json"
OUT_DIR = ROOT / "scratch/source-image-ocr"
DEFAULT_MODEL = "gemini-2.5-flash"

PROMPT = """You are extracting technical text from a photo or diagram for a Honda/Acura
repair knowledge base.

Transcribe ALL readable text, numbers, labels, table cells, callouts, and captions.
Preserve structure:
- Use Markdown tables when the image is a table or chart.
- Use numbered/bulleted lists for steps or parts lists.
- Keep units (ft-lbs, Nm, mm, psi, AWG, etc.) exactly as shown.
- If text is in Russian or another language, transcribe it as written, then add an English
  translation of the technical content underneath under a heading "English translation:".
- If the image is pure photo with no useful technical text, reply with exactly: [NO TEXT]
- Do not invent part numbers, torque values, or steps that are not visible.
- Ignore website chrome, ads, watermarks, navigation, and storefront UI.

Output only the extracted content (or [NO TEXT])."""


def load_api_key() -> str:
    env = os.environ.get("GEMINI_API_KEY")
    if env:
        return env.strip()
    for path in (ROOT / ".gemini_api_key", Path(".gemini_api_key")):
        if path.is_file() and path.read_text().strip():
            return path.read_text().strip()
    raise SystemExit("GEMINI_API_KEY not found (env or .gemini_api_key)")


def mime_for(path: Path, declared: str | None) -> str:
    if declared and declared.startswith("image/"):
        return declared.split(";")[0].strip()
    guess, _ = mimetypes.guess_type(str(path))
    if guess and guess.startswith("image/"):
        return guess
    ext = path.suffix.lower()
    return {
        ".jpg": "image/jpeg",
        ".jpeg": "image/jpeg",
        ".png": "image/png",
        ".webp": "image/webp",
        ".gif": "image/gif",
    }.get(ext, "image/jpeg")


def collect_images() -> list[dict]:
    manifest = json.loads(MANIFEST.read_text())
    by_sha: dict[str, dict] = {}

    for img in manifest["images"]:
        if img.get("role") == "site-wide":
            continue
        path_rel = img.get("object_path")
        if not path_rel:
            continue
        path = ARCHIVE / path_rel
        if not path.is_file():
            continue

        drafts: set[int] = set()
        article_paths: set[str] = set()
        alts: list[str] = []
        filenames: list[str] = []
        for occ in img.get("occurrences") or []:
            if occ.get("draft_id"):
                drafts.add(int(occ["draft_id"]))
            if occ.get("article_path"):
                article_paths.add(occ["article_path"])
            if occ.get("alt"):
                alts.append(str(occ["alt"]))
            if occ.get("source_filename"):
                filenames.append(str(occ["source_filename"]))
        if not drafts:
            continue

        w, h = img.get("width") or 0, img.get("height") or 0
        nbytes = img.get("bytes") or path.stat().st_size
        if w and h and (w < 120 or h < 80):
            continue
        if w and h and h < 60 and w > 400:
            continue
        if nbytes < 3000:
            continue

        sha = img["sha256"]
        if sha in by_sha:
            by_sha[sha]["drafts"] |= drafts
            by_sha[sha]["article_paths"] |= article_paths
            continue

        by_sha[sha] = {
            "sha256": sha,
            "object_path": path_rel,
            "abs_path": str(path),
            "mime": mime_for(path, img.get("mime_type") or img.get("content_type")),
            "width": w,
            "height": h,
            "bytes": nbytes,
            "drafts": drafts,
            "article_paths": article_paths,
            "alt": alts[0] if alts else None,
            "filename": filenames[0] if filenames else None,
        }

    items = list(by_sha.values())
    for item in items:
        item["drafts"] = sorted(item["drafts"])
        item["article_paths"] = sorted(item["article_paths"])
    items.sort(key=lambda x: x["sha256"])
    return items


def build_requests(images: list[dict], model: str) -> list[types.InlinedRequest]:
    requests: list[types.InlinedRequest] = []
    for item in images:
        path = Path(item["abs_path"])
        data = path.read_bytes()
        img_part = types.Part.from_bytes(data=data, mime_type=item["mime"])
        context = (
            f"Filename: {item.get('filename') or path.name}\n"
            f"Alt text: {item.get('alt') or '(none)'}\n"
            f"Draft IDs: {', '.join(map(str, item['drafts']))}\n"
            f"Articles: {', '.join(item['article_paths']) or '(unknown)'}\n"
        )
        text_part = types.Part.from_text(text=PROMPT + "\n\n" + context)
        requests.append(
            types.InlinedRequest(
                model=model,
                contents=[types.Content(role="user", parts=[text_part, img_part])],
                metadata={
                    "sha256": item["sha256"],
                    "object_path": item["object_path"],
                    "filename": item.get("filename") or "",
                    "drafts": ",".join(map(str, item["drafts"])),
                    "article_paths": "|".join(item["article_paths"]),
                },
                config=types.GenerateContentConfig(
                    temperature=0.1,
                    response_modalities=["TEXT"],
                ),
            )
        )
    return requests


def response_text(ir) -> str | None:
    if ir.error:
        return None
    resp = ir.response
    if not resp:
        return None
    if getattr(resp, "text", None):
        return resp.text
    try:
        parts = resp.candidates[0].content.parts
    except Exception:
        return None
    chunks = []
    for part in parts or []:
        t = getattr(part, "text", None)
        if t:
            chunks.append(t)
    return "\n".join(chunks).strip() if chunks else None


def write_inventory(images: list[dict]) -> Path:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    inv = OUT_DIR / "inventory.json"
    inv.write_text(json.dumps(images, indent=2), encoding="utf-8")
    return inv


def submit(
    model: str,
    limit: int | None,
    display_name: str,
    chunk_size: int = 40,
) -> list[str]:
    """Submit one or more batch jobs (chunked to stay under inline payload limits)."""
    images = collect_images()
    if limit:
        images = images[:limit]
    write_inventory(images)
    print(f"Images selected: {len(images)}")
    print(f"Total bytes: {sum(i['bytes'] or 0 for i in images) / 1e6:.1f} MB")
    drafts = set()
    for i in images:
        drafts.update(i["drafts"])
    print(f"Drafts covered: {len(drafts)}")

    client = genai.Client(api_key=load_api_key())
    job_names: list[str] = []
    chunks = [images[i : i + chunk_size] for i in range(0, len(images), chunk_size)]
    jobs_meta: list[dict] = []

    for idx, chunk in enumerate(chunks, 1):
        requests = build_requests(chunk, model)
        name = f"{display_name}-{idx:02d}-of-{len(chunks):02d}"
        print(
            f"Submitting chunk {idx}/{len(chunks)} "
            f"({len(requests)} images, {sum(c['bytes'] or 0 for c in chunk)/1e6:.1f} MB)..."
        )
        job = client.batches.create(
            model=model,
            src=requests,
            config={"display_name": name},
        )
        job_names.append(job.name)
        jobs_meta.append(
            {
                "name": job.name,
                "display_name": name,
                "chunk": idx,
                "chunks": len(chunks),
                "image_count": len(chunk),
                "state": str(job.state),
            }
        )
        print(f"  -> {job.name} {job.state}")

    meta = {
        "model": model,
        "jobs": jobs_meta,
        "job_names": job_names,
        "image_count": len(images),
        "draft_count": len(drafts),
        "chunk_size": chunk_size,
        "submitted_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
    }
    (OUT_DIR / "last_job.json").write_text(json.dumps(meta, indent=2), encoding="utf-8")
    print(json.dumps(meta, indent=2))
    return job_names


def poll(job_names: list[str], seconds: int) -> None:
    client = genai.Client(api_key=load_api_key())
    pending = set(job_names)
    while pending:
        for name in list(pending):
            job = client.batches.get(name=name)
            state = str(job.state).split(".")[-1]
            print(time.strftime("%H:%M:%S"), name, state)
            if any(x in state for x in ("SUCCEEDED", "FAILED", "CANCELLED", "EXPIRED")):
                if "FAILED" in state:
                    print("  error:", getattr(job, "error", None))
                pending.discard(name)
        if pending:
            time.sleep(seconds)


def collect(job_names: list[str]) -> None:
    client = genai.Client(api_key=load_api_key())
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    per_image_dir = OUT_DIR / "by-image"
    per_image_dir.mkdir(exist_ok=True)

    inventory_path = OUT_DIR / "inventory.json"
    inventory = (
        {i["sha256"]: i for i in json.loads(inventory_path.read_text())}
        if inventory_path.is_file()
        else {}
    )
    by_draft: dict[int, list[dict]] = defaultdict(list)
    results: list[dict] = []
    errors = 0
    empty = 0
    job_states: dict[str, str] = {}

    for job_name in job_names:
        job = client.batches.get(name=job_name)
        state = str(job.state).split(".")[-1]
        job_states[job_name] = state
        print("Job state:", job_name, state)
        if "SUCCEEDED" not in state:
            print(f"  skipping non-succeeded job: {state}")
            continue

        responses = []
        if getattr(job, "dest", None) and getattr(job.dest, "inlined_responses", None):
            responses = job.dest.inlined_responses
        elif getattr(job, "dest", None) and getattr(job.dest, "file_name", None):
            print("  file destination:", job.dest.file_name)
            raise SystemExit("File-based batch destination not implemented; use inlined responses")

        for ir in responses or []:
            meta = ir.metadata or {}
            sha = meta.get("sha256") or "unknown"
            text = response_text(ir)
            err = str(ir.error) if ir.error else None
            if err:
                errors += 1
                print("ERROR", sha[:12], err[:160])
            if not text:
                text = ""
            if text.strip() == "[NO TEXT]" or not text.strip():
                empty += 1

            rec = {
                "sha256": sha,
                "object_path": meta.get("object_path"),
                "filename": meta.get("filename"),
                "drafts": [int(x) for x in (meta.get("drafts") or "").split(",") if x],
                "article_paths": [p for p in (meta.get("article_paths") or "").split("|") if p],
                "error": err,
                "text": text.strip(),
                "job": job_name,
            }
            if not rec["drafts"] and sha in inventory:
                rec["drafts"] = inventory[sha]["drafts"]
                rec["article_paths"] = inventory[sha]["article_paths"]

            results.append(rec)
            (per_image_dir / f"{sha}.md").write_text(text.strip() + "\n", encoding="utf-8")
            for did in rec["drafts"]:
                by_draft[did].append(rec)

    (OUT_DIR / "results.json").write_text(
        json.dumps(results, indent=2, ensure_ascii=False),
        encoding="utf-8",
    )

    drafts_dir = OUT_DIR / "by-draft"
    drafts_dir.mkdir(exist_ok=True)
    for did, items in sorted(by_draft.items()):
        lines = [f"# Draft {did} — OCR extracts from source images", ""]
        for i, rec in enumerate(items, 1):
            if not rec["text"] or rec["text"] == "[NO TEXT]":
                continue
            lines.append(f"## Image {i}: {rec.get('filename') or rec['sha256'][:12]}")
            if rec.get("article_paths"):
                lines.append(f"Articles: {', '.join(rec['article_paths'])}")
            lines.append("")
            lines.append(rec["text"])
            lines.append("")
        (drafts_dir / f"{did:03d}.md").write_text("\n".join(lines).rstrip() + "\n", encoding="utf-8")

    summary = {
        "jobs": job_states,
        "images": len(results),
        "errors": errors,
        "empty_or_no_text": empty,
        "drafts_with_extracts": len(by_draft),
        "out_dir": str(OUT_DIR),
    }
    (OUT_DIR / "summary.json").write_text(json.dumps(summary, indent=2), encoding="utf-8")
    print(json.dumps(summary, indent=2))


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--submit", action="store_true", help="Submit new batch job(s)")
    parser.add_argument(
        "--poll",
        nargs="*",
        metavar="JOB",
        help="Poll job(s) until done (default: last_job.json)",
    )
    parser.add_argument(
        "--collect",
        nargs="*",
        metavar="JOB",
        help="Collect results from job(s) (default: last_job.json)",
    )
    parser.add_argument("--model", default=DEFAULT_MODEL)
    parser.add_argument("--limit", type=int, default=None, help="Limit image count (debug)")
    parser.add_argument("--chunk-size", type=int, default=40, help="Images per batch job")
    parser.add_argument("--poll-seconds", type=int, default=30)
    parser.add_argument("--display-name", default="hondabase-source-image-ocr")
    parser.add_argument("--inventory-only", action="store_true")
    parser.add_argument(
        "--submit-only",
        action="store_true",
        help="With --submit: do not poll/collect",
    )
    args = parser.parse_args()

    def jobs_from_args_or_last(arg_list: list[str] | None) -> list[str]:
        if arg_list:
            return arg_list
        last = OUT_DIR / "last_job.json"
        if not last.is_file():
            raise SystemExit("No job names given and scratch/source-image-ocr/last_job.json missing")
        meta = json.loads(last.read_text())
        if meta.get("job_names"):
            return list(meta["job_names"])
        if meta.get("name"):
            return [meta["name"]]
        raise SystemExit("last_job.json has no job names")

    if args.inventory_only:
        images = collect_images()
        path = write_inventory(images)
        print(f"Wrote {len(images)} images to {path}")
        return

    if args.submit:
        names = submit(args.model, args.limit, args.display_name, args.chunk_size)
        if not args.submit_only:
            poll(names, args.poll_seconds)
            collect(names)
        return

    if args.poll is not None:
        poll(jobs_from_args_or_last(args.poll), args.poll_seconds)
        return

    if args.collect is not None:
        collect(jobs_from_args_or_last(args.collect))
        return

    parser.error("Specify --submit, --poll [JOB...], --collect [JOB...], or --inventory-only")


if __name__ == "__main__":
    main()
