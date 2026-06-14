# Widgets

Widgets are small, interactive components that can be embedded inside an article (for
example the OBD trouble-code lookup). They are **trusted, code-reviewed components that live
in the site repo**, not arbitrary code from the content repo. Community-authored Markdown can
only *invoke* a whitelisted widget by name; it can never define or execute one. That keeps
approval-gated content safe while still allowing rich interactivity.

## Using a widget in an article (for authors)

Put a directive on its own line, surrounded by blank lines:

```
::: widget error-codes :::
```

With parameters (whitelisted `key="value"` pairs):

```
::: widget error-codes obd="1" :::
```

Rules:
- The directive must be on its own line.
- Unknown widget names are left as plain text (no error, nothing breaks), so a typo is
  visible rather than fatal.
- Parameters are optional and widget-specific.

## Available widgets

| Name          | What it does                                              | Parameters | Data source |
| ------------- | --------------------------------------------------------- | ---------- | ----------- |
| `error-codes` | Live keyword/number lookup of Honda OBD trouble codes.    | none yet   | `public/reference/error-codes/error-codes.json` |

(Embedded example: `cars/electronics/Honda-Acura-Trouble-Codes`.)

## How it works (architecture)

The flow lives in two classes plus a Blade view per widget.

1. **Directive resolution** in `app/Services/ArticleService::find()`:
   - A regex finds `^::: widget <name> <attrs> :::$` lines in the Markdown body.
   - Each is handed to `WidgetRenderer::render($name, $attrs)`. If it returns HTML, the
     directive is replaced with a short text token (`xWIDGETnx`); otherwise it is left as-is.
   - The Markdown is converted to HTML (with `html_input => 'escape'`, so nothing in the
     prose can inject markup).
   - The tokens (which CommonMark wrapped in `<p>...</p>`) are then swapped for the widget
     HTML. Because this happens *after* conversion, the widget's own markup is emitted
     verbatim and is not escaped.
2. **Rendering** in `app/Services/WidgetRenderer.php`:
   - `$allowed` is the whitelist of widget names.
   - `render()` resolves `resources/views/widgets/<name>.blade.php` and renders it with data
     from `data()` plus the parsed `attrs`.
   - `data()` supplies per-widget data (for `error-codes`, it reads and normalizes the
     trouble-code JSON).
3. **Interactivity**: widgets use **Alpine** (`x-data`, `x-model`, `x-for`, ...). Alpine is
   loaded once, self-hosted, from `public/assets/alpine.min.js` (referenced in
   `resources/views/layouts/app.blade.php`), so there is no runtime CDN dependency and the
   site stays forkable. Widget HTML is injected into the page, and Alpine initializes it on
   load.
4. **Styling**: widget CSS lives in `public/assets/article.css` under `.widget` and
   widget-specific classes, using the pgmfi design tokens.

Note: because a widget is rendered to a string and injected into the article HTML, its Blade
`@push`/`@stack` will **not** reach the page layout. Rely on the globally loaded Alpine and on
`article.css`; do not depend on per-widget `@push('scripts')`.

## Adding a new widget

1. Whitelist the name in `WidgetRenderer::$allowed`.
2. If it needs data, add a case in `WidgetRenderer::data()` returning an array for the view.
3. Create `resources/views/widgets/<name>.blade.php`. Keep it self-contained: Blade for
   markup, Alpine for behavior. Pass data into Alpine with `@js($data)`.
4. Style it in `public/assets/article.css` (reuse `.widget`; add a `.widget-<name>` class as
   needed). Keep it mobile-first.
5. Add a row to the **Available widgets** table above.
6. Embed it in an article with `::: widget <name> :::`.

Constraints to honor:
- No external runtime dependencies; self-host any JS/CSS.
- Whitelist params and treat them as untrusted strings.
- Widgets must be accessible (labels on inputs) and work on a phone.
- Heavier, server-driven widgets can use Livewire later; the current pattern (Alpine +
  injected HTML) is for self-contained, client-side interactivity that embeds anywhere.
