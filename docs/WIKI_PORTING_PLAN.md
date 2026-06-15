# Hondatabase - Wiki Porting Assessment & Progress Plan

This plan assessed all **513 topics** in the `library` web of the `pgmfi` wiki archive database. It categorized them, identified valuable articles vs stubs, and established the path to complete the porting process.

> **STATUS: PORTING COMPLETE (2026-06-15).** The content port is done. The per-category backlog
> tables in sections 2-3 below are a **historical record** of the original assessment (frozen at
> "Batch 13"); they were never updated through the final bulk-port commits and **do not reflect
> reality**. See the live counts here and in `docs/PROGRESS.md`. The one outstanding content task is
> **re-categorization** (see section 5), not more porting.

## 1. Overall Progress Summary (current, 2026-06-15)

| Status | Count | Description |
| :--- | :---: | :--- |
| **Ported to articles** | **496** | English article bundles on disk under `content/cars/` (`hondabase:reindex` → 496 `en` index rows) |
| ↳ credited to the pgmfi wiki | 493 | articles carrying a `sources: pgmfi.org wiki` credit (the rest are freshly authored: wideband guide, injector-offsets DB, carousel docs) |
| **Translated (pt-PT)** | **496** | Portuguese mirror bundles under `content/pt/cars/` (496 `pt` index rows; en==pt after the duplicate-slug cleanup) |
| **Skipped** | ~20 | of the 513 library topics: empty stubs, personal placeholders, and duplicates consolidated during porting (e.g. the OBD0 conversion-formula pages) |

The closing commits in the **content** repo (`hondabase/articles`) were `7e2f6af "Port all remaining
valuable PGMFI wiki articles to Hondabase"` and `2a4a9db "Port short technical stub articles and
formulas"`, which superseded the batch-by-batch ledger below.

## 2. Category Breakdown (HISTORICAL - frozen at Batch 13, does not reflect the completed port)

## 2. Category Breakdown

| Category | Total | Completed | Pending | Stubs |
| :--- | :---: | :---: | :---: | :---: |
| Unclassified/Other | 252 | 3 | **199** | 50 |
| Electronics/ECU hardware & chipping | 88 | 16 | **59** | 13 |
| Electronics/Sensors & solenoids | 71 | 18 | **43** | 10 |
| Tuning & ROM editing | 43 | 7 | **30** | 6 |
| Electronics/Wiring & conversion | 17 | 2 | **14** | 1 |
| General Info & History | 15 | 0 | **10** | 5 |
| Engine & Drivetrain mechanical | 11 | 0 | **9** | 2 |
| Diagnostics & troubleshooting | 11 | 2 | **8** | 1 |
| Fueling & Injectors | 5 | 1 | **4** | 0 |

## 3. Prioritized Pending Articles by Category

Below are the top pending articles in each category, sorted by length (approximate value/content depth). This serves as our prioritized backlog.

### Unclassified/Other (Top 15 prioritized)

| Title | Slug | Length (chars) | Priority / Note |
| :--- | :--- | :---: | :--- |
| Easy Rtp V10 | `easy-rtp-v10` | 7513 | **High** (Rich content) |
| Sim | `sim` | 7008 | **High** (Rich content) |
| Pgsrc Translation | `pgsrc-translation` | 6960 | **High** (Rich content) |
| Oki6260A | `oki6260a` | 6036 | **High** (Rich content) |
| Release Notes | `release-notes` | 5466 | **High** (Rich content) |
| Text Formatting Rules | `text-formatting-rules` | 4598 | **High** (Rich content) |
| Willem | `willem` | 3806 | **High** (Rich content) |
| UPD7004C | `upd7004c` | 3451 | **High** (Rich content) |
| P0A | `p0a` | 3373 | **High** (Rich content) |
| Easy Rtp Install | `easy-rtp-install` | 3369 | **High** (Rich content) |
| DLC | `dlc` | 2956 | **High** (Rich content) |
| 82C55 | `82c55` | 2880 | **High** (Rich content) |
| 66k Assembler Docs | `66k-assembler-docs` | 2749 | **High** (Rich content) |
| Data Logging | `data-logging` | 2736 | **High** (Rich content) |
| New Markup Test Page | `new-markup-test-page` | 2581 | **High** (Rich content) |

### Electronics/ECU hardware & chipping (Top 15 prioritized)

| Title | Slug | Length (chars) | Priority / Note |
| :--- | :--- | :---: | :--- |
| Chipping An88-89ECU | `chipping-an88-89ecu` | 6471 | **High** (Rich content) |
| Doc ECU School | `doc-ecu-school` | 5503 | **High** (Rich content) |
| Japanese Domestic Market P30D12 Modification | `japanese-domestic-market-p30d12-modification` | 3808 | **High** (Rich content) |
| OBD0 Inter Chip Communication | `obd0-inter-chip-communication` | 2924 | **High** (Rich content) |
| OBD0PM6PM7RAM Locations | `obd0pm6pm7ram-locations` | 2478 | Medium |
| Chipping OBD2 | `chipping-obd2` | 2449 | Medium |
| Add IAB To P28 | `add-iab-to-p28` | 2079 | Medium |
| Acura Integra PR4ECU Pinout And Schematics | `acura-integra-pr4ecu-pinout-and-schematics` | 1897 | Medium |
| OBD0ECUAUTOTOMANUALWITHOUTREMOVEANYHARDWARE | `obd0ecuautotomanualwithoutremoveanyhardware` | 1747 | Medium |
| Knock Board | `knock-board` | 1745 | Medium |
| P72 Debug Mode | `p72-debug-mode` | 1702 | Medium |
| ECU Hardware Mods | `ecu-hardware-mods` | 1492 | Medium |
| P14 | `p14` | 1489 | Medium |
| Chipping OBD0PM6 | `chipping-obd0pm6` | 1450 | Medium |
| Hi Res P72 | `hi-res-p72` | 1376 | Medium |

### Electronics/Sensors & solenoids (Top 15 prioritized)

| Title | Slug | Length (chars) | Priority / Note |
| :--- | :--- | :---: | :--- |
| Inter Wiki Map | `inter-wiki-map` | 3554 | **High** (Rich content) |
| How To Log External Data Such As An Egt Sensor | `how-to-log-external-data-such-as-an-egt-sensor` | 3319 | **High** (Rich content) |
| Turbo Compressor Map | `turbo-compressor-map` | 2833 | **High** (Rich content) |
| Rom Maps | `rom-maps` | 2810 | **High** (Rich content) |
| Disable Vtec VSS Check P30 203 | `disable-vtec-vss-check-p30-203` | 1975 | Medium |
| Engine Coolant Temperature | `engine-coolant-temperature` | 1957 | Medium |
| Electronic Part Supplier | `electronic-part-supplier` | 1775 | Medium |
| RTP Project | `rtp-project` | 1701 | Medium |
| O2 Input Mod | `o2-input-mod` | 1050 | Medium |
| OBD1 Civic Integra EC Us | `obd1-civic-integra-ec-us` | 1017 | Medium |
| Add Knock To P30G00 | `add-knock-to-p30g00` | 981 | Medium |
| Remove A Knock Sensor | `remove-a-knock-sensor` | 949 | Medium |
| Adjust PR4 Pa Sensor | `adjust-pr4-pa-sensor` | 948 | Medium |
| Disable Vtec VSS Check P28 | `disable-vtec-vss-check-p28` | 879 | Medium |
| Map Sensor Equation | `map-sensor-equation` | 849 | Medium |

### Tuning & ROM editing (Top 15 prioritized)

| Title | Slug | Length (chars) | Priority / Note |
| :--- | :--- | :---: | :--- |
| Crome Script | `crome-script` | 18510 | **High** (Rich content) |
| Proposed Datalogging Protocol | `proposed-datalogging-protocol` | 4149 | **High** (Rich content) |
| Howto Add Extra Features In Crome | `howto-add-extra-features-in-crome` | 3130 | **High** (Rich content) |
| Nokia Cable Datalogging | `nokia-cable-datalogging` | 2533 | **High** (Rich content) |
| Common TE Problems | `common-te-problems` | 2333 | Medium |
| Usb To Serial Converter Second Gen | `usb-to-serial-converter-second-gen` | 1850 | Medium |
| Rtp Truth Table | `rtp-truth-table` | 1514 | Medium |
| Full Duplex Datalogging | `full-duplex-datalogging` | 1415 | Medium |
| Pre Ignition | `pre-ignition` | 1166 | Medium |
| DLC Communication | `dlc-communication` | 1076 | Medium |
| Dual Roms | `dual-roms` | 1031 | Medium |
| OBD0 Stock Datalogging | `obd0-stock-datalogging` | 691 | Medium |
| Add Boost | `add-boost` | 688 | Medium |
| Crome ROM Editor | `crome-rom-editor` | 561 | Medium |
| Rom Emulator | `rom-emulator` | 553 | Medium |

### Electronics/Wiring & conversion (Top 14 prioritized)

| Title | Slug | Length (chars) | Priority / Note |
| :--- | :--- | :---: | :--- |
| Accord Auto OBD2-OBD1 | `accord-auto-obd2-obd1` | 13050 | **High** (Rich content) |
| Wiki Plugin | `wiki-plugin` | 2552 | **High** (Rich content) |
| Calendar Plugin | `calendar-plugin` | 1794 | Medium |
| OBD1P08 Auto Manual | `obd1p08-auto-manual` | 888 | Medium |
| Wire Harness | `wire-harness` | 582 | Medium |
| OBD0 Conversion Formula | `obd0-conversion-formula` | 511 | Medium |
| OBD0 Conversion Formulas | `obd0-conversion-formulas` | 511 | Medium |
| OBD0 Conversion Formulae | `obd0-conversion-formulae` | 510 | Medium |
| OBD1P13 Auto Manual | `obd1p13-auto-manual` | 493 | Medium |
| OBD1 Conversion Formulae | `obd1-conversion-formulae` | 438 | Medium |
| PS9 Auto Manual | `ps9-auto-manual` | 356 | Medium |
| OBD2P5M Auto Manual | `obd2p5m-auto-manual` | 214 | Low (Short reference) |
| Conversion | `conversion` | 211 | Low (Short reference) |
| Spark Plug | `spark-plug` | 149 | Low (Short reference) |

### General Info & History (Top 10 prioritized)

| Title | Slug | Length (chars) | Priority / Note |
| :--- | :--- | :---: | :--- |
| Uber Data FAQ | `uber-data-faq` | 6847 | **High** (Rich content) |
| Begginers FAQ | `begginers-faq` | 4405 | **High** (Rich content) |
| Php Wiki Administration | `php-wiki-administration` | 3032 | **High** (Rich content) |
| Wiki Word | `wiki-word` | 2126 | Medium |
| How To Use Wiki | `how-to-use-wiki` | 1987 | Medium |
| Magic Php Wiki UR Ls | `magic-php-wiki-ur-ls` | 1699 | Medium |
| Inter Wiki | `inter-wiki` | 1647 | Medium |
| Wiki Wiki Web | `wiki-wiki-web` | 847 | Medium |
| Php Wiki | `php-wiki` | 255 | Low (Short reference) |
| Php Wiki Documentation | `php-wiki-documentation` | 121 | Low (Short reference) |

### Engine & Drivetrain mechanical (Top 9 prioritized)

| Title | Slug | Length (chars) | Priority / Note |
| :--- | :--- | :---: | :--- |
| OBD1 8bit Low Cam RPM | `obd1-8bit-low-cam-rpm` | 1567 | Medium |
| Engine Sim | `engine-sim` | 919 | Medium |
| OBD1 8bit High Cam RPM | `obd1-8bit-high-cam-rpm` | 917 | Medium |
| Low Cam | `low-cam` | 352 | Medium |
| High Cam | `high-cam` | 348 | Medium |
| Dual Runner Manifold | `dual-runner-manifold` | 307 | Medium |
| Prelude Accord | `prelude-accord` | 305 | Medium |
| Turbo | `turbo` | 294 | Low (Short reference) |
| Cam Profile | `cam-profile` | 151 | Low (Short reference) |

### Diagnostics & troubleshooting (Top 8 prioritized)

| Title | Slug | Length (chars) | Priority / Note |
| :--- | :--- | :---: | :--- |
| OBD1 Code Compatibility | `obd1-code-compatibility` | 1675 | Medium |
| CEL | `cel` | 1612 | Medium |
| OBD0 Code Compatibility | `obd0-code-compatibility` | 1385 | Medium |
| 91PM6 Target Idle | `91pm6-target-idle` | 1316 | Medium |
| Label Decode | `label-decode` | 778 | Medium |
| Limp Mode | `limp-mode` | 489 | Medium |
| 91PM7 Target Idle | `91pm7-target-idle` | 367 | Medium |
| Idle Air Control Valve | `idle-air-control-valve` | 190 | Low (Short reference) |

### Fueling & Injectors (Top 4 prioritized)

| Title | Slug | Length (chars) | Priority / Note |
| :--- | :--- | :---: | :--- |
| OBD1 8bit Fuel | `obd1-8bit-fuel` | 648 | Medium |
| Fuel Cut | `fuel-cut` | 410 | Medium |
| Fuel Octane | `fuel-octane` | 292 | Low (Short reference) |
| OBD0 Fuel | `obd0-fuel` | 111 | Low (Short reference) |

## 4. Porting Roadmap & Next Steps

1. **Phase 1: High-Priority Electronics & Tuning (Next Batch)**: Port and reformat high-content articles (length > 2500 chars) such as `ecu-definition-codes`, `ecu-chipping-wirelist`, `kurts-obd0-obd1`, and `obd0ecuautotomanualwithoutremoveanyhardware`.
2. **Phase 2: Wiring & Conversions**: Focus on the harness and swaps category to help enthusiasts with swaps.
3. **Phase 3: Diagnostics & Troubleshooting**: Standardize error code guides and symptom-based checklists.
4. **Phase 4: Mechanical & Drivetrain**: Port mechanical guides like RT4WD details, cams, and mechanical engine basics.
5. **Phase 5: Cleaning Stubs**: Review the 88 stubs and decide whether to delete them or write thin helper summaries.

---


## 5. Re-categorization + nested taxonomy (FINAL plan, approved 2026-06-15)

**Goal.** Split the flat `cars/electronics/` bucket (495 of 496 articles) into a real, **arbitrary-depth
nested** taxonomy, so the knowledgebase browses as a structured tree instead of one giant category.

**Decisions (owner, 2026-06-15):**
- **Arbitrary-depth nesting.** `category` becomes a path (`electronics/ecu/chipping`), any depth.
- **Prune off-topic pages** (non-Honda wiki cruft like `wabi-sabi`); the migration's dry-run prints the
  full delete list for owner approval before anything is removed.
- **No redirects.** Old `/cars/electronics/{slug}` URLs will 404 after the move (accepted, despite the
  site being indexable - the main site is crawlable; only `/pgmfi/wiki/` is `Disallow`ed and only
  editor/admin pages carry `noindex`).
- **Internal absolute links rewritten** during the move; **`en` + `pt` trees move in lockstep**.
- **Facets:** one facet per **ancestor path segment** (drill-down `electronics` -> `electronics/ecu`).

### Model change
`category` goes from a single slug (`/^[A-Za-z0-9._-]+$/`, no slashes, fixed bundle depth
`content/{type}/{category}/{slug}/`) to a **path**. A *bundle* is any directory whose name matches its
`.md` (slug == folder); its `category` is the path between `{type}` and the bundle. The
`articles.category` column already is a string - it just holds `electronics/ecu` now. The unique key
`type+category+slug+locale` is unchanged.

### Touchpoints
1. **Routing (the hard part - ambiguity).** `/cars/electronics/ecu` is ambiguous (article `ecu` in
   `electronics`, vs a listing of `electronics/ecu`). Resolve against the **index**, not regex: a
   catch-all `/{type}/{path}` (+ `/{locale}/{type}/{path}`) -> one resolver: article if
   `type + path == category/slug`; else category listing if `path` prefixes any article's category;
   else 404. Assets (final segment has a `.ext`) resolve in the same controller.
2. **`ArticleService`:** replace the two-level `glob` walk with a **recursive bundle scan**
   (`scanAll`/`scanOne`/`categories`/`articlesIn`/`categoryExists`/`find`/`rawMarkdown`/`assetPath`);
   `safe()` validates the category **per segment** (split on `/`).
3. **Facets / Explorer:** facet on each ancestor segment; category-scoped search still works.
4. **Breadcrumbs + category pages + `BreadcrumbList` JSON-LD:** expand the single category crumb into
   one crumb per path segment (article, category, editor views).
5. **Editor / creator / translate routes** (`/edit/{type}/{path}`, `/{locale}/edit/{type}/{path}`):
   category becomes a path; `ArticleCreator` category input accepts a path.
6. **Sitemap, `ArticleRevision::url()`, history:** unchanged once `category` carries the path.

### Migration command - `hondabase:recategorize`
One-off, `--dry-run` first: load the priority-ordered **tag->category-path map**; compute the move
plan for every `en` bundle + mirror onto `pt`; print the ~7 no-tag fallbacks and the **prune list** for
approval; on confirm `git mv` each bundle in both trees (assets ride along), rewrite absolute
`/cars/electronics/...` body links, delete pruned bundles; then `hondabase:reindex`. Idempotent +
reversible (all `git mv`).

### Sequencing
- **Phase A - engine:** nested-category support in routing + service + facets/breadcrumbs, content
  **still flat** (`electronics` is just a one-segment path, nothing visibly changes). Ship + tests green.
- **Phase B - content move:** build + dry-run `hondabase:recategorize`, owner approves plan + prune
  list, execute on a branch in `hondabase/articles`, reindex.

### Tests
Nested routing (article vs listing, deep paths, ambiguous paths, assets), recursive scan/reindex
counts, per-segment facets, breadcrumb expansion, the command's dry-run plan + link rewrite + both-tree
move, and that flat articles still resolve during Phase A.
