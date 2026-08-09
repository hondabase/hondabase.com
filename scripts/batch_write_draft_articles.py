#!/usr/bin/env python3
"""Write full Hondabase draft article bodies via Gemini Batch API.

Reads packs from scratch/draft-source-packs/NNN.json, submits chunked batch jobs,
collects Markdown bodies + improved summaries, and optionally applies them to
article_drafts for user viruxe.

Usage:
  python3 scripts/batch_write_draft_articles.py --submit --submit-only
  python3 scripts/batch_write_draft_articles.py --poll
  python3 scripts/batch_write_draft_articles.py --collect
  python3 scripts/batch_write_draft_articles.py --apply
"""

from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import time
from pathlib import Path

from google import genai
from google.genai import types

ROOT = Path(__file__).resolve().parents[1]
PACK_DIR = ROOT / "scratch/draft-source-packs"
OUT_DIR = ROOT / "scratch/draft-bodies"
DEFAULT_MODEL = "gemini-2.5-flash"
RULES = (ROOT / "docs/ARTICLE_FORMATTING_RULES.md").read_text(encoding="utf-8")[:5000]

SYSTEM_PROMPT = f"""You are writing technical Honda/Acura knowledge-base articles for Hondabase.

Write ORIGINAL English technical copy informed by the source notes. Do NOT copy source prose
verbatim. Do NOT invent exact torque values, part numbers, wire colors, or measurements that are
not supported by the provided source material or standard widely-known Honda service practice.
When a specific figure is uncertain, state that it must be confirmed against the factory service
manual.

Follow these formatting rules:
{RULES}

Output requirements (strict):
1. Return ONLY a JSON object (no markdown fences) with keys:
   - "summary": string, 1–2 SEO sentences, max ~158 characters, no "scaffold" wording
   - "body": string, full Markdown body starting with a single # title
2. Body structure: scannable ## / ### sections, tables where useful, GFM alerts
   (> [!NOTE], > [!WARNING], > [!IMPORTANT], > [!TIP], > [!CAUTION]) where appropriate.
3. No TODO placeholders. No "article scaffold" language. No site branding (nthefastlane, icelord,
   pgmfi, "we", personal diary voice). Platform-agnostic professional tone.
4. Preserve applicability from the provided tags/applies_to/complexity; expand only when sources
   clearly support it.
5. If sources are thin, still write a complete useful article: procedure structure, safety,
   inspection points, verification, and explicit "confirm against FSM" callouts rather than
   fabricated numbers.
6. Do not include YAML frontmatter in body — body is Markdown only.
7. Sources stay attributed externally; do not paste long copyrighted blocks.
"""


def load_api_key() -> str:
    env = os.environ.get("GEMINI_API_KEY")
    if env:
        return env.strip()
    for path in (ROOT / ".gemini_api_key", Path(".gemini_api_key")):
        if path.is_file() and path.read_text().strip():
            return path.read_text().strip()
    raise SystemExit("GEMINI_API_KEY not found")


def load_packs(limit: int | None = None) -> list[dict]:
    packs = []
    for path in sorted(PACK_DIR.glob("[0-9][0-9][0-9].json")):
        packs.append(json.loads(path.read_text(encoding="utf-8")))
    if limit:
        packs = packs[:limit]
    return packs


def pack_prompt(pack: dict) -> str:
    sources_txt = []
    for s in pack.get("source_pages") or []:
        sources_txt.append(
            f"### {s.get('site')}: {s.get('title')}\nURL: {s.get('url')}\n\n{s.get('text') or '(no extractable HTML text)'}\n"
        )
    ocr = (pack.get("ocr") or "").strip()
    return f"""Write the Hondabase article for this private draft.

Draft ID: {pack['draft_id']}
Title: {pack['title']}
Path: {pack['path']}
Category: {pack['category']}
Slug: {pack['slug']}
Tags: {json.dumps(pack.get('tags'), ensure_ascii=False)}
Applies to: {json.dumps(pack.get('applies_to'), ensure_ascii=False)}
Complexity: {pack.get('complexity')}

## Source page extracts
{chr(10).join(sources_txt) if sources_txt else '(none)'}

## OCR text from source images (may be noisy)
{ocr if ocr else '(none)'}

Return JSON with keys summary and body only.
"""


def build_requests(packs: list[dict], model: str) -> list[types.InlinedRequest]:
    reqs = []
    for pack in packs:
        prompt = SYSTEM_PROMPT + "\n\n" + pack_prompt(pack)
        reqs.append(
            types.InlinedRequest(
                model=model,
                contents=[
                    types.Content(
                        role="user",
                        parts=[types.Part.from_text(text=prompt)],
                    )
                ],
                metadata={
                    "draft_id": str(pack["draft_id"]),
                    "title": pack["title"],
                    "path": pack["path"],
                    "slug": pack["slug"],
                },
                config=types.GenerateContentConfig(
                    temperature=0.35,
                    response_modalities=["TEXT"],
                    response_mime_type="application/json",
                ),
            )
        )
    return reqs


def submit(model: str, limit: int | None, chunk_size: int, display_name: str) -> list[str]:
    packs = load_packs(limit)
    if not packs:
        raise SystemExit(f"No packs in {PACK_DIR}")
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    (OUT_DIR / "packs_submitted.json").write_text(
        json.dumps([{"id": p["draft_id"], "title": p["title"], "path": p["path"]} for p in packs], indent=2),
        encoding="utf-8",
    )

    client = genai.Client(api_key=load_api_key())
    chunks = [packs[i : i + chunk_size] for i in range(0, len(packs), chunk_size)]
    job_names: list[str] = []
    jobs_meta = []
    for idx, chunk in enumerate(chunks, 1):
        reqs = build_requests(chunk, model)
        name = f"{display_name}-{idx:02d}-of-{len(chunks):02d}"
        print(f"Submitting article chunk {idx}/{len(chunks)} ({len(reqs)} drafts)...")
        job = client.batches.create(
            model=model,
            src=reqs,
            config={"display_name": name},
        )
        job_names.append(job.name)
        jobs_meta.append(
            {
                "name": job.name,
                "display_name": name,
                "chunk": idx,
                "chunks": len(chunks),
                "count": len(chunk),
                "state": str(job.state),
            }
        )
        print(f"  -> {job.name} {job.state}")

    meta = {
        "model": model,
        "jobs": jobs_meta,
        "job_names": job_names,
        "draft_count": len(packs),
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
    chunks = [getattr(p, "text", None) or "" for p in (parts or [])]
    text = "\n".join(c for c in chunks if c).strip()
    return text or None


def parse_article_json(raw: str) -> dict | None:
    if not raw:
        return None
    text = raw.strip()
    if text.startswith("```"):
        text = re.sub(r"^```(?:json)?\s*", "", text)
        text = re.sub(r"\s*```$", "", text)
    try:
        data = json.loads(text)
    except json.JSONDecodeError:
        # try to find outermost object
        m = re.search(r"\{.*\}", text, re.S)
        if not m:
            return None
        try:
            data = json.loads(m.group(0))
        except json.JSONDecodeError:
            return None
    if not isinstance(data, dict):
        return None
    summary = (data.get("summary") or "").strip()
    body = (data.get("body") or "").strip()
    if not body:
        return None
    if not body.lstrip().startswith("#"):
        body = "# Untitled\n\n" + body
    return {"summary": summary, "body": body}


def collect(job_names: list[str]) -> None:
    client = genai.Client(api_key=load_api_key())
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    bodies_dir = OUT_DIR / "by-draft"
    bodies_dir.mkdir(exist_ok=True)

    results = []
    errors = 0
    job_states = {}

    for job_name in job_names:
        job = client.batches.get(name=job_name)
        state = str(job.state).split(".")[-1]
        job_states[job_name] = state
        print("Job:", job_name, state)
        if "SUCCEEDED" not in state:
            continue
        responses = []
        if getattr(job, "dest", None) and getattr(job.dest, "inlined_responses", None):
            responses = job.dest.inlined_responses
        for ir in responses or []:
            meta = ir.metadata or {}
            did = int(meta.get("draft_id") or 0)
            raw = response_text(ir)
            err = str(ir.error) if ir.error else None
            parsed = parse_article_json(raw or "")
            if err or not parsed:
                errors += 1
                print("ERROR draft", did, err or "parse failed", (raw or "")[:120].replace("\n", " "))
                results.append(
                    {
                        "draft_id": did,
                        "path": meta.get("path"),
                        "title": meta.get("title"),
                        "error": err or "parse_failed",
                        "raw": (raw or "")[:2000],
                    }
                )
                continue
            rec = {
                "draft_id": did,
                "path": meta.get("path"),
                "title": meta.get("title"),
                "summary": parsed["summary"],
                "body": parsed["body"],
                "error": None,
            }
            results.append(rec)
            (bodies_dir / f"{did:03d}.json").write_text(
                json.dumps(rec, indent=2, ensure_ascii=False),
                encoding="utf-8",
            )
            (bodies_dir / f"{did:03d}.md").write_text(parsed["body"] + "\n", encoding="utf-8")

    (OUT_DIR / "results.json").write_text(json.dumps(results, indent=2, ensure_ascii=False), encoding="utf-8")
    ok = sum(1 for r in results if not r.get("error"))
    summary = {
        "jobs": job_states,
        "results": len(results),
        "ok": ok,
        "errors": errors,
        "out_dir": str(OUT_DIR),
    }
    (OUT_DIR / "summary.json").write_text(json.dumps(summary, indent=2), encoding="utf-8")
    print(json.dumps(summary, indent=2))


def apply_to_db() -> None:
    """Compose frontmatter + body and update article_drafts via PHP artisan tinker-style script."""
    bodies_dir = OUT_DIR / "by-draft"
    files = sorted(bodies_dir.glob("[0-9][0-9][0-9].json"))
    if not files:
        raise SystemExit(f"No body JSON files in {bodies_dir}")

    payload = []
    for path in files:
        rec = json.loads(path.read_text(encoding="utf-8"))
        if rec.get("error") or not rec.get("body"):
            continue
        payload.append(
            {
                "draft_id": rec["draft_id"],
                "summary": rec.get("summary") or "",
                "body": rec["body"],
            }
        )

    payload_path = OUT_DIR / "apply_payload.json"
    payload_path.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
    print(f"Applying {len(payload)} drafts...")

    apply_php = OUT_DIR / "apply_drafts.php"
    apply_php.write_text(
        """<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

use App\\Models\\ArticleDraft;
use App\\Models\\User;
use App\\Support\\ArticleDocument;

$payloadPath = $argv[1] ?? '';
if ($payloadPath === '' || !is_file($payloadPath)) {
    fwrite(STDERR, "payload missing\\n");
    exit(1);
}
$payload = json_decode(file_get_contents($payloadPath), true, 512, JSON_THROW_ON_ERROR);
$user = User::query()->whereRaw('LOWER(discord_username) = ?', ['viruxe'])->sole();
$updated = 0;
$skipped = 0;
$errors = [];

foreach ($payload as $item) {
    $draft = ArticleDraft::query()
        ->where('user_id', $user->id)
        ->whereKey($item['draft_id'])
        ->first();
    if (!$draft) {
        $skipped++;
        $errors[] = 'missing draft '.$item['draft_id'];
        continue;
    }
    $parsed = ArticleDocument::parse($draft->document);
    $fm = $parsed['fm'];
    $summary = trim((string) ($item['summary'] ?? ''));
    if ($summary !== '') {
        $fm['summary'] = mb_substr($summary, 0, 200);
    }
    if (!empty($fm['sources']) && is_array($fm['sources'])) {
        foreach ($fm['sources'] as &$src) {
            if (is_array($src)) {
                $src['adapted'] = true;
            }
        }
        unset($src);
    }
    $body = (string) $item['body'];
    $document = ArticleDocument::compose($fm, $body);
    $draft->document = $document;
    $draft->note = 'Full draft copy generated from source inventory + image OCR; verify technical claims against FSM before submission.';
    if (preg_match('/^#\\s+(.+)$/m', $body, $m)) {
        $draft->title = trim($m[1]);
    }
    $draft->save();
    $updated++;
    echo "updated #{$draft->id} {$draft->category}/{$draft->slug} body_len=".strlen($body)."\\n";
}
echo "DONE updated=$updated skipped=$skipped\\n";
if ($errors) {
    echo 'errors: '.implode('; ', $errors)."\\n";
}
""",
        encoding="utf-8",
    )
    result = subprocess.run(
        ["php", str(apply_php), str(payload_path)],
        cwd=str(ROOT),
        capture_output=True,
        text=True,
    )
    print(result.stdout)
    if result.returncode != 0:
        print(result.stderr)
        raise SystemExit(result.returncode)


def jobs_from_last_or_args(arg_list: list[str] | None) -> list[str]:
    if arg_list:
        return arg_list
    last = OUT_DIR / "last_job.json"
    if not last.is_file():
        raise SystemExit("No job names and last_job.json missing")
    meta = json.loads(last.read_text())
    if meta.get("job_names"):
        return list(meta["job_names"])
    if meta.get("name"):
        return [meta["name"]]
    raise SystemExit("last_job.json has no job names")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--submit", action="store_true")
    parser.add_argument("--submit-only", action="store_true")
    parser.add_argument("--poll", nargs="*", metavar="JOB")
    parser.add_argument("--collect", nargs="*", metavar="JOB")
    parser.add_argument("--apply", action="store_true", help="Write collected bodies into article_drafts")
    parser.add_argument("--model", default=DEFAULT_MODEL)
    parser.add_argument("--limit", type=int, default=None)
    parser.add_argument("--chunk-size", type=int, default=15)
    parser.add_argument("--poll-seconds", type=int, default=30)
    parser.add_argument("--display-name", default="hondabase-draft-articles")
    args = parser.parse_args()

    if args.submit:
        names = submit(args.model, args.limit, args.chunk_size, args.display_name)
        if not args.submit_only:
            poll(names, args.poll_seconds)
            collect(names)
        return
    if args.poll is not None:
        poll(jobs_from_last_or_args(args.poll), args.poll_seconds)
        return
    if args.collect is not None:
        collect(jobs_from_last_or_args(args.collect))
        return
    if args.apply:
        apply_to_db()
        return
    parser.error("Specify --submit, --poll, --collect, or --apply")


if __name__ == "__main__":
    main()
