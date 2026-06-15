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
- [x] **P4 - Content migration** *(done 2026-06-15; executed, no prunes)*
  - [x] `Recategorizer` + `hondabase:recategorize` (dry-run default; `--execute`, `--prune=`).
        `RecategorizeTest` (3). **0 generation-specific** (no chassis metadata yet), all 496 re-filed
        from `electronics` into subjects -> sensors 137, rom 119, wiring 79, diagnostics 78, ecu 36,
        reference 21, fueling 11, tuning 11, ignition 4.
  - [x] 2 no-tag articles retagged `diagnostics` (Honda-Acura-Trouble-Codes, how-to-check-obd1-ecu-codes);
        review list then empty. Owner approved the mapping as-is, no prunes (generation-filing deferred
        until chassis/model metadata is gathered separately).
  - [x] Executed across en + pt (992 bundles). **Caveat hit:** `git mv` only moves tracked files, but
        the entire pt tree + a few new en articles were uncommitted WIP -> those moves silently failed
        and were finished with a plain filesystem `mv` from the saved plan (git records them as renames
        at commit time anyway). Empty `electronics` dirs removed; reindexed (992 articles, 4235 facets,
        0 compatibilities - expected). en/pt now share one category per slug (0 cross-locale mismatch).
  - [x] Internal absolute links rewritten in 746 files; the 12 residual `/cars/electronics/...` links
        are pre-existing dead links to non-existent legacy wiki UI pages (debug-info, all-pages, ...),
        correctly left untouched. HTTP-verified: new paths 200 (en+pt), old paths 404 (no redirects).
- [x] **P5 - Product-centric personalization** *(done 2026-06-15)*
  - [x] `user_vehicles`->`user_products` table rename (data preserved via `Schema::rename`),
        `UserVehicle`->`UserProduct` model, `User::vehicles()`->`products()`; callers updated
        (`Garage`, `Dashboard`). **Owner decision: member-facing UI keeps its "vehicle" wording and
        the `/me/garage` route** - only the storage generalized.
  - [x] nullable `taxonomy_node_id` FK (nullOnDelete) + `UserProduct::taxonomyNode()`; a node-pinned
        product implies follows for the node's kind/slug + chassis codes (`impliedFollows()` folds in
        `nodeFollows()`). `GarageTest` (3). Node-picker UI + "fits my products" filtering are
        forward-looking (inert until generation metadata + a picker land) - FK + follow plumbing ready.

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
- **2026-06-15** - **P5 done: product-centric personalization. EPIC COMPLETE.** Renamed
  `user_vehicles`->`user_products` (+ `UserVehicle`->`UserProduct`, `User::products()`), added a
  nullable `taxonomy_node_id` FK with node-derived follows; UI/route keep "vehicle"/"garage" wording
  per owner choice. `GarageTest` (3). 60 tests pass, Pint clean, build green, `/me/garage` 302s
  (auth gate). All five phases (P1-P5 + P3b) done; generation-filing of content awaits the owner's
  chassis/model metadata pass.
- **2026-06-15** - **P4 done: content migration executed.** Re-filed all 496 articles (en + pt) out of
  the flat `cars/electronics` into the 9 tag-derived subjects; 0 generation-specific (no chassis
  metadata yet, so generation-filing is deferred). 2 no-tag articles retagged `diagnostics`, no prunes.
  `git mv` failed silently on the uncommitted-WIP pt tree + new en articles; finished those with plain
  `mv` from the saved plan. Reindexed (992 articles, 0 compatibilities), links rewritten in 746 files,
  HTTP-verified new=200/old=404. 57 tests pass. Content changes left uncommitted for the owner to
  commit alongside their in-flight attribution pass (owner choice).
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
