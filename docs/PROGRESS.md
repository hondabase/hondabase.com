# Hondabase - Implementation Progress

Living log of the Hondabase rebuild. Plan of record:
`/root/.claude/plans/continue-wobbly-iverson.md` (v5 + addenda, approved 2026-06-13).

## Environment (verified 2026-06-13)
- **PHP** 8.5.7 · all Laravel extensions present
- **Laravel** 13.15.0 · **Livewire** 4.3 · **Socialite** 5.27 · **Composer** 2.10.1
- **MariaDB** 11.8.6 - app DB `hondabase` (user `hondabase`, TCP 127.0.0.1); root via socket
- **Node** 24.14 / **npm** 11.9 (Vite available; Tailwind pipeline deferred to P7)
- **Web:** `www.hondabase.com` → docroot `/var/www/hondabase/www/public`
  (nginx `try_files … /index.php`); `/pgmfi/wiki/` has its own route. nginx healthy.
  *(Local curl verification needs `--resolve www.hondabase.com:443:127.0.0.1` for SNI.)*
- **Sibling app:** `/var/www/hondabase/files` (`files.hondabase.com`) - DB `hondabase_files`,
  Discord auth, git-backed `storage/archive` (repo `hondabase/files-archive`). SSO target.
- **Wiki source for sample data:** MariaDB `pgmfi_wiki_archive.topics` (514 `library` +
  56 `home`); electronics = ECU/wiring/sensor/injector/ignition cluster in `library`.
  `attachments` table → co-located bundle assets. (Full wiki port is P8.)

## Operations (live infra)
- **Queue worker:** systemd unit `hondabase-queue.service` (in repo at
  `docs/deploy/hondabase-queue.service`) runs `php artisan queue:work database` as `www-data`,
  enabled at boot, `Restart=on-failure`, `ExecReload=queue:restart`. Drains the `database`
  queue (CommitArticle git jobs + upcoming notifications). Verified processing a probe job.
  Deploy on a fresh box: copy the unit to `/etc/systemd/system/`, `systemctl daemon-reload`,
  `systemctl enable --now hondabase-queue`.

## Status by phase
- [x] **P0 - Scaffold + SSO** *(done)*
  - [x] Composer installed; Laravel 13 scaffolded + merged into project root (legacy intact)
  - [x] MariaDB `hondabase` provisioned; `.env` (mariadb, session=database,
        SESSION_DOMAIN=.hondabase.com, queue=database); default migrations run
  - [x] Ownership → `www-data`; app boots; **APP_DEBUG=false** (site is public)
  - [x] Livewire 4.3 + Socialite 5.27 installed
  - [x] **Homepage cut over to Laravel** - pgmfi-styled interim landing live at
        `www.hondabase.com`; legacy `/pgmfi/{wiki,forum}`, `/reference/error-codes`,
        `/guides/…` all verified **HTTP 200**. Legacy homepage backed up to
        `storage/legacy-index.php.bak`.
  - [x] Discord OAuth login (Socialite + socialiteproviders/discord, reusing the files-app
        Discord application) + guild gating/auto-join + sign-in/out UI; `users` gained
        discord/github columns. **Live login confirmed working** (2026-06-14) - the
        `https://www.hondabase.com/auth/callback` redirect URI is registered on the Discord app.
  - [x] **Signed `.hondabase.com` identity cookie + `files/` verification (cross-stack SSO).**
        `www` (the IdP) issues an HMAC-signed `hb_identity` cookie on Discord login
        (`App\Support\IdentityCookie`), scoped to `.hondabase.com`, NOT Laravel-encrypted
        (added to `encryptCookies(except:)` in `bootstrap/app.php`) so plain PHP can verify it.
        Token = `base64url(json).base64url(hmac_sha256(json, secret))` carrying only shared
        Discord identity (id/name/avatar), never PII; `exp` = session lifetime. The non-Laravel
        `files/` app verifies + hydrates its session on every request (`sso_hydrate_session()`
        in `app/bootstrap.php`, helpers in `app/auth.php`) and upserts the user. `www` logout
        clears the cookie, and `files/` logout calls `sso_clear_cookie()`, so logout is
        SSO-wide. Shared secret = `HONDABASE_SSO_SECRET` (www `.env`) == `SSO_SECRET`
        (files `config.local.php`). **Verified end-to-end:** files is anonymous without the
        cookie and shows the signed-in user (Sign out + username) when sent a www-issued cookie;
        tampered/expired tokens are rejected.
  - [x] **www is the SOLE identity provider.** `files/`'s `auth_login()` now redirects to
        `SSO_IDP_URL/auth/login?return=<files URL>` (return derived from `after_login` or a
        same-origin Referer) instead of running its own Discord OAuth. `www`'s `login()`
        stashes a `return` query param (validated to `https://*.hondabase.com` only, via
        `isSafeReturn`, blocking open redirects + subdomain spoofs like
        `files.hondabase.com.evil.com`) across the Discord round-trip and `callback()` bounces
        back to it once the cookie is set. Guild gating is therefore enforced once, at the IdP.
        files' own Discord callback is retained as an unused fallback. **Verified:** files
        `/auth/login` 302s to the www IdP carrying the return URL; www stashes only safe
        returns; both apps still 200.
- [x] **P1 - Articles repo + wiki sample data** *(done)*: `hondabase/articles`
  cloned to `content/`; `bin/port-wiki.php` + commonmark/html-to-markdown added;
  lenient frontmatter linter implemented as `app:lint-articles` artisan command;
  initial 17 electronics articles branch (`import/wiki-electronics`) reviewed, linted, and merged to `main`;
  second batch of 7 curated electronics articles ported, reformatted, and merged to `main`;
  created a comprehensive wiki porting plan (`WIKI_PORTING_PLAN.md`) after analyzing all 513 wiki topics;
  ported and reformatted a third batch of 5 high-priority articles (including a large ECU definition table using DOM table parsing).
- [x] **P2 - Rendering, widgets & routing** *(done)*: `ArticleService`
  (CommonMark GFM + heading anchors + safe-escaped HTML), article + category pages
  (pgmfi, mobile-first, `article.css`), co-located image route, 404s, homepage KB link,
  frontmatter "Applies to" panel (OBD / engine / chassis / ECU badges + complexity + tags
  + summary; YAML via symfony/yaml; fully optional).
  Plus a `::: widget :::` directive + an interactive **error-codes** Alpine widget
  (self-hosted alpine.min.js, WidgetRenderer, embedded in the trouble-codes article).
  **Completed 2026-06-14:** `{{> partial }}` includes (`content/_partials/<name>.md`,
  expanded pre-Markdown so partials may carry widgets/links, recursion-guarded, unknown
  partials left verbatim); **internal cross-linking** (relative `.md` links between bundles
  rewritten to clean article routes, fragments preserved, resolved against the article dir;
  content already uses absolute `/type/cat/slug` links which pass through unchanged);
  **in-article find-in-page** = the article-scope `ScopedSearch` (sticky, mobile-first Alpine
  bar registered on `alpine:init` so it survives `wire:navigate`; highlights matches in the
  prose with count + next/prev). Chassis routing/redirects N/A to the current category-based
  tree (no chassis content yet); deferred to the P8 full wiki port when `_data/redirects.yaml`
  exists.
- [x] **P3 - Index, browse, search + explorer** *(done)*: `articles` +
  `article_facets` index (flexible facets from type/category/tags/**every** applies_to field)
  + `hondabase:reindex` (74 articles, 494 facets). Livewire **Explorer** homepage:
  content-shifting search (not autocomplete) + clickable facet groups (Categories / Engine
  family / OBD / Tags / ECUs / Scope) with live counts + recent-first cards, mobile-first.
  Livewire provides Alpine app-wide (error-codes widget reuses it). **Follows + opinionated
  homepage built**: signed-in users follow any facet (star on every chip); a "For you" row +
  follow-weighted ordering surface their interests (verified with a simulated user).
  Category-scoped search (explorer reused per category with an "Everything" escape),
  `/sitemap.xml`, and (2026-06-14) **article-level find-in-page** are all done; this phase's
  search scopes are complete. Live Discord login confirmed working.
- [~] **P4 - Editing, approval & git attribution** *(core pipeline live + verified)*:
  end-to-end suggest -> review -> attributed commit is built. `article_revisions` table +
  model (LCS line `diff()` + context-collapsing `compactDiff()`). **In-browser editor**
  (`App\Livewire\ArticleEditor`, auth-gated, `/edit/{type}/{category}/{slug}`): edits the raw
  markdown (frontmatter + body) with a **live, content-shifting preview** rendered by the
  *same* `ArticleService` pipeline as a published page (refactored `renderBody()` shared by
  `find()` + new `preview()`/`rawMarkdown()`), edit-summary field, no-op guard; mobile-first
  Edit/Preview tabs (split-pane on wide screens). Submit creates a **pending revision (no git
  yet)**. **Owner-only review queue** (`/admin/reviews`, `review-articles` Gate via
  `User::isOwner()`, nav link for owners): compact diff + editor note, approve (queues commit)
  / reject with note, **unpushed banner**. **`App\Jobs\CommitArticle`** (queued, idempotent on
  `commit_sha`, retryable): writes the file, **path-limited commit** (never sweeps unrelated
  dirty files) authored by the bot with subject `Update <path> (by <DiscordName>)`, the editor
  via **`Co-Authored-By:`** (real GitHub no-reply if linked via `User::gitIdentity()`, else a
  stable synthetic `…@discord.hondabase.com`) + **`Reviewed-By:`** the approver, then reindex,
  then best-effort **push** (off until a deploy key exists: `HONDABASE_GIT_PUSH`, config
  `hondabase.git`; unpushed commits surface in the admin count). **Verified end-to-end** in an
  isolated repo: linked-owner `Reviewed-By` got a `users.noreply.github.com` address, unlinked
  editor `Co-Authored-By` got the synthetic one, the unrelated dirty file stayed out of the
  commit, file updated on disk, unpushed=1. HTTP gates verified (anon `/edit` + `/admin/reviews`
  302 to login; article page shows the auth-aware edit CTA).
  **Deviation from plan:** editor is a **markdown editor with live preview**, not TipTap WYSIWYG
  (TipTap needs the Vite/Tailwind build deferred to P7; this fits the current no-build
  Livewire+Alpine stack and round-trips the file byte-for-byte).
  **Staff role + reversibility (2026-06-14):** `users.is_staff` + `User::isStaff()` (owner is
  implicitly staff); the gate is now **`manage-articles`** (= staff or owner) and grants
  *everything* article-related. `hondabase:staff <user> [--revoke|--list]` grants the role.
  **Staff edits auto-apply:** a `manage-articles` user's submit is self-approved + committed
  immediately (re-checked server-side, so a tampered client `canManage` cannot bypass the
  queue); members still go to review. **Everything is tracked + reversible:** every edit is an
  `article_revisions` row (pending/approved/rejected all retained); `App\Livewire\ArticleHistory`
  (`/admin/history` global recent-applied, `/admin/history/{type}/{cat}/{slug}` per-article full
  trail) shows diffs + state + commit/push + reviewer + revert links; a staff **Revert** records
  a *new* auto-approved revision whose commit restores the pre-edit snapshot (`reverts_revision_id`
  link, `Revert to before edit #N …` subject + `Reverts:` trailer) so history is never rewritten
  and a revert is itself revertible. **Verified end-to-end** (isolated repo): member edit stays
  pending + tampered `canManage` ignored; staff edit auto-commits self-approved; revert restores
  the file + links + commits with the revert subject/trailer; gate denies members and member
  `revert()`/`approve()` action calls produce **zero** side effects.
  **Single-article reindex (2026-06-14):** the commit job no longer rebuilds the whole index;
  `App\Services\ArticleIndexer::indexOne($type,$cat,$slug)` re-scans just the touched article
  (and removes its rows if it was deleted), so a commit updates exactly one index row.
  `hondabase:reindex` still does the full rebuild (forkability invariant intact).
  **Edit-conflict detection (2026-06-14):** at apply time `CommitArticle` compares the on-disk
  file against the edit's `original_body` base; if main moved underneath it the revision is
  **parked as `conflicted`** (new enum value) and **nothing is committed or overwritten**. The
  review queue lists conflicts in their own section with a **Re-base & commit** action
  (`RevisionReview::rebase()` refreshes the base to current on-disk + re-queues, keeping the
  editor's proposed body) or reject; the history view shows a `conflicted` badge.
  **Verified end-to-end** (isolated repo): a stale-base edit parks as `conflicted` with the file
  untouched and no commit; a clean-base edit commits; a re-based conflict then commits cleanly.
  **New-article creation + image upload (2026-06-14):** `/new` route + `App\Livewire\ArticleCreator`
  (auth-gated): pick type/category/slug (existing categories offered via datalist), write raw
  markdown with the **same live preview pipeline** as the editor, and upload co-located images
  (Livewire `WithFileUploads`, jpg/png/gif/webp ≤4 MB, names slug-sanitized + de-duped). Submit
  records an `ArticleRevision` exactly like an edit (member → review queue, staff → auto-applied),
  with `original_body=''` (new file, passes the conflict check) and the bundle filenames in a new
  **`assets` JSON column**; the uploads are staged in `storage/app/pending-assets/{id}` until the
  queued `CommitArticle` writes **both the `.md` and the images in one path-limited commit**
  (`git add -- <paths>` first, since untracked files can't be partial-committed; cleans staging on
  success). **Verified end-to-end** (isolated repo): staff create → `.md` + image committed
  together with `Co-Authored-By`/`Reviewed-By`, article indexed; member create → pending, nothing
  committed, uploads staged for approval; `/new` 302s anon to login.
  **Staff-management UI (2026-06-14):** owner-only `manage-staff` gate + `/admin/staff`
  (`App\Livewire\StaffManager`): search users, grant/revoke the staff role (the UI form of
  `hondabase:staff`); the owner is always staff and can't self-toggle. Nav links added for
  "New article" (all auth) and "Staff" (owner). **Verified** (renders, refuses owner toggle,
  grants a member, anon 302).
  TODO: TipTap in P7 (planned deviation; needs the Vite build); run a queue worker (jobs sit on
  the `database` queue).
- [x] **P5 - Community & personalization** *(done)*:
  **Favorites, garage CRUD, "My Hondabase" dashboard + onboarding** built on the no-build
  Livewire+Alpine stack (2026-06-14). New instance-local tables (`2026_06_14_150000_create_community_tables`):
  `favorites` (user↔article bookmark, unique, cascades with the derived index), `user_vehicles`
  (year/make/model/chassis/engine/nickname/notes, free-form by design since the catalog is
  facet-derived), `user_equipment` (ecu/wideband/software/tool). Models `Favorite`,
  `UserVehicle` (`label()` + `impliedFollows()`), `UserEquipment` (`KINDS`); `User` gained
  `favorites()`/`vehicles()`/`equipment()`. **`FavoriteButton`** Livewire (article header
  Save/Saved star; resolves the `articles` row from type/cat/slug so the file-rendered page
  needs no controller change; anon click redirects to login with `return`). **`Garage`**
  (`/me/garage`): add/edit/delete vehicles + equipment, mobile-first forms, engine `<datalist>`
  from facet labels; **saving a vehicle seeds facet follows** (engine family + chassis,
  skipping dupes) so the feed self-populates. **`Dashboard`** = "My Hondabase" (`/me`):
  onboarding card when the account is empty, else garage summary + a **Following feed** (recent
  articles matching any followed facet, the `forYou` query reused) + removable follow chips +
  saved-articles list (unsave inline). Nav gained "My Hondabase"; `me.css` added; favorite
  styles appended to `article.css`. **Verified end-to-end** (Livewire test user): empty
  dashboard shows onboarding; adding a B-Series/DC2 vehicle created 2 follows + the populated
  dashboard renders garage/feed/saved; favorite toggles; test data cleaned up. HTTP: `/me` +
  `/me/garage` 302 anon, article page 200 with the Save button.
  **Notifications (2026-06-14):** `CommitArticle` now notifies followers after the article is
  committed + indexed (first apply only; reverts/conflicts don't notify, the editor is excluded,
  failures are caught so they never break the commit). `FollowerNotifier` resolves users
  following any of the article's facets with a per-user reason ("Matches your B-Series");
  `ArticleChanged` notification + Laravel `notifications` table (database channel); `NotificationBell`
  Livewire bell in the nav (unread badge, dropdown, open-marks-read + go-to-article, mark-all-read).
  **Web-push (2026-06-14):** `laravel-notification-channels/webpush` installed, local VAPID
  keypair generated into `.env` (documented in `.env.example`; no external account), `push_subscriptions`
  table + `HasPushSubscriptions` on `User`. `ArticleChanged::via()` adds the web-push channel only
  for users with a subscription; `toWebPush()` sends the headline. Service worker `public/sw.js`
  (push + notificationclick → open/focus article); subscribe toggle on `/me` (Alpine: registers SW,
  requests permission, subscribes with the VAPID key, POSTs to `/me/push`); `PushSubscriptionController`
  (store/destroy). **Verified:** follower notified + editor excluded + payload correct + bell renders
  + mark-all-read clears; web-push `via()` gates on subscription, `toWebPush` builds; `/sw.js` 200,
  push routes auth-gated, all blades compile, vendor re-chowned to www-data.
- [~] **P6 - Analytics & nightly dump**: **Google Analytics 4** live (G-63JRK5RNJM,
  env-driven via `GA_MEASUREMENT_ID`; reuses the files-app property). Article-aware events in
  `public/assets/ga.js`: `article_view` carries category/vehicle_type/complexity/obd/engine/
  tags (from data attrs); `search` + `facet_select` on the explorer; SPA `page_view` on
  `wire:navigate`. Documented in `docs/analytics.md`. **Nightly dump done:** `hondabase:dump`
  (change-gated, transient tables excluded, no email PII collected) is scheduled daily at 00:00
  in `routes/console.php` and commits the `.sql` with the site repo. TODO (external/non-code):
  register the article event params as GA4 custom dimensions in the GA admin console.
- [x] **P7 - WYSIWYG editor, Tailwind styling, docs, hardening, CI** *(scope revised 2026-06-14; done 2026-06-15)*:
  **Foundation done (2026-06-14):** Vite/Tailwind v4 pipeline wired into the layout
  (`@vite()` replaces the static `base.css` link; bunny web-fonts dropped so builds stay
  offline). pgmfi tokens ported into `resources/css/app.css` `@theme` (colors/surfaces/fonts →
  utilities + `:root` vars); `base.css` component styles moved into `@layer base`/`@layer
  components` (**hybrid** approach: semantic classes for repeated pgmfi patterns, utilities for
  one-off layout in Blade). Transitional `:root` aliases map the old short var names so the
  not-yet-migrated `article`/`explorer`/`me`/`editor`.css keep rendering until each is converted.
  Build verified; homepage serves the built bundle; article/category/`/me` still correct.
  **Stylesheet consolidation done (2026-06-14):** the four remaining hand-written sheets
  (`article`/`explorer`/`me`/`editor`.css) were moved to `resources/css/legacy/` and `@import`ed
  into the single Vite bundle (unlayered, preserving cascade); the per-page `<link>` tags were
  removed from all 10 templates and the five static files deleted from `public/assets/` (only
  `alpine.min.js` + `ga.js` remain there). One bundle now ships site-wide (79.5 kB → 17 kB gz);
  every page verified loading only `/build/assets/app-*.css` with no legacy links. The article
  template was found **already semantic** (`<article>/<header>/<h1>/<section>/<footer>`, `<nav>`
  breadcrumb, `<time>`) and already emits `TechArticle` JSON-LD. **Remaining migration:** convert
  the legacy `@import`ed CSS to Tailwind utilities/components and drop the var-alias block when the
  last consumer is gone (cosmetic; no visual change).
  **Breadcrumb SEO + a11y done (2026-06-14):** `BreadcrumbList` JSON-LD added to the article and
  category pages (Home → category → article), and the breadcrumb `<nav>`s got `aria-label`
  + `aria-current="page"`; validated rendering valid JSON-LD over HTTP. Remaining semantic work:
  finish the home/category listing markup (`<ul>`/`<section>`) as those templates are migrated.
  **Semantic HTML5 + SEO (added to scope 2026-06-14):** the layout shell already uses
  `<header>/<nav>/<main>/<footer>`; as each template is migrated, give content real semantics
  (`<article>` + single `<h1>` + `<section>`s, `<nav aria-label>` breadcrumbs, `<time>` dates,
  `<ul>` listings) for accessibility + cleaner crawling. Semantic tags are a *weak/indirect* SEO
  signal on their own, so pair them with the real wins: per-page `<title>`/meta/canonical/OG +
  **JSON-LD structured data** (`TechArticle`, `BreadcrumbList`) on top of the existing sitemap.
  a **TipTap WYSIWYG editor** is wanted so non-technical members can contribute without writing
  Markdown; it **replaces** the current markdown+live-preview editor outright (no point keeping a
  raw-Markdown editor for "power users" - one editor, lower maintenance). TipTap needs a JS
  build, so **Vite comes in** - and since Vite is in regardless, the **Tailwind migration is back
  on**: port the established pgmfi look from the hand-written CSS
  (`hondabase.css`/`article.css`/`me.css`/`editor.css`) into **Tailwind (latest, v4)** using its
  full feature set (CSS-first `@theme` tokens, no legacy `tailwind.config.js`), so the existing
  visual design is preserved, not redesigned. **TipTap round-trip constraint (now load-bearing):**
  with the raw-Markdown editor gone, TipTap is the *only* path edits flow through, so its
  HTML<->Markdown bridge must be **lossless** or content degrades on every save - articles still
  commit raw Markdown (frontmatter + body) to the repo. Frontmatter is edited via structured
  fields, not the rich-text canvas. Custom constructs must survive: the `::: widget :::`
  directives, `{{> partial }}` includes, and relative `.md` cross-links need dedicated TipTap
  nodes (or protected raw blocks) so they aren't mangled into plain text.
  **TipTap round-trip VALIDATED (2026-06-14) - architecture proven before touching the live
  editor.** Stack installed **via pnpm** (project is pnpm-based; never npm - see
  [[use-pnpm-never-npm]]): `@tiptap/core@3` + `@tiptap/starter-kit@3` + `@tiptap/extension-table@3`
  (`TableKit`) + `tiptap-markdown@0.9` (+ `jsdom` dev). A headless harness
  (`scripts/tiptap-roundtrip.mjs`, kept as a regression test) runs the **real** pipeline over all
  503 articles: **98.8% idempotent** (re-saving a saved file is byte-identical - no progressive
  drift, the property that matters for the only edit path) and **99.2% text-stable**. Critically,
  **GFM tables round-trip losslessly** (cell separation, bold-in-cell, inline code, ASCII-art code
  blocks all intact) - the three pinout/ECU table articles that a naive server-side
  `league/html-to-markdown` proxy *mangled* (cells concatenated, `**` leaking) are all idempotent
  here. `::: widget :::` directives **survive verbatim** (markdown-it passes them through as a
  paragraph; making them friendly editor nodes is a later UX nicety, not a data-loss risk). The
  only 4 non-stable files are pre-existing malformed legacy "link-soup" imports (e.g. `rom-maps`,
  a heading glued to text) already slated for reformatting; the harness flags them.
  **TipTap editor BUILT + WIRED IN (2026-06-14) - the textarea is gone.** The rich-text editor is a
  code-split entry (`resources/js/editor.js`, loaded only on `/new` + `/edit` via `@vite` in the page
  `@push('head')`, so readers never download the ~193 kB-gz TipTap bundle; site-wide `app.js` is now
  empty). It registers an Alpine `tiptapEditor` component on `alpine:init` (survives `wire:navigate`),
  mounts under `wire:ignore` so Livewire never morphs ProseMirror's DOM, and pushes serialized
  Markdown back to the `$bodyMarkdown` Livewire property (debounced) to drive the same server-rendered
  live preview as before. A `save()` flushes the latest Markdown in the submit round-trip so a click
  mid-keystroke never loses content. The extension set is byte-identical to the validated harness, so
  the round-trip guarantee still holds. **Frontmatter is now structured fields, not raw YAML**:
  `App\Support\ArticleDocument` splits a file into frontmatter + body and recomposes canonical YAML
  (per-key inline depth → house style: inline `tags`/leaf lists, block `applies_to`, block `sources`);
  the shared `App\Livewire\Concerns\EditsFrontmatter` trait exposes summary / complexity / tags /
  repeatable applies_to rows / source cards, and **preserves any unmodeled key verbatim** (e.g. a
  hand-set `title`) so editing never drops metadata. Both `ArticleEditor` + `ArticleCreator` use the
  trait + TipTap (the edit-reason field renamed `summary`→`note` to free `summary` for frontmatter).
  **Three round-trip harnesses pass over all 503 articles:** the TipTap body (98.8% idempotent),
  `scripts/frontmatter-roundtrip.php` (100% idempotent, 100% data preserved), and
  `scripts/editor-roundtrip.php` (the full file→parse→structured-fields→recompose path: **100%
  idempotent, 100% frontmatter preserved**). Verified end-to-end with the Livewire test harness
  (both components mount + hydrate; a real edit composes the correct `proposed_body` with canonical
  `tags: [...]`; rolled back, no DB/git side effects) and over HTTP (article/home 200, `/new` +
  `/edit` 302 anon). **Known one-time cost (accepted per plan):** existing content is not yet in
  TipTap/canonical-YAML form, so the *first* edit of each article reflows its whole body + frontmatter
  (idempotent thereafter); only ~1.4% are already byte-identical. A future `hondabase:normalize` batch
  pass could absorb this churn ahead of editors.
  **editor.css CONVERTED + DELETED (2026-06-14):** its editor/creator/review/history/staff styles were
  ported into `app.css` `@layer components` on the canonical `--color-*`/`--font-*` `@theme` tokens
  (plus new TipTap canvas/toolbar/structured-metadata styles), the `@import './legacy/editor.css'`
  dropped and the file removed; the five consuming blades kept their class names, so one bundle now
  styles them (CSS 79.7 → 84.8 kB). Remaining P7: convert the last three legacy sheets
  (`article`/`explorer`/`me`.css) + drop the var-alias block, `STYLE_GUIDE.md` (tokens as `@theme`),
  `AGENTS.md`, hardening, CI.
- [~] **P8 - Later: full wiki port, pgmfi subdomain, i18n** *(i18n started 2026-06-15)*:
  **Scope decided (2026-06-15, user):** full **UI + content** i18n; first target locale is
  **European Portuguese (`pt`, hreflang `pt-PT`)** alongside English (the default + fallback).
  **i18n foundation DONE (2026-06-15):** supported-locale map in `config/hondabase.php`
  (`locales` keyed by app code → `native` + `hreflang`); **`App\Http\Middleware\SetLocale`**
  (appended to the `web` group) resolves the active locale per request as **cookie →
  `Accept-Language` → app default**, honouring only declared locales; **`LocaleController@switch`**
  + `GET /locale/{locale}` (named `locale.switch`) persists the choice in a forever cookie and
  redirects back, ignoring unsupported codes. The shared layout now emits a dynamic
  `<html lang="{hreflang}">`, runs its chrome strings through `__()` (nav, footer, tagline,
  title/description), and renders a **footer language switcher** (native names, current marked,
  `hreflang`/`rel=nofollow` on links; `.lang-switch` styles added). UI strings translated via the
  JSON catalog **`lang/pt.json`** (European Portuguese: "Ficheiros", "A Minha Hondabase", "Equipa",
  "Iniciar sessão", …). **Verified** end-to-end over HTTP (en default; `locale=pt` cookie flips
  `<html lang>` + chrome to pt-PT; switch route sets the encrypted cookie + redirects; bad locale
  ignored) and with **`tests/Feature/LocaleTest.php`** (5 tests: default, cookie, Accept-Language,
  switch persists, bad-locale ignored). 19 tests pass; Pint clean; build green.
  **`__()` coverage extended to the member-facing Livewire views (2026-06-15):** explorer,
  dashboard, garage (+ its flash messages and `Garage.php`), the TipTap editor + creator and their
  shared partials (`frontmatter-fields`, `editor-canvas`), `favorite-button`, and `notification-bell`
  all run their UI strings through `__()`/`trans_choice` (placeholder-aware: `:label`/`:id`/`:date`,
  `{!! __() !!}` for the few links/`<strong>` spans, pluralized article + count strings). `ArticleCreator`
  flash/validation messages wrapped too. **Admin views stay English by decision (user, 2026-06-15):**
  the `/admin/*` surfaces - `revision-review`, `article-history`, `staff-manager`, `revision-assets` -
  are intentionally **not** translated (staff/owner-only tooling). The `lang/pt.json` catalog grew to
  ~150 European-Portuguese entries; JSON valid, Pint clean, all 19 tests pass, `view:cache` compiles
  every blade, Vite build green, `tinker` confirms the pt catalog + plural rules resolve.
  **Content i18n DONE (2026-06-15):** article bodies are now served per locale. Translations live in
  a **separate locale tree** (`content/pt/{type}/{category}/{slug}/{slug}.md`); a translation folder
  holds only the `.md` and any missing non-Markdown assets **fall back to the English bundle**
  (unprefixed, locale-agnostic asset URLs). English stays **unprefixed + canonical**; non-default
  locales get **prefixed mirror routes** (`/{locale}/{type}/{category}/{slug}` and `…/{category}`),
  constrained to the declared "others" alternation (`pt`) so they never shadow a content type.
  `App\Support\Locales` centralises the locale map (default/others/hreflang/route pattern); `SetLocale`
  gained a **leading-URL-segment source at highest precedence** (a shared `/pt/...` link renders in its
  own language). `ArticleService` read methods (`find`/`articlesIn`/`categories`/`categoryExists`/
  `assetPath`/`scan*`) are locale-aware with English fallback (`is_fallback` flag). **Phase B index:**
  migration added a `locale` column (unique now `type+category+slug+locale`); `hondabase:reindex` walks
  the `pt` subtree (en=496, pt=400 rows); facets/follows stay on the **English identity** only
  (language-agnostic). `Explorer` overlays translated title/summary and broadens full-text search to the
  active-locale `body_text` when the locale ≠ en, with locale-aware result links. SEO: per-locale
  `<link rel="canonical">`, `hreflang` alternates for every available translation + `x-default`→English,
  a **per-article language switcher** (only locales with a real translation), and a "showing the English
  version" fallback notice. **Verified** over HTTP (`--resolve` SNI): en canonical/unprefixed with
  `pt-PT` alternate; `/pt/...` renders the translation with a self-canonical; an untranslated `/pt/...`
  falls back to English + notice; a co-located image on a `/pt/` page loads from the en bundle; `/xx/...`
  404s; sitemap emits 400 `/pt/` URLs; the explorer renders `/pt/`-prefixed links under the pt locale.
  **`tests/Feature/ContentLocaleTest.php`** (6 tests: canonical/unprefixed en, localized pt render,
  English fallback, bad-locale 404, reindex creates a pt row, `availableLocales`). 29 tests pass; Pint
  clean; Vite build green. **Note:** the controller reads route params **by name** (not positional
  method args) so the extra leading `{locale}` segment never shifts `type`/`category`/`slug`.
  **Out of scope (later, Phase C):** authoring/editing translations in the TipTap editor + creator
  (a locale target, `CommitArticle` writing `pt/...` paths, a per-locale review queue).

## Design directives (2026-06-13, from user)
- **Homepage = exploration surface ("universe" style)**, not legacy entrypoints: show ALL
  categories/tags/vehicle types/chassis/engines as an easy-to-grasp map; **recent articles
  first**. Re-weight toward the logged-in user's favorites + garage.
- **Content-shifting search, not autocomplete:** the search box re-renders/filters the page
  in place (Livewire) as the query changes - whole-page faceted shift.
- **Context-aware search scope** via one `ScopedSearch` Livewire component: Home=everything;
  Category=this-category|everything; Article=find-in-article; entity pages=that entity.
- **Mobile-first everywhere** - primary user is a hobbyist on a phone in the garage/on the
  road. Build P2/P3 explorer + search phone-first.
- → Captured in the plan under "Discovery, exploration & context-aware search". Interim
  landing stays until the catalog/articles exist (P1/P3) to populate the explorer.

## Repo reconciliation (2026-06-13) - reuse EXISTING org repos
- **Site code** = `hondabase/hondabase.com` (currently an old **Jekyll** site; the Laravel
  app in `/var/www/hondabase/www` will replace it). Do NOT create `hondabase/site`.
- **Articles** = `hondabase/articles` (renamed from `article-staging` on 2026-06-13;
  EXISTING, spec-structured `cars|motorcycles|aircraft|common/<category>/<slug>/<slug>.md`
  + co-located assets). Canonical content repo; cloned to `content/`.
- **Existing article convention (authoritative):** category = folder path; title = first H1;
  existing articles have **no frontmatter**, so frontmatter is **optional/additive**, never
  required. Linter validates frontmatter only when present, plus structural rules
  (slug == folder, assets exist, known category). Simplifies the plan's frontmatter model.
- `cars/electronics/` already holds `Honda-Acura-Trouble-Codes` and
  `how-to-check-obd1-ecu-codes` (trouble-code content already exists as articles).
- A `smart-case-formatter` GitHub Action lowercases folder/file names on push.
- Owner is org **admin** (VIRUXE), so deploy-key / repo creation is available.

## Documentation locations (decided 2026-06-13)
- **App / maintainer docs** -> site repo `hondabase.com` under `docs/` (this file,
  STYLE_GUIDE, architecture); `AGENTS.md` at repo root. Describes the code.
- **Content / authoring docs** -> articles repo `hondabase/articles`: `docs/article-format.md`
  (the article format + style standard) + root `CONTRIBUTING.md`, beside `README.md` and
  `markdown-cheat-sheet.md`. Travels with the content and forks.
- Articles are being **reformatted to `docs/article-format.md`** (adapted to the site, not
  legacy wiki semantics): drop auto-link soup, add lead + sections, normalize ECU/VTEC
  casing, relative cross-links, callouts, captioned images. `knock-sensor` done as the
  worked example.

## Key implementation notes
- **Forkability invariant:** content-derived tables rebuild via `hondabase:reindex`; no PII
  in any public repo. Nightly `mysqldump` (minus analytics + transient) → **private**
  `hondabase/backups`.
- **Attribution:** commits authored as bot; editor via `Co-Authored-By:` (real GitHub
  no-reply email if linked, else synthetic) + `Reviewed-By:` approver; pushed via deploy key.
- **Security model:** all article edits are approval-gated (draft→review→publish).
- **Styling:** the established pgmfi look is now **fully migrated to Tailwind v4** (CSS-first
  `@theme` tokens) in a single Vite bundle `resources/css/app.css` - base/components layers on
  canonical `--color-*`/`--font-*` tokens, the old hand-written sheets (`hondabase`/`article`/
  `explorer`/`me`/`editor`.css) all ported in and deleted, no per-page `<link>`s, no var aliases.
  Visual design preserved, not redesigned.

## Changelog
- **2026-06-15** - **P8 content i18n.** Article bodies now serve per locale: separate `content/pt/...`
  tree (non-`.md` assets fall back to the en bundle), unprefixed+canonical English with prefixed
  `/pt/...` mirror routes (constrained so they never shadow a content type), `App\Support\Locales`
  helper, `SetLocale` URL-segment precedence, locale-aware `ArticleService` + `Explorer` overlay/search,
  a `locale` index column (en=496/pt=400 rows via `reindex`; facets stay on the English identity),
  per-locale canonical + `hreflang`/`x-default`, a per-article language switcher, and an English-fallback
  notice. Controller reads route params **by name** so the leading `{locale}` segment never shifts
  `type`/`category`/`slug`. Verified over HTTP + 6 new `ContentLocaleTest` cases (29 tests pass, Pint
  clean, build green). Out of scope (later, Phase C): authoring translations in the TipTap editor.
- **2026-06-15** - **P8 member-facing UI i18n.** Extended `__()`/`trans_choice` coverage to the
  Livewire views (explorer, dashboard, garage, TipTap editor + creator, favorite-button,
  notification-bell) with placeholder/plural awareness; admin (`/admin/*`) views intentionally stay
  English (staff tooling). `lang/pt.json` grew to ~150 entries (19 tests pass, Pint clean, build green).
- **2026-06-15** - **P8 i18n foundation.** Decided scope (UI + content; first locale European
  Portuguese `pt`/`pt-PT` alongside English). Built the locale plumbing: `locales` config map,
  `SetLocale` middleware (cookie → Accept-Language → default, web group), `LocaleController` +
  `/locale/{locale}` switcher cookie, dynamic `<html lang>`, `__()`-wrapped layout chrome + footer
  language switcher, and the `lang/pt.json` catalog. Verified over HTTP + 5 new `LocaleTest` cases
  (19 tests pass, Pint clean, build green). Next: `__()` the remaining Livewire views, then
  content i18n (locale-prefixed routes, per-locale Markdown, hreflang, English fallback).
- **2026-06-15** - **P7 closed: Pint + CI + docs.** Adopted Laravel Pint (`pint.json`, laravel
  preset, legacy `public/`/`scratch/`/`tools/`/`bin/` excluded), formatted the app source (93 files
  clean, 14 tests still green). Added GitHub Actions CI (`.github/workflows/ci.yml`): PHP tests
  (SQLite), `pint --test`, and a pnpm Vite build, on push-to-main + PRs with caching. Wrote
  `docs/STYLE_GUIDE.md` (@theme tokens, one-bundle Tailwind v4, hybrid class/utility rule) and root
  `AGENTS.md`. Fixed a storage-ownership glitch that was failing the new carousel tests (env, not code).
- **2026-06-14** - **P7 Tailwind CSS migration complete.** Ported the last three legacy sheets
  (`article`/`explorer`/`me`.css) into `app.css` `@layer components` on the canonical `@theme`
  tokens (ordered short-var → `--color-*` rename, `[x-cloak]` deduped into `@layer base`), deleted
  `resources/css/legacy/`, and removed the transitional var-alias block - `app.css` is now a single
  token-native sheet (no legacy `@import`s, no short aliases; grep-verified). One bundle site-wide
  (85.5 kB / 17.6 kB gz). Build green, www-data restored; HTTP verified (home/category/article 200,
  `/me` 302) and all ported classes present in the built bundle. Remaining P7: STYLE_GUIDE, AGENTS, CI.
- **2026-06-14** - **P7 TipTap editor built + wired in; editor.css converted.** Replaced the
  raw-Markdown textarea in `ArticleEditor` + `ArticleCreator` with a TipTap rich-text canvas
  (code-split `resources/js/editor.js`, loaded only on `/new`+`/edit`; Alpine `tiptapEditor` on
  `alpine:init`, `wire:ignore` mount, debounced Markdown push to `$bodyMarkdown`, `save()` flush
  before submit) and **structured frontmatter fields** (new `App\Support\ArticleDocument` split/
  recompose + `App\Livewire\Concerns\EditsFrontmatter` trait: summary/complexity/tags/applies_to
  rows/source cards, unmodeled keys preserved verbatim; edit-reason field renamed `summary`→`note`).
  Recomposes canonical house-style YAML + Markdown so commits stay plain Markdown. Ported
  `editor.css` into `app.css` `@layer components` on `@theme` tokens (+ TipTap canvas/toolbar styles),
  dropped the `@import` and deleted the file. **Three harnesses green over 503 articles** (TipTap
  98.8% idempotent; `frontmatter-roundtrip.php` + `editor-roundtrip.php` both 100% idempotent + 100%
  data-preserved). Verified via Livewire test (mount/hydrate/edit→correct `proposed_body`, rolled
  back) + HTTP (article/home 200, editor routes 302 anon); build green; www-data ownership restored.
  Accepted one-time cost: first edit of each not-yet-normalized article reflows body+frontmatter
  (idempotent after).
  **Tailwind CSS migration COMPLETE (2026-06-14):** the last three legacy sheets
  (`article`/`explorer`/`me`.css) were ported into `app.css` `@layer components` on the canonical
  `--color-*`/`--font-*` `@theme` tokens (ordered token rename, the duplicate `[x-cloak]` rule
  deduped into `@layer base`), the `resources/css/legacy/` dir deleted, and the **transitional
  short-var-alias block removed** - so `app.css` is now a single, fully token-native sheet with
  **zero legacy `@import`s and zero short var aliases** (grep-verified). One bundle ships site-wide
  (85.5 kB → 17.6 kB gz). Build green; www-data ownership restored. Verified over HTTP (home +
  category + article 200, `/me` 302 anon) and that every ported class (`prose-article`, `ex-card`,
  `garage-card`, `fav-btn`, `markdown-alert`, `chip-follow`, `find-panel`) is present in the built
  bundle and the homepage references the new hashed `app-*.css`. (Merging the two sheets surfaced a
  real `.chip-follow` name collision - explorer's follow-star vs the dashboard's removable chip both
  used it - since they used to live in separate per-page sheets; the dashboard variant was renamed
  `.chip-followed`.)
  **Pint + CI + docs DONE (2026-06-15):** adopted **Laravel Pint** (`pint.json`, laravel preset,
  excluding legacy `public/`/`scratch/`/`tools/`/`bin/`) and ran it across the app source - 93 files
  now style-clean; tests still green (14 passed). Added **GitHub Actions CI** (`.github/workflows/ci.yml`):
  three jobs - PHP tests (in-memory SQLite, no MariaDB needed), `pint --test`, and a **pnpm Vite build**
  (catches CSS/JS regressions) - on push to `main` + all PRs, with composer/pnpm caching + concurrency
  cancel. Wrote **`docs/STYLE_GUIDE.md`** (the `@theme` token table, one-bundle Tailwind v4 setup,
  base/components layers + the hybrid semantic-class/utility rule, `ed-`/`ex-`/`rev-`/`staff-` naming,
  mobile-first) and root **`AGENTS.md`** (constraints, layout, conventions, run/verify commands). **P7
  complete.**
- **2026-06-14** - **P5 core personalization** (chosen slice; web-push deferred). Instance-local
  `favorites`/`user_vehicles`/`user_equipment` tables + models; `FavoriteButton` (article Save),
  `Garage` CRUD at `/me/garage` (vehicle save seeds engine/chassis follows), and the
  `Dashboard` "My Hondabase" at `/me` (onboarding when empty; else garage + Following feed +
  removable follow chips + saved list). Nav link + `me.css` + `article.css` Save styles.
  Verified end-to-end (Livewire test user create→follow-seed→favorite→cleanup) and over HTTP
  (`/me` 302 anon, article 200 with Save). Deferred: catalog-entity favorites (follows cover it),
  notifications + web-push (needs VAPID).
- **2026-06-14** - **closed the remaining P2/P3/P4 partials.** **P2 done:** `{{> partial }}`
  includes (`content/_partials/`, recursion-guarded, unknown left verbatim), internal
  cross-linking (relative `.md` links → clean article routes; absolute links pass through),
  and the article-scope `ScopedSearch` = a sticky mobile-first **find-in-page** (Alpine on
  `alpine:init`, survives `wire:navigate`). **P3 done:** with category-scoped search +
  sitemap already live, find-in-page completes the search scopes. **P4:** **new-article
  creation + image upload** (`/new` → `ArticleCreator`; raw-markdown + live preview, co-located
  image uploads staged to `storage/app/pending-assets/{id}`, new `article_revisions.assets`
  JSON column, `CommitArticle` writes the `.md` + images in one path-limited commit via
  `git add -- <paths>`) and an **owner-only staff-management UI** (`manage-staff` gate,
  `/admin/staff`). **P6:** confirmed the nightly `hondabase:dump` is scheduled at 00:00.
  All verified in an isolated content repo + over HTTP; no test data leaked to the live DB;
  www-data ownership restored on all edited files. Remaining deferrals: TipTap (P7, needs the
  Vite build), a running queue worker (ops), GA4 custom-dimension registration (GA admin).
- **2026-06-14** - P0 **complete**: cross-stack SSO identity cookie. `www` issues a signed,
  unencrypted `.hondabase.com` `hb_identity` cookie on login and clears it on logout; the
  `files/` app verifies the HMAC, hydrates its session, and clears the cookie on its own
  logout (SSO-wide logout). **www is now the sole IdP:** files' `auth_login()` redirects to
  www (`/auth/login?return=<files URL>`, open-redirect-guarded to `*.hondabase.com`) and www
  bounces back after login, so guild gating runs once at the IdP. Shared
  `HONDABASE_SSO_SECRET`/`SSO_SECRET`. Verified end-to-end (anon vs www-issued cookie; files
  login 302s to www with the return URL) and that tampered/expired tokens + unsafe returns are
  rejected. Restored www-data ownership on all edited files (root-owned edits had 500'd the
  files app).
- **2026-06-13** - P0: env verified; Laravel 13.15 scaffolded & merged (legacy intact);
  `hondabase` DB + `.env` + migrations; ownership fixed; Livewire+Socialite installed.
  **Homepage cut over to the new Laravel site** (pgmfi-styled), legacy subpaths verified
  live, APP_DEBUG off. Recorded universe-explorer / content-shifting-search / mobile-first
  directives into the plan.
- **2026-06-13** - P1/P2: first article rendering live (article + category pages,
  co-located images). Frontmatter "Applies to" panel made **fully data-driven** so it serves
  the whole Honda + Acura catalog (any `applies_to` field renders; OBD + engine families get
  special badges). Defined `docs/article-format.md` + `CONTRIBUTING.md` in the articles repo.
  Reformatted the 16 ported electronics articles to the standard (de-linked, casing fixed,
  flexible frontmatter) and pushed to branch `import/wiki-electronics`. Thin stubs
  (ignition-coil, hi/low-impedance-injectors) flagged for content.
- **2026-06-13** - P1: Implemented lenient frontmatter and structure linter command `app:lint-articles`. Linted and merged the original `import/wiki-electronics` branch (17 articles) to `main`. Ported, reformatted, and merged a second batch of 7 highly relevant electronics articles (OBD1 Auto/Manual, Troubleshooting Solid CEL, MAP Sensor, TPS Sensor, Oxygen Sensor, VTEC Solenoid, Vehicle Speed Sensor). All 26 articles fully passing lint checks and pushed to origin/main.
- **2026-06-13** - P1: Analyzed all 513 legacy wiki topics to create a comprehensive `WIKI_PORTING_PLAN.md` mapping them into categories and priority queues. Ported, cleaned, and merged a third batch of 5 high-priority articles, including a custom table parser to cleanly convert the massive `ecu-definition-codes` table.
- **2026-06-14** - P4 core: built the **edit -> review -> attributed-commit** pipeline.
  New `article_revisions` table + `ArticleRevision` (LCS `diff()` + `compactDiff()`);
  `ArticleService` refactor (shared `renderBody()`; new `rawMarkdown()`/`preview()`/
  `headSha()`); **`ArticleEditor`** Livewire (raw-markdown editor, live same-pipeline preview,
  mobile-first Edit/Preview tabs) at `/edit/...`; **`RevisionReview`** owner-only queue at
  `/admin/reviews` (`review-articles` Gate); **`CommitArticle`** queued job (path-limited bot
  commit + `Co-Authored-By`/`Reviewed-By`, reindex, opt-in push, unpushed count);
  `User::gitIdentity()`/`displayName()`; `config('hondabase.git')`; `editor.css` + flash.
  Article footer now shows an auth-aware **Edit this article** CTA. **Verified end-to-end** in
  an isolated git repo (linked vs unlinked attribution, path-limited commit, unpushed=1) and
  over HTTP (auth/owner gates). Side effect: the verification `hondabase:reindex` synced the
  **stale** live index (74) up to the current on-disk article count (**495**, all currently
  under `cars/electronics/`); the index is derived/rebuildable so this is a correct sync, not a
  data change. Editor is markdown+preview, **not** TipTap (deferred to P7 with the Vite build).
- **2026-06-14** - P4 staff + reversibility: added a **staff role** (`users.is_staff`,
  `User::isStaff()`, `hondabase:staff` command) and renamed the gate to **`manage-articles`**.
  Staff manage articles: they review members' edits AND their own edits **auto-apply** (self
  approved + committed, server-side re-checked against client tampering). Added full
  **change-tracking + reversibility**: `ArticleHistory` page (global + per-article) over all
  revisions, and a **Revert** that restores a prior snapshot as a new tracked commit
  (`reverts_revision_id`, revert-aware commit subject/trailer) so nothing is ever rewritten and
  reverts are themselves revertible. Verified end-to-end (member pending vs staff auto-apply,
  revert restores + links, member action calls cause zero side effects).
- **2026-06-14** - P4 conflict-safety + incremental reindex: the commit job now reindexes only
  the touched article (`ArticleIndexer::indexOne`) instead of rebuilding the whole index, and
  detects **edit conflicts** at apply time. If the on-disk file no longer matches the edit's
  base, the revision is parked as **`conflicted`** (new status) with nothing committed or
  overwritten; the review queue surfaces conflicts with a **Re-base & commit** action
  (`RevisionReview::rebase()`) or reject, and the history view badges them. Verified in an
  isolated repo: stale-base edit parks untouched, clean edit commits, re-based conflict commits.

- **2026-06-15** - Added Markdown-backed searchable ECU wirelists: shared parser/renderer,
  mobile-first Alpine filtering, and a structured TipTap row editor that keeps wirelist data
  inside approval-gated, diffable, revertible article revisions. Reformatted the OBD1 ECU
  chipping wirelist into clear instructions plus 96 preserved connections. Added the
  report-only `hondabase:audit-presentation` queue across all configured locales.
- **2026-06-15** - Improved wirelist readability by rendering arrow-delimited connection paths
  as visual signal chains: vertical traces on phones and wrapping horizontal traces on wider
  screens, with component/pin names and parenthesized signals separated for faster scanning.
