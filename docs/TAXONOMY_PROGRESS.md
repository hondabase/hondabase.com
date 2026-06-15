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

- [x] **P1 - Taxonomy & subjects foundation** *(done 2026-06-15)*
  - [x] `content/_data/taxonomy.json` (data-driven node kinds; real Honda/Acura cars branch w/
        chassis+years; stubbed motorcycles/aircraft) + `content/_data/subjects.json`
  - [x] `taxonomy_nodes` + `subjects` migrations + `TaxonomyNode`/`Subject` models
  - [x] `TaxonomySync` seeds both from the JSON inside `hondabase:reindex` (idempotent, forkable);
        reindex reports 36 nodes / 17 subjects
  - [x] `App\Services\PathParser` (greedy node-prefix match -> nodePath + subject); `TaxonomyTest` (4)
- [ ] **P2 - Compatibility & fits**
  - [ ] `compatibilities` pivot migration (article_id, taxonomy_node_id, source, meta)
  - [ ] sync hydration: inherited (path), explicit (`fits:`), `applies_to`->node bridge
  - [ ] derive node facets into existing `article_facets` (explorer/follows keep working); tests
- [ ] **P3 - Routing, node pages & breadcrumbs**
  - [ ] taxonomy-aware `resolve()` node branch + `node.blade` (reuses Explorer)
  - [ ] generation-rich breadcrumbs + BreadcrumbList JSON-LD; tests + HTTP smoke
- [ ] **P4 - Content migration (file under generations)**
  - [ ] taxonomy-aware `hondabase:recategorize --dry-run` (generation-specific vs multi-fit + prune)
  - [ ] owner approves split + prune list; execute en+pt + internal link rewrite; reindex
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
  (security, not privacy). Old "no PII / private backups repo" rule is dropped. Taxonomy management:
  file-canonical (a control panel edits + commits the JSON; tables stay derived) - panel slated for P3.

## Changelog
- **2026-06-15** - **P1 done.** taxonomy.json + subjects.json (all-products, data-driven kinds),
  `taxonomy_nodes`/`subjects` tables + models, `TaxonomySync` wired into `hondabase:reindex`
  (36 nodes/17 subjects), `PathParser`, `TaxonomyTest` (4). Also: dropped unused `users.email`/
  `email_verified_at`/`password` columns (data minimization) + simplified `UserFactory`. 38 tests pass.
- **2026-06-15** - Epic approved (auto mode). Plan finalized; Phase A foundations confirmed in code;
  this tracker created. Starting P1.
