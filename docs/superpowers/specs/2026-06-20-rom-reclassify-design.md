# Reclassify `rom` from category to tag-under-ecu

Date: 2026-06-20

## Problem

`rom` appears in the explorer's "Categories" filter group. It is not a real
content category: it is an attribute of specific ECU articles that reference
chip ROMs (flash/EPROM parts, chipping, ROM editors). It currently exists as a
category only because `Recategorizer::SUBJECT_RULES` tests the `rom`/`chipping`/
`maps` tags *before* `tuning` and `ecu` (first match wins), so 108 English
articles (222 across en+pt) bin into `cars/rom`.

This is not a code defect: it is the deliberate output of the recategorizer's
rules. Fixing it is a content-model change, not a facet bug. The previous commit
(`42a3b8b`) already made path segments that match taxonomy nodes use the node's
own kind; segments with no node (subject folders like `rom`, `sensors`) keep
`kind = 'category'` by design. `rom` has no taxonomy node, so it stays a
category until its articles are re-filed.

## Goal

- `rom` no longer produces a `category` facet.
- `rom` becomes a **tag**, concentrated on the curated chip-ROM articles.
- Curated chip-ROM articles live under `cars/ecu`.
- The remainder of the `cars/rom` corpus redistributes to its real subjects
  (mostly `tuning`), losing the `rom` tag.

## Classification

For each `cars/rom/<slug>` article (planned on the English identity; the pt
mirror moves alongside):

A. **chip-ROM** — has a `rom` tag **and** a chip signal:
   - a tag in `{memory, eprom, flash}`, **or**
   - slug matches
     `eprom|flash|chip|bin|checksum|sram|8051|27c|28c|74hc|66k|82c55|mcu|internal-rom|otp|uv-erase|hex2-bin|latch`.

   → subject `ecu`; **keep** the `rom` tag. (~14 articles.)

B. **everything else** → subject from `SUBJECT_RULES` with the `rom` rule
   removed (mostly `tuning`; single-chassis articles still file into the
   generation tree per the existing `generationFor` logic); **strip** the `rom`
   tag. (~94 articles.)

Measured dry-run distribution with the `rom` rule removed (108 en articles):
`tuning 72, ecu 10 (+chip-ROM overrides), diagnostics 7, generation-tree 8,
fueling 4, sensors 3, reference 2, wiring 2`. Curation: of the 75 currently
`rom`-tagged, **keep 14, strip 61**. The 14 keeps:
`27sf256, 66k-resources, 74hc373, bin-file, chipping-with-pictures, eprom,
flash, hex2-bin, intel8051, internal-rom, mcu, otp, rom-emulator, uv-erase`.

The keep-list is a heuristic; the dry-run prints it for review before execute.

## Components

### 1. `Recategorizer::SUBJECT_RULES`

Remove the `'rom' => ['rom', 'chipping', 'maps']` entry permanently so a future
`hondabase:recategorize` run never re-creates the subject. Live indexing derives
categories from real content paths via `PathParser`, not from these rules, so
once the content moves the category is gone for good.

### 2. New command `hondabase:reclassify-rom`

Dry-run by default (mirrors `hondabase:recategorize`). Scoped strictly to the
`cars/rom` corpus so no other article is touched.

- **plan**: scan `cars/rom` English articles, classify each (chip-ROM → `ecu`
  keep tag; else subject-without-rom strip tag), build the move list and the
  tag-strip list, print the target distribution + the chip-ROM keep-list +
  strip count, and write the plan JSON to `storage/app/reclassify-rom-plan.json`.
- **`--execute`**: `git mv` the en+pt bundles and rewrite absolute
  `/cars/rom/<slug>` body links (reuse `Recategorizer::execute` /
  `rewriteLinks`), strip the `rom` tag from the non-chip frontmatter in both
  trees, then `ArticleIndexer::indexAll()`. Confirm prompt before mutating.

### 3. Frontmatter tag-strip helper

A new helper that removes a single tag from a markdown file's `tags:` list while
preserving every other frontmatter field and the body. Applied to both en and
pt copies. Idempotent (removing an absent tag is a no-op). This is new: the
recategorizer today only `git mv`s and rewrites body links.

## Data flow

```
hondabase:reclassify-rom (dry run)
  scan cars/rom (en) -> classify -> { moves[], stripSlugs[] } -> plan.json + report
hondabase:reclassify-rom --execute
  git mv en+pt bundles + rewrite /cars/rom/<slug> body links
  strip `rom` tag from non-chip frontmatter (en+pt)
  ArticleIndexer::indexAll()
```

## Testing (TDD)

Unit-test the pure pieces before wiring the command:

- chip-ROM detection: tag-signal and slug-signal cases, and the negative
  (rom-tagged generic tuning article is not chip-ROM).
- subject routing: chip-ROM → `ecu`; a `tuning`-tagged non-chip → `tuning`;
  confirm the `rom` rule no longer routes anything.
- frontmatter tag-strip helper: removes `rom`, preserves other tags and fields,
  idempotent, leaves the body untouched.

## Verification after `--execute`

- `ArticleFacet` has zero `category` rows whose value is `rom` or starts with
  `rom/`.
- `kind='tag', value='rom'` facet count is approximately 14, all on
  `cars/ecu/*` articles.
- `content/cars/rom/` no longer exists.
- Spot-check: `27sf256` is under `cars/ecu` and keeps its `rom` tag; a generic
  tuning article (e.g. `boost`) is under `cars/tuning` with `rom` removed.

## Risks / notes

- Destructive on the `content/` git repo (`git mv` + frontmatter edits). Dry-run
  and review the plan first; commit the content repo separately from the app.
- The pt mirror receives the same moves and the same tag strips.
- Old `/cars/rom/...` URLs 404 (no redirects — consistent with the existing
  recategorizer's owner decision). Body links are rewritten to the new homes.
