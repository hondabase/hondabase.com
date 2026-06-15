# Taxonomy Epic - Implementation Progress

Living tracker for the **Hybrid Storage & Product-Aware Taxonomy** epic (generation-aware routing,
subject-centric vs product-centric storage, `fits:` compatibility). Plan of record:
`/root/.claude/plans/actually-i-just-found-shimmying-sparrow.md`. Rolls up into `docs/PROGRESS.md`
when the epic closes.

**Scope reminder:** all Honda/Acura **products**, not just vehicles (cars, motorcycles, ATV, marine,
power equipment, aircraft). Taxonomy node kinds are data-driven; the user-owned model is
`user_products`, not `user_vehicles`.

**Foundations already in place (Phase A, pre-epic):** arbitrary-depth nested categories - catch-all
`/{type}/{path}` resolver (`ArticleController::resolve`), `ArticleService::splitPath` + recursive
`findBundles`/`isBundle` scanner, per-segment `facetsFor` + breadcrumbs. `articles.category` stores
the full path. This epic adds the semantic layer over those paths.

## Status by phase

- [x] **P1 - Taxonomy & subjects foundation** *(done 2026-06-15; storage pivoted to DB-canonical)*
  - [x] `database/data/taxonomy.json` + `subjects.json` SEED (data-driven node kinds; real Honda/Acura
        cars branch w/ chassis+years; stubbed motorcycles/aircraft). NOTE: moved out of `content/_data`
        - it's a one-time bootstrap, not the live source.
  - [x] `taxonomy_nodes` + `subjects` migrations + `TaxonomyNode`/`Subject` models (durable, editable)
  - [x] `TaxonomySync::import()` + `hondabase:taxonomy:seed` (empty-guard + `--force`); `reindex` no
        longer seeds/wipes taxonomy (36 nodes / 17 subjects persist across reindex)
  - [x] `App\Services\PathParser` (greedy node-prefix match -> nodePath + subject); `TaxonomyTest` (4)
- [x] **P2 - Compatibility & fits** *(done 2026-06-15)*
  - [x] `compatibilities` pivot migration + `Compatibility` model (cascades with the article row)
  - [x] `CompatibilityResolver`: inherited (folder path via PathParser), explicit (`fits:` paths),
        `applies_to` chassis/models/trims -> node bridge; wired into `ArticleIndexer::persist`
        (default-locale identity only, gated on locale not frontmatter)
  - [x] node-derived make/model/generation facets merged into `article_facets` (de-duped on
        kind|value); `CompatibilityTest` (4). Real corpus yields 0 links today (no chassis/model/
        `fits` data + all under `electronics`) - correct; lights up in P4 + as authors add `fits:`
- [~] **P3 - Routing, node pages & breadcrumbs** *(read side done 2026-06-15; control panel = P3b)*
  - [x] taxonomy-aware `resolve()` node branch + `node.blade` (node metadata + child nodes +
        compatible-article cards; static listing rather than the full Explorer - simpler, can swap later)
  - [x] `BreadcrumbBuilder` -> generation/model + subject-named breadcrumbs on article/category/node
        + BreadcrumbList JSON-LD; `NodePageTest` (5); HTTP-verified node pages 200 + bad child 404
  - [x] **P3b - taxonomy control panel** *(done 2026-06-15)* `/admin/taxonomy` (`manage-articles`,
        English): `TaxonomyManager` Livewire CRUDs the DB-canonical taxonomy directly - add/edit
        nodes (path auto-computed), rename (cascades descendant paths), delete subtree, subject CRUD,
        "Rebuild article links" (reindex). Slug rename/delete blocked when articles are filed under
        the node (folder-coupling guard). Nav link added. `TaxonomyManagerTest` (7).
- [~] **P4 - Content migration** *(tooling done 2026-06-15; execution AWAITS owner approval)*
  - [x] `Recategorizer` + `hondabase:recategorize` (dry-run default; `--execute`, `--prune=`).
        `RecategorizeTest` (3). Dry-run on live corpus: **0 generation-specific** (no chassis
        metadata), all 496 re-file from `electronics` into subjects -> sensors 137, rom 119,
        wiring 79, diagnostics 76, ecu 36, reference 23, fueling 11, tuning 11, ignition 4.
        2 no-tag review candidates (Honda-Acura-Trouble-Codes, how-to-check-obd1-ecu-codes).
  - [ ] **owner: approve the subject mapping/ordering + name any prune slugs**, then `--execute`
        (git mv en+pt, rewrite absolute links, reindex). Not run yet.
- [ ] **P5 - Product-centric personalization**
  - [ ] rename `user_vehicles`->`user_products`, `UserVehicle`->`UserProduct`, Garage labels; data preserved
  - [ ] optional `taxonomy_node_id` FK; "fits my products" filtering + node follows; localized node names

## Decisions (locked)
- Adopt structured taxonomy + compatibility, but **derive facets from it** so the existing
  explorer/follows/garage stay load-bearing (not a parallel browse system).
- Keep `{type}/{path}` routing; PathParser decides node-vs-subject split within a type.
- File generation-specific articles under generations; multi-fit stays subject-centric with `fits:`.
- **No redirects** for moved URLs (owner decision; old URLs 404).
- **Privacy posture (owner, 2026-06-15):** everything is public. No email/password collected (Discord
  OAuth only; those `users` columns dropped). The DB dump commits to the **public** site source;
  only credentials (`remember_token`, `push_subscriptions` keys) + transient tables are excluded
  (security, not privacy). Old "no PII / private backups repo" rule is dropped.
- **Taxonomy storage: DB-canonical (owner, 2026-06-15).** The `taxonomy_nodes`/`subjects` TABLES are
  the live source of truth (edited via the control panel; natural add/remove/rename for an evolving,
  not-fully-known dataset). A JSON seed (`database/data/*.json`) bootstraps them once via
  `hondabase:taxonomy:seed`; `reindex` never touches them; forkability rides on the public DB dump.
  (Reversed the earlier file-canonical plan once the dump became public.) Caveat: a node `slug` is
  also a content folder name, so renaming/removing a node with inherited articles implies moving
  those folders - the control panel ties rename to a content move, or restricts it to empty nodes.

## Changelog
- **2026-06-15** - **P3b done: taxonomy control panel.** `/admin/taxonomy` (`TaxonomyManager`,
  manage-articles, English) for direct CRUD on the DB-canonical taxonomy + subjects, with path
  auto-compute, descendant repath on rename, subtree delete, and a folder-coupling guard
  (rename/delete blocked when articles are filed under the node) + "Rebuild article links".
  `TaxonomyManagerTest` (7). 54 tests pass. (Also cleared a stale route cache that was hiding new routes.)
- **2026-06-15** - **Taxonomy pivoted to DB-canonical.** The `taxonomy_nodes`/`subjects` tables are now
  the live, control-panel-editable source of truth (better for an evolving dataset); JSON moved from
  `content/_data` to a `database/data` seed loaded once by `hondabase:taxonomy:seed`. `reindex` no
  longer seeds/wipes taxonomy. Viable now that the DB dump is public (forkability rides on it). Tests
  updated to `import()` the fixture before reindex. 47 tests pass.
- **2026-06-15** - **P3 read side done.** Node landing pages (`/cars/honda/civic/eg`) via a
  taxonomy-aware `resolve()` branch + `node.blade` (metadata, child nodes, compatible-article cards);
  `BreadcrumbBuilder` gives generation/model/subject-named breadcrumbs across article/category/node
  with BreadcrumbList JSON-LD. `NodePageTest` (5); 47 tests pass; HTTP-verified. Remaining: P3b
  taxonomy control panel.
- **2026-06-15** - **P2 done.** `compatibilities` pivot + `Compatibility` model; `CompatibilityResolver`
  (inherited folder path / explicit `fits:` / `applies_to` bridge) wired into the indexer with
  node-derived make/model/generation facets folded into `article_facets`. `CompatibilityTest` (4).
  42 tests pass. Current corpus links = 0 (no generation-specific data yet), which is expected.
- **2026-06-15** - **P1 done.** taxonomy.json + subjects.json (all-products, data-driven kinds),
  `taxonomy_nodes`/`subjects` tables + models, `TaxonomySync` wired into `hondabase:reindex`
  (36 nodes/17 subjects), `PathParser`, `TaxonomyTest` (4). Also: dropped unused `users.email`/
  `email_verified_at`/`password` columns (data minimization) + simplified `UserFactory`. 38 tests pass.
- **2026-06-15** - Epic approved (auto mode). Plan finalized; Phase A foundations confirmed in code;
  this tracker created. Starting P1.
