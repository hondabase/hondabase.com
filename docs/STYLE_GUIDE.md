# Hondabase Style Guide

How the front end is styled and how to keep it consistent. The visual language is
the **pgmfi aesthetic**: dark, high-contrast, monospaced accents, Honda-red highlights,
a faint scanline texture. Mobile-first, always.

## One bundle, Tailwind v4

All CSS lives in **`resources/css/app.css`** and is compiled by Vite into a single
hashed bundle (`public/build/assets/app-*.css`) loaded site-wide via `@vite` in the
layout. There are **no per-page `<link>` tags** and **no hand-written stylesheets in
`public/`** anymore (the old `hondabase`/`article`/`explorer`/`me`/`editor`.css were all
folded in and deleted).

The editor route additionally loads a code-split `editor-*.js` (TipTap); readers never
download it. `resources/js/app.js` is intentionally empty.

Tailwind is **v4, CSS-first**: configured entirely in `app.css` with `@theme` and
`@import 'tailwindcss'` — there is **no `tailwind.config.js`**.

## Design tokens (`@theme`)

Tokens are declared once in the `@theme` block. In Tailwind v4 each token becomes
**both** a utility class **and** a `--color-*` / `--font-*` CSS custom property, so you
can use whichever fits:

```css
/* utility in Blade */            /* property in app.css component */
class="bg-bg text-amber"          background: var(--color-bg); color: var(--color-amber);
```

| Token                   | Value                  | Meaning                         |
| ----------------------- | ---------------------- | ------------------------------- |
| `--color-bg`            | `#0a0a0b`              | page background                 |
| `--color-bg-2`          | `#0c0c0e`              | raised surfaces, inputs         |
| `--color-panel`         | `rgba(24,24,27,.55)`   | cards / panels                  |
| `--color-panel-hover`   | `rgba(39,39,42,.6)`    | card hover                      |
| `--color-border`        | `#27272a`              | hairline borders                |
| `--color-border-2`      | `#3f3f46`              | stronger borders / inputs       |
| `--color-txt`           | `#e4e4e7`              | body text                       |
| `--color-dim`           | `#a1a1aa`              | secondary text                  |
| `--color-muted`         | `#71717a`              | captions, meta, kickers         |
| `--color-head`          | `#f4f4f5`              | headings                        |
| `--color-red`           | `#e10600`              | Honda red — primary accent      |
| `--color-amber`         | `#fbbf24`              | links, interactive accent       |
| `--color-amber-2` / `-3`| `#fcd34d` / `#fde68a`  | lighter amber steps             |
| `--color-green`         | `#6ee7b7`              | success / "ok" states           |
| `--font-display`        | Chakra Petch           | headings, brand, kickers        |
| `--font-sans`           | IBM Plex Sans          | body                            |
| `--font-mono`           | IBM Plex Mono          | meta, chips, code, labels       |

**Never hard-code these hex values in components or Blade** — reference the token. The
only raw colors that remain are one-off tints (e.g. `rgba(251,191,36,.5)` for an amber
glow, the blue/purple markdown-alert accents) where no token exists; prefer
`color-mix(in srgb, var(--color-amber) 50%, ...)` when you need a derived shade.

Web fonts are **self-hosted / system-fallback only** — no external font CDN, so the
build stays offline (forkability).

## Layers & the hybrid approach

`app.css` is organized into Tailwind layers:

- **`@layer base`** — element defaults (`body`, `a`, the scanline `body::before`,
  `[x-cloak]`).
- **`@layer components`** — the repeated pgmfi patterns as **semantic classes**
  (`.card`, `.chip`, `.btn`, `.prose-article`, `.ed-*`, `.ex-*`, `.rev-*`, `.staff-*`,
  …). These are the vocabulary the Blade templates speak.

The rule of thumb (**hybrid**):

- **Repeated pattern** (a card, a chip, a toolbar button) → give it a semantic class in
  `@layer components` so the markup stays readable and the look is defined once.
- **One-off layout** in a single template (a flex row, a gap, a max-width) → use Tailwind
  **utility classes** directly in the Blade.

Don't reach for utilities to rebuild something that already has a component class, and
don't add a component class for something used exactly once.

## Class naming

Component classes use short, area-prefixed kebab-case so the cascade is easy to scan:

- `ed-*` — the editor / creator (TipTap canvas, toolbar, structured-metadata fields)
- `ex-*` — the explorer / homepage (search, facets, result cards)
- `rev-*` — the review queue + history (diffs, state badges)
- `staff-*` — staff management
- `prose-article` — rendered article body (headings, tables, alerts, code)
- shared atoms: `.card`, `.chip`, `.badge`, `.btn`, `.flash`, `.crumbs`, `.facts`

## Mobile-first

Author the base styles for a phone, then widen with `min-width` media queries (or
Tailwind `sm:`/`md:` utilities). The primary user is a hobbyist on a phone in the garage.
Editors collapse to single-column Write/Preview tabs and only split into side-by-side
panes at `min-width: 880px`; the explorer facet rail goes sticky at `860px`. Keep tap
targets thumb-reachable.

## Adding styles

1. Need a new color/font? Add a token to `@theme` — don't inline a hex.
2. Reusable widget? Add a class under `@layer components` using `var(--color-*)`.
3. One-off spacing/layout? Utilities in the Blade.
4. Run `pnpm build` and confirm the page still renders; the class must appear in the
   built bundle (Tailwind only emits utilities it sees via the `@source` globs).

## Checks

- `pnpm build` — compiles the bundle; CI fails on any CSS/JS error.
- Tokens, layers, and the hybrid approach are enforced by review, not tooling — keep the
  table above the single source of truth for the palette.
