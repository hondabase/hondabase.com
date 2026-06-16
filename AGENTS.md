# AGENTS.md

Orientation for AI coding agents and new contributors working in this repo
(`hondabase/hondabase.com`). Read this before making changes.

## What this is

Hondabase is a community **Honda/Acura knowledge base**: a Laravel 13 + MariaDB app that
renders Markdown articles. It replaces an old Jekyll site. Articles are **git-backed** in
a separate repo for forkability; the database is a *derived, rebuildable* index.

- **App:** Laravel 13 · Livewire 4 · PHP 8.3+ · MariaDB · Tailwind v4 + Vite · Alpine ·
  TipTap (editor only).
- **Live at:** `www.hondabase.com` (docroot `public/`). Sibling app `files.hondabase.com`
  shares auth via a signed cookie.

## Non-negotiable constraints

1. **Forkability.** Content lives in the public `hondabase/articles` repo, cloned to
   `content/`. MariaDB is *derived* — anything in it must be rebuildable with
   `php artisan hondabase:reindex`. **No SaaS** (search = MariaDB FULLTEXT via Scout).
2. **Public database dumps.** The daily database dump (`hondabase:dump`) commits to the public
   site repository (since auth is Discord OAuth only and no email/passwords are collected). It
   excludes security-sensitive credentials (`remember_token`, push keys) and transient tables.
3. **Editing is approval-gated.** Member edits → review queue → attributed git commit.
   Staff edits auto-apply. Every edit is a tracked, revertible `article_revisions` row.
   Commits are authored by the bot with `Co-Authored-By:` the editor + `Reviewed-By:` the
   approver. Never bypass the gate (it is re-checked server-side).
4. **Mobile-first.** The primary user is a hobbyist on a phone.
5. **Articles commit as raw Markdown** (frontmatter + body). The TipTap editor's
   HTML↔Markdown round-trip must stay **lossless** — it is the only edit path. Custom
   constructs (`::: widget :::`, `{{> partial }}`, relative `.md` cross-links) must
   survive a round-trip. See the round-trip harnesses below.
6. **DB-canonical taxonomy & subjects.** The taxonomy (`taxonomy_nodes`) and subjects are DB-canonical and editable directly via the control panel at `/admin/taxonomy`. The initial JSON seed files and the `hondabase:taxonomy:seed` command have been removed; use the public SQL dump (`database/dumps/hondabase.sql`) to bootstrap the taxonomy on fresh installs. `hondabase:reindex` does not touch the taxonomy.
7. **Multi-locale content moves.** Articles exist in both `en` (unprefixed) and `pt` (Portuguese,
   in `content/pt/...`) locales. Any recategorization or folder moves must be executed across
   both locale trees (e.g. using `hondabase:recategorize`) and internal absolute links rewritten.

## Layout

| Path                         | What                                                        |
| ---------------------------- | ---------------------------------------------------------- |
| `app/Livewire/`              | UI components (Explorer, ArticleEditor/Creator, Garage, …) |
| `app/Services/`              | ArticleService (render), ArticleIndexer (reindex), …       |
| `app/Jobs/CommitArticle.php` | Queued, path-limited, attributed git commit                |
| `app/Support/`               | ArticleDocument (frontmatter split/recompose), IdentityCookie |
| `app/Console/Commands/`      | `hondabase:*` + `app:lint-articles`                        |
| `content/`                   | Clone of `hondabase/articles` (the Markdown). Not in this repo. |
| `resources/css/app.css`      | The **single** Tailwind v4 bundle (see `docs/STYLE_GUIDE.md`) |
| `resources/js/editor.js`     | TipTap editor (code-split, loaded only on `/new` + `/edit`) |
| `scripts/*roundtrip*`        | Editor/frontmatter round-trip regression harnesses         |
| `docs/`                      | Maintainer docs (PROGRESS.md is the living log)            |

`PROGRESS.md` is the source of truth for what's built and why — **update it as you work.**

## Conventions

- **pnpm, never npm.** All Node work goes through pnpm (`pnpm install`, `pnpm build`,
  `pnpm dev`). A `pnpm-lock.yaml` is committed.
- **GitHub ops via the `gh` CLI.**
- **PHP style: Laravel Pint.** Run `vendor/bin/pint` before committing; CI runs
  `pint --test`. Scope is configured in `pint.json` (legacy `public/`, `scratch/`,
  `tools/`, `bin/` are excluded).
- **Styling:** Tailwind v4 `@theme` tokens, one bundle, hybrid semantic-class/utility
  approach. Never hard-code palette hex values — see `docs/STYLE_GUIDE.md`.
- **Run as `www-data`.** The app files are owned by `www-data`; after editing, restore
  ownership (`chown -R www-data:www-data <paths>`) or artisan/web requests 500.

## Running things

```bash
# install
composer install && pnpm install
cp .env.example .env && php artisan key:generate

# dev (server + queue + vite + logs)
composer run dev          # or: pnpm dev  /  php artisan serve

# build assets
pnpm build

# tests (in-memory SQLite — no MariaDB needed)
php artisan test          # or: composer test

# code style
vendor/bin/pint           # fix;  pint --test to check

# content / index
php artisan hondabase:reindex      # rebuild the derived index from content/
php artisan app:lint-articles      # validate article structure/frontmatter
php scripts/editor-roundtrip.php   # editor round-trip regression over all articles
```

A systemd unit `hondabase-queue.service` (in `docs/deploy/`) drains the `database` queue
(commit jobs, notifications) in production.

## When you change things

- Touching the **render pipeline, editor, or `ArticleDocument`** → run the round-trip
  harnesses (`scripts/*roundtrip*`); they must stay idempotent or content degrades on save.
- Moving content or changing paths → ensure moves are mirrored in both locales (`en` and `pt`) and
  internal links are updated across the entire corpus.
- Modifying taxonomy nodes or subjects → use `/admin/taxonomy` or the seed command
  `hondabase:taxonomy:seed`, and ensure `hondabase:reindex` successfully updates compatibility links.
- Touching **anything indexed** → confirm `hondabase:reindex` still rebuilds cleanly
  (the index must be reproducible from `content/` alone).
- Adding **content-derived DB state** → make sure it's rebuildable and PII-free.
- Always: `pint`, `php artisan test`, `pnpm build`, restore `www-data` ownership, and
  update `docs/PROGRESS.md`.
