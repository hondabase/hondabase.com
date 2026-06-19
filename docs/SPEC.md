# Hondabase — As-Built Specification

This is the current, implemented specification of Hondabase, the companion to the original
`SPEC0.md` / `SPEC1.md` (which described the pre-build vision). It records what actually
exists today. Living status lives in `docs/PROGRESS.md`.

> Convention: never use em dashes anywhere in this project. Mobile-first everywhere
> (primary user is a hobbyist on a phone in the garage).

## 1. Goal

A community-driven, GitHub-preserved technical knowledgebase for the whole Honda and Acura
product catalog (cars, motorcycles, aircraft, power equipment, marine), rebuilt from the
original static-site vision into a dynamic app. Two hard principles:

- **Forkability:** articles + their assets live in a public Git repo; the database is a
  derived index that is fully rebuildable from the repo (`php artisan hondabase:reindex`).
- **Preservation:** content and images are stored in Git so nothing is lost when a forum
  or image host disappears.

## 2. Stack

- **Laravel 13** on **PHP 8.5.7**, **MariaDB 11.8** (DB `hondabase`, user `hondabase`).
- **Livewire 4** (+ Alpine, shipped by Livewire and used app-wide).
- **league/commonmark** + **league/html-to-markdown**, **symfony/yaml**.
- **Laravel Socialite** + **socialiteproviders/discord** (Discord login).
- **Google Analytics 4** (gtag, env-gated).
- Styling: hand-written CSS using the pgmfi design tokens (`public/assets/base.css`,
  `article.css`, `explorer.css`). Tailwind/Vite migration is deferred (P7).

## 3. Topology

- **Site app:** this Laravel app at `/var/www/hondabase/www`, served at
  `https://www.hondabase.com` (nginx docroot `public/`, `try_files ... /index.php`).
  Intended home repo: `hondabase/hondabase.com`. The nightly DB dump is committed here.
- **Articles repo:** `hondabase/articles` (public, canonical content), cloned to
  `content/`. Pushed to via a deploy key.
- **Legacy, still served as sub-paths:** `/pgmfi/wiki`, `/pgmfi/forum`,
  `/reference/error-codes`, `/guides/...` (the old homepage is backed up at
  `storage/legacy-index.php.bak`).
- **Sibling app:** `/var/www/hondabase/files` (`files.hondabase.com`), DB `hondabase_files`,
  the Discord-authed file archive. Shares the Discord application; full cross-app session
  SSO (a signed `.hondabase.com` cookie) is still pending.

## 4. Content model (articles repo)

```
content/<type>/<category>/<slug>/<slug>.md      + co-located image/attachment assets
```

- **type:** `cars`, `motorcycles`, `aircraft`, `common`. **category:** the folder. The
  folder IS the category; never in frontmatter. **title:** first `# H1` (or `title:`).
- **Frontmatter is optional and additive** (see `content/docs/article-format.md`). Key
  block is `applies_to`, which is **flexible and open-ended** for the whole catalog:

```yaml
applies_to:
  brand: [honda, acura]
  models: [civic, integra]
  chassis: [EG, EK, DC2]
  engines: [B-Series, B18C]   # no-digit value = whole family; with a digit = specific engine
  ecus: [P28, P30, PM6]
complexity: beginner
tags: [ecu, obd1, diagnostics]
```

Every `applies_to` field renders in the article's "Applies to" panel (engine families get
badges; everything else shows as chips). OBD belongs in `tags` only (`obd0`, `obd1`,
`obd2`, etc.) because it is too ECU-specific to be a global applicability axis.
`last_updated` is git-derived.

Authoring/style rules and source-faithful porting rules are in
`content/docs/article-format.md`; contribution overview in `content/CONTRIBUTING.md`.

## 5. Rendering

`app/Services/ArticleService.php`:
- CommonMark (GitHub-flavored) + heading-anchor permalinks; `html_input => escape` (raw
  HTML in articles is escaped).
- The first H1 becomes the page title; the body is rendered into the article page.
- Relative image `src` and relative attachment `href` are rewritten to the co-located asset
  route.
- **Image carousels:** fenced `carousel` blocks containing local image slides render as an
  accessible manual-control/swipe carousel and have a visual TipTap editor node.
- **Widgets:** a `::: widget <name> key="value" :::` directive renders a trusted Blade
  widget. Resolver: `app/Services/WidgetRenderer.php`; views in `resources/views/widgets/`;
  first widget is `error-codes` (interactive OBD code lookup, Alpine). See `docs/widgets.md`.

Routes (in `routes/web.php`), all type-constrained so they never shadow legacy paths:
- `/{type}/{category}/{slug}` article, `/{type}/{category}/{slug}/{file}` asset,
  `/{type}/{category}` category page, `/` explorer homepage, `/sitemap.xml`.

## 6. Index and facets (derived, rebuildable)

- `php artisan hondabase:reindex` scans the content repo and rebuilds:
  - `articles` (type, category, slug, title, summary, complexity, body_text [FULLTEXT],
    repo_path, updated_at).
  - `article_facets` (`kind`, `value`, `label`): a **flexible facet** per type, category,
    tag, and every supported `applies_to` field (engine families normalized). OBD is indexed
    only through tags.
- Forkability invariant: drop the DB, `migrate`, `reindex` and the dynamic site is fully
  restored from the repos.

## 7. Explorer + search (homepage)

`app/Livewire/Explorer.php` + `resources/views/livewire/explorer.blade.php`:
- **Content-shifting search (not autocomplete):** the query re-renders the whole surface
  (article cards + facet groups + counts) in place.
- **Facet groups** with live counts (Categories, Engine family, Tags, ECUs, ...);
  clicking a facet filters (AND across active filters).
- **Context-aware scope:** on a category page the same component is scoped to that category
  (`<livewire:explorer type=... category=... />`) with an "Everything" toggle.
- Mobile-first (facets become a stacked panel on small screens).

## 8. Auth

`app/Http/Controllers/AuthController.php` + Socialite:
- Discord OAuth reusing the files app's Discord application; redirect
  `https://www.hondabase.com/auth/callback`.
- Scopes `identify guilds guilds.join` only (**email is never requested or stored**).
- Community guild membership required, with bot auto-join; otherwise a "join the Discord"
  page.
- `users` carries `discord_id` (unique), `discord_username`, `avatar`, `github_id`,
  `github_login`; `email`/`password` are nullable and unused. Sign-in/out in the header.

## 9. Follows and the opinionated homepage

- A signed-in user can **follow any facet** (a star on every facet chip) - category, tag,
  engine family, ECU, chassis, etc. This is the "interests" mechanism.
  Stored in `follows` (`user_id`, `kind`, `value`, `label`).
- The homepage becomes **opinionated** for that user: a "For you" row of recent articles
  matching their follows, and the main grid is re-ordered by how many follows each article
  matches.

## 10. Analytics

GA4 (`G-63JRK5RNJM`, env `GA_MEASUREMENT_ID`, optional/forkable). `public/assets/ga.js`
sends article-aware events: `article_view` with `category`, `vehicle_type`, `complexity`,
`obd`, `engine`, `tags`; plus `search`, `facet_select`, and SPA `page_view` on
`wire:navigate`. Register the parameters as GA4 custom dimensions to report on them. See
`docs/analytics.md`.

## 11. Backups

`php artisan hondabase:dump` writes `database/dumps/hondabase.sql` (plain SQL, git-diff
friendly), excluding transient tables (sessions, cache, jobs, ...). No email is present
(never collected). Scheduled daily at 00:00, only when something changed that day; the dump
is committed **with the site repo** (pending the site repo's Git connection).

## 12. Tooling and docs

- Commands: `hondabase:reindex`, `hondabase:dump`, `app:lint-articles` (frontmatter linter),
  `hondabase:audit-presentation` (report-only presentation cleanup queue).
- Wiki migration: `bin/port-wiki.php` (TWiki HTML to Hondabase Markdown), `WIKI_PORTING_PLAN.md`.
- Docs: `docs/PROGRESS.md`, `docs/widgets.md`, `docs/analytics.md`, this `docs/SPEC.md`;
  content/authoring docs in the articles repo (`content/docs/article-format.md`,
  `content/CONTRIBUTING.md`).

## 13. Built vs pending

**Built and live:** explorer + content-shifting + scoped search, article rendering +
"Applies to" panel + widgets, the facet index + reindex, Discord login + guild gating,
follows + opinionated homepage, sitemap, GA4, the nightly dump command + schedule. ~74
electronics articles ported.

**Pending:**
- P4: in-browser WYSIWYG (TipTap) editor + draft/review/publish approval + git commit
  attribution (commit as bot with `Co-Authored-By` the editor, `Reviewed-By` the approver,
  pushed via deploy key).
- Cross-app SSO (shared `.hondabase.com` session cookie with the files app).
- Site repo Git connection (so the nightly dump and app code are versioned).
- P5 extras: user garage (vehicles/equipment), web-push notifications.
- P7: Tailwind/Vite build of the pgmfi tokens, `AGENTS.md`, hardening, CI.
- P8: full wiki port, move `pgmfi` to its own subdomain, i18n.
