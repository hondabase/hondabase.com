# Analytics (Google Analytics 4)

Hondabase uses **Google Analytics 4** for usage analytics. It is deliberately
**article-aware**: an article view reports the article's full classification, not just a
URL, so content performance can be analysed by category, engine family, OBD generation,
and so on.

## Setup

- Property: **G-63JRK5RNJM** (shared with `files.hondabase.com`).
- Configured by env: `GA_MEASUREMENT_ID` (in `.env`), exposed via
  `config('hondabase.ga_id')`.
- **Optional / forkable:** if `GA_MEASUREMENT_ID` is unset, no Google scripts load at all.

## How it loads

- `resources/views/layouts/app.blade.php` injects the gtag loader and `gtag('config', ...)`
  only when `ga_id` is set, then loads `public/assets/ga.js`.
- `public/assets/ga.js` adds the article-aware and SPA behaviour on top of gtag.

## Events

| Event | When | Parameters |
| ----- | ---- | ---------- |
| `page_view` | initial load (auto via gtag config) and on every `wire:navigate` SPA navigation | `page_location`, `page_title` |
| `article_view` | an article page is shown (load or SPA navigation) | `article`, `category`, `vehicle_type`, `complexity`, `obd`, `engine`, `tags` |
| `search` | explorer search box, debounced 800 ms, query >= 2 chars | `search_term` |
| `facet_select` | a facet chip is clicked in the explorer | `facet` |

The `article_view` parameters come from `data-ga-*` attributes on the `<article>` element,
set in `resources/views/article.blade.php` from the article's frontmatter (`applies_to`,
`tags`, `complexity`, etc.).

## Register custom dimensions (required to report on article params)

GA4 collects the custom parameters automatically, but to use them in reports you must
register each as a **custom dimension** once, in **GA4 Admin -> Custom definitions ->
Create custom dimension** (scope: Event):

`article`, `category`, `vehicle_type`, `complexity`, `obd`, `engine`, `tags`,
`search_term`, `facet`.

Then you can build reports such as "views by engine family" or "OBD1 vs OBD2 engagement".

## Adding a new event or parameter

1. Send it from `public/assets/ga.js` (or from a view) with `gtag('event', 'name', {...})`.
2. For article context, add a `data-ga-*` attribute in `article.blade.php` and read it in
   `ga.js`.
3. Register any new parameter as a custom dimension in the GA4 admin.

## Files

- `config/hondabase.php` - `ga_id`
- `resources/views/layouts/app.blade.php` - gtag loader (env-gated)
- `public/assets/ga.js` - SPA page views + article/search/facet events
- `resources/views/article.blade.php` - `data-ga-*` attributes
