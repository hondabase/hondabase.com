# Scraped Article Draft Inventory

This document records the private article-scaffold import assembled from Icelord and
Nthefastlane on 2026-08-08. The executable manifest is
`scripts/import-scraped-draft-scaffolds.php`.

## Result

- **87 private drafts** belong to `VIRUXE (@viruxe)` (`users.id = 2`, draft IDs 2–88).
- **54 drafts** came from Nthefastlane pages.
- **33 drafts** came from Icelord static pages and WordPress post series.
- The Icelord drafts retain **79 source entries** because related multi-part posts are merged into
  coherent articles without losing the individual source URLs.
- No article revision, Markdown article, git commit, or derived article-index row was created.

Drafts are distributed across the existing flat article categories:

| Category | Drafts |
| :--- | ---: |
| Reference | 42 |
| Wiring | 12 |
| Transmissions | 10 |
| Tuning | 6 |
| Diagnostics | 5 |
| Ignition | 4 |
| Fueling | 3 |
| Sensors | 3 |
| ECU | 2 |

## Source Inventory and Selection

The Nthefastlane sitemap exposed 518 page URLs. Seventy-two Honda-keyword candidates were
fetched and reviewed in detail. Storefront pages, a GY6 scooter page, and pages already represented
by Hondabase were excluded; the remaining 54 technical pages each became a draft.

Icelord's current WordPress Honda search exposed 269 posts over 27 result pages. The legacy
`/honda/` area added 32 fetched pages and indexes. Reposts, photo-only updates, personal diary
entries, downloads, and unrelated material were excluded. Closely related numbered posts—such as
the coil-on-plug controller, climate-control retrofit, balance-shaft delete, custom gauges, fuel
tank replacement, and exhaust work—were merged into one article scaffold per technical subject.
Every retained part remains in that draft's `sources` frontmatter.

Internet Archive URL discovery helped locate old Icelord paths, but an archive URL without fetched
article content was not counted as gathered content and did not create a draft.

## Duplicate Policy

The import checks exact published paths, normalized published titles, and any existing draft path.
It is additive and never updates an existing draft, so rerunning it cannot overwrite a contributor's
work. A second dry run after import produced zero new drafts and skipped all 87 existing paths.

Fifteen semantic source groups were also excluded manually because the existing corpus already
covers them. These include the five earlier Nthefastlane imports, OBD pinouts and trouble codes,
ECU identification/chipping, TPS calibration, CKP/VTEC diagnosis, electronic boost control, and
Icelord's P14/dual-ROM, RTP, and knock-board material. The manifest maps every exclusion group to
the existing coverage.

## Scaffold Standard

Every draft contains:

- the required `summary`, `tags`, `applies_to`, and `complexity` frontmatter;
- source cards with the original URL and an explicit no-reuse-license assumption;
- one descriptive H1;
- a reference, diagnostic, repair, or modification-specific outline;
- applicability, parts/tools, verification, safety, and post-work sections; and
- explicit reminders to validate technical claims against factory service information before
  submission.

The scaffolds do not copy source prose. They provide an original structure and preserve provenance
for a contributor to research, verify, and complete through the normal approval-gated workflow.

## Private Source-Image Archive

The gathered source images are preserved privately at
`storage/app/private/source-image-archive/`. They are not draft uploads, are not present in the
public articles repository, and are not cleared for publication. No watermark, recompression, or
other image modification was applied. The latest manifest records them as
`unresolved-all-rights-reserved` with `publication_cleared: false`.

The completed archive contains:

- all **132 source-page HTML snapshots**, with no page-fetch failures;
- **580 normalized image URLs**, including source-provided resolution variants;
- **470 preserved URL variants** representing **459 distinct image objects**;
- **502 human-readable hard links**, grouped into 97 article/source-page indexes and two
  site-wide indexes; and
- SHA-256, MIME type, dimensions, exact source URL, original filename, caption, title, alt text,
  draft path, and source-page provenance in the manifests and per-folder `index.json` files.

No article-content image family is unavailable. Icelord advertises 102 `-1920` URLs that currently
return 404; their available alternate-resolution variants are preserved and the failed URLs remain
recorded. Eight Icelord WordPress theme/header/favicon URLs return 403 from both current hostnames.
They are recorded as unavailable site-wide assets and are not article content. All 114 normalized
Nthefastlane image references were preserved; they represent 105 distinct objects.

The archive has two complementary layouts:

```text
objects/sha256/<prefix>/<sha256>.<ext>
by-article/<draft-path>/<site>/<source-page>/images/<sequence>-<context>.<ext>
by-site/<site>/site-wide/images/<sequence>-<context>.<ext>
```

The SHA-addressed object is authoritative. Human-readable paths are generated hard links and are
rebuilt atomically on each run. A name comes from source caption, title, alt text, or a descriptive
original filename. If the source supplies only a numeric name, camera filename, hash, or opaque
Wix ID, the archiver combines the article slug with that unchanged identifier; it does not invent
visual meaning. Site-wide assets are kept out of every article folder.

The archiver is Python because Beautiful Soup/lxml, Requests, and Pillow cover malformed legacy
HTML, resumable downloads, and image validation more directly than the application's Node build
toolchain. Install its isolated dependencies and run it with:

```bash
python3 -m venv /tmp/hondabase-source-image-venv
/tmp/hondabase-source-image-venv/bin/pip install -r scripts/requirements-source-image-archive.txt
/tmp/hondabase-source-image-venv/bin/python scripts/archive_scraped_source_images.py --apply
```

Successful objects, page snapshots, and known failures are reused. Pass `--refresh-pages` to
refetch source HTML or `--retry-unavailable` to retry URLs recorded as unavailable. The PHP helper
`scripts/export-scraped-draft-sources.php` supplies the exact Viruxe draft/source mapping, so the
archive follows the current private drafts without duplicating a second manifest of article paths.

## Reproduction

Dry run:

```bash
php scripts/import-scraped-draft-scaffolds.php
```

Create only missing drafts for Viruxe:

```bash
php scripts/import-scraped-draft-scaffolds.php --apply
```
