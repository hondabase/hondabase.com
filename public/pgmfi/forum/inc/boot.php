<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $cfg = require __DIR__ . '/config.php';
        $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function fmt_date(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? gmdate('d M Y, H:i', $ts) : '—';
}

function time_tag(?string $dt): string
{
    if (!$dt || !($ts = strtotime($dt))) {
        return '<span>—</span>';
    }
    return '<time datetime="' . gmdate('c', $ts) . '">' . gmdate('d M Y, H:i', $ts) . '</time>';
}

function fmt_capture(?string $ts): string
{
    if (!$ts || strlen($ts) < 8) {
        return '';
    }
    return substr($ts, 0, 4) . '-' . substr($ts, 4, 2) . '-' . substr($ts, 6, 2);
}

function render_body(string $html): string
{
    // Point smilie references at the locally recovered icon set; icons we
    // could not recover keep their original URL and fall back to the
    // missing-image placeholder.
    static $smilies = null;
    if ($smilies === null) {
        $smilies = [];
        foreach (glob(__DIR__ . '/../assets/smilies/*.gif') ?: [] as $f) {
            $smilies[basename($f)] = true;
        }
    }
    $html = preg_replace_callback(
        '#https?://forum\.pgmfi\.org(?::80)?/images/smilies/([a-zA-Z0-9_.-]+\.gif)#',
        fn($m) => isset($smilies[$m[1]]) ? '/pgmfi/forum/assets/smilies/' . $m[1] : $m[0],
        $html
    );

    // Rewrite references to any recovered media (images/attachments mirrored
    // from the Internet Archive) to the locally served, content-addressed
    // copy. Keyed on the exact in-post reference and the canonical URL.
    static $media = null;
    if ($media === null) {
        $media = [];
        $rows = db()->query(
            "SELECT pm.original_reference AS ref, m.original_url AS url, m.local_path
             FROM media m
             LEFT JOIN post_media pm ON pm.media_id = m.media_id
             WHERE m.state = 'complete' AND m.local_path IS NOT NULL"
        );
        foreach ($rows as $r) {
            $local = '/pgmfi/forum/media/' . $r['local_path'];
            if (!empty($r['ref'])) {
                $media[$r['ref']] = $local;
            }
            if (!empty($r['url'])) {
                $media[$r['url']] = $local;
            }
        }
    }
    if ($media) {
        $html = strtr($html, $media);
    }
    return $html;
}

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    return [$page, $pages, ($page - 1) * $perPage];
}

function page_links(string $base, int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $sep = str_contains($base, '?') ? '&' : '?';
    $out = '<nav class="pager" aria-label="Pagination"><span>PAGE</span>';
    foreach (range(1, $pages) as $p) {
        if ($pages > 9 && $p > 2 && $p < $pages - 1 && abs($p - $page) > 1) {
            if ($p === 3 || $p === $pages - 2) {
                $out .= '<i>··</i>';
            }
            continue;
        }
        $out .= $p === $page
            ? '<strong aria-current="page">' . $p . '</strong>'
            : '<a href="' . h($base . $sep . 'page=' . $p) . '">' . $p . '</a>';
    }
    $out .= '</nav>';
    return $out;
}

function layout_top(string $title, array $crumbs = [], string $live = 'mode=site'): void
{
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> · pgmfi.org archive</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=IBM+Plex+Mono:ital,wght@0,400;0,500;0,600;1,400&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style type="text/tailwindcss">
  :root {
    --red: #e10600;
    --amber: #fbbf24;
    --font-display: "Chakra Petch", sans-serif;
    --font-mono: "IBM Plex Mono", monospace;
  }

  /* ---------- base ---------- */
  html { @apply bg-zinc-950; }
  body {
    font-family: "IBM Plex Sans", sans-serif;
    @apply relative text-zinc-300 antialiased min-h-screen;
  }
  body::before {
    content: '';
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background-image: repeating-linear-gradient(0deg, rgba(255,255,255,.022) 0 1px, transparent 1px 3px);
  }
  body::after {
    content: ''; position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background:
      radial-gradient(1100px 500px at 85% -10%, rgba(225,6,0,.07), transparent 60%),
      radial-gradient(900px 420px at -10% 0%, rgba(251,191,36,.05), transparent 55%);
  }
  body > div { @apply relative z-10 mx-auto max-w-6xl px-4 pb-16; }

  /* ---------- header ---------- */
  .site-header { @apply pt-8 pb-6; }
  .site-header > div { @apply flex flex-col gap-4 border-b-2 border-zinc-800 pb-4; }
  @media (min-width: 640px) {
    .site-header > div { flex-direction: row; align-items: flex-end; justify-content: space-between; }
  }
  .site-header .brand { @apply block; }
  .site-header .brand small {
    font-family: var(--font-mono);
    @apply block text-[11px] tracking-[.25em] uppercase not-italic;
    color: var(--red);
  }
  .site-header .brand h1 {
    font-family: var(--font-display);
    @apply text-3xl font-bold uppercase tracking-wide text-zinc-100 transition-colors;
  }
  .site-header .brand:hover h1 { color: #fcd34d; }
  .site-header .brand h1 b { color: var(--red); }
  .site-header .brand p { font-family: var(--font-mono); @apply text-xs text-zinc-500 mt-1; }
  @media (min-width: 640px) { .site-header .brand h1 { @apply text-4xl; } }

  .site-header form { @apply relative w-full; }
  @media (min-width: 640px) { .site-header form { width: auto; } }
  .site-header form > .field { @apply flex border border-zinc-700 bg-zinc-900/80; }
  .site-header form > .field:focus-within { border-color: rgba(251,191,36,.6); }
  .site-header input {
    font-family: var(--font-mono);
    @apply bg-transparent px-3 py-2 text-sm text-zinc-200 placeholder-zinc-600 outline-none flex-1 min-w-0;
  }
  .site-header button { font-family: var(--font-mono); @apply px-4 text-sm; color: var(--amber); }
  .site-header button:hover { @apply bg-zinc-800; }
  @media (min-width: 640px) {
    .site-header input { flex: none; width: 12rem; @apply py-1.5 text-xs; }
    .site-header button { @apply px-3 text-xs; }
  }
  input[type="search"]::-webkit-search-cancel-button {
    -webkit-appearance: searchfield-cancel-button;
    filter: invert(.7) sepia(1) saturate(3) hue-rotate(5deg);
  }

  /* ---------- breadcrumbs ---------- */
  .crumbs { font-family: var(--font-mono); @apply mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-zinc-500; }
  .crumbs > b { @apply flex items-center gap-2 font-normal text-zinc-400; }
  .crumbs > b::before { content: ''; @apply inline-block h-1.5 w-1.5 bg-amber-400 animate-pulse; }
  .crumbs ol { @apply flex flex-wrap items-center gap-x-2 gap-y-1; list-style: none; }
  .crumbs li { @apply flex items-center gap-x-2; }
  .crumbs li + li::before { content: '/'; @apply text-zinc-700; }
  .crumbs ol::before { content: '/'; @apply text-zinc-700; }
  .crumbs a { color: rgba(251,191,36,.9); }
  .crumbs a:hover { color: #fcd34d; }
  .crumbs li:last-child > span { @apply text-zinc-300; }
  #lv-dot { @apply ml-auto items-center gap-1.5 text-[10px] uppercase tracking-wider text-emerald-400; display: inline-flex; }
  #lv-dot[hidden] { display: none; }
  #lv-dot::before { content: ''; @apply inline-block h-1.5 w-1.5 rounded-full bg-emerald-400; }

  /* ---------- page furniture ---------- */
  .page-head { @apply flex items-end justify-between gap-4 mb-3; }
  .page-head h2 { font-family: var(--font-display); @apply text-xl font-bold uppercase tracking-wide text-zinc-100 leading-snug; }
  .page-head p { @apply text-xs text-zinc-500; }
  .page-head .row-meta { @apply mt-1; }

  .pager { font-family: var(--font-mono); @apply flex items-center gap-1 text-xs; }
  .pager span { @apply text-zinc-600 mr-2; }
  .pager i { @apply px-1 text-zinc-700 not-italic; }
  .pager a { color: rgba(251,191,36,.8); @apply px-2 py-1; }
  .pager a:hover { @apply bg-zinc-800; color: #fcd34d; }
  .pager strong { @apply px-2 py-1 bg-amber-400 text-zinc-950 font-bold; }
  main > .pager, main > nav[data-live-region] { @apply mt-3 flex justify-end; }

  /* ---------- panels & rows ---------- */
  section { @apply mb-8; }
  section > h2 {
    font-family: var(--font-display);
    @apply uppercase tracking-widest text-sm text-zinc-100 bg-zinc-900 px-3 py-2;
    border-left: 4px solid var(--red);
  }
  .panel { @apply border border-zinc-800 divide-y divide-zinc-800/80; list-style: none; }
  section > .panel { @apply border-t-0; }
  .panel li { @apply px-3 py-3 transition-colors; }
  .panel li:hover { background: rgba(24,24,27,.6); }
  .panel li.empty { font-family: var(--font-mono); @apply py-6 text-sm text-zinc-500; }
  @media (min-width: 640px) {
    .panel li { display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
    .panel li > :first-child { min-width: 0; flex: 1; }
  }

  .row-title { @apply block text-sm leading-snug py-0.5 -my-0.5; color: #fcd34d; }
  .row-title:hover { color: #fde68a; }
  .row-desc { @apply text-xs text-zinc-500 mt-0.5; }
  .row-meta { font-family: var(--font-mono); @apply text-[11px] text-zinc-500 mt-1; }
  .row-meta b { @apply text-zinc-300 font-normal; }
  .row-meta a { @apply text-zinc-400 underline decoration-dotted; }
  .row-meta a:hover { color: var(--amber); }
  @media (min-width: 640px) {
    .panel li > .row-meta { margin-top: 0; flex-shrink: 0; text-align: right; }
  }
  .panel .row-title { font-size: .875rem; }
  .panel h3 { font-weight: 400; }
  section .row-title { font-family: var(--font-display); @apply text-base font-semibold; }
  .tag {
    font-family: var(--font-mono);
    @apply mt-1 inline-block text-[10px] uppercase text-zinc-600 border border-zinc-700 px-1 not-italic;
  }

  /* ---------- posts ---------- */
  .post { @apply border border-zinc-800; background: rgba(24,24,27,.4); }
  .post + .post { @apply mt-4; }
  .post > header { @apply flex flex-wrap items-baseline justify-between gap-2 border-b border-zinc-800 bg-zinc-900 px-3 py-2; }
  .post > header h3 { font-family: var(--font-display); @apply font-semibold text-amber-300 text-base; }
  .post > header h3 small { font-family: var(--font-mono); @apply ml-2 text-[10px] uppercase tracking-wider text-zinc-500 font-normal; }
  .post > header p { font-family: var(--font-mono); @apply flex items-baseline gap-3 text-[11px] text-zinc-500; }
  .post > header p .capture { @apply text-zinc-600; }
  .post > h4 { font-family: var(--font-mono); @apply px-3 pt-2 text-xs text-zinc-400 font-normal; }
  .postbody { @apply px-3 py-3 text-zinc-300; font-size: .92rem; line-height: 1.65; overflow-wrap: anywhere; }
  .postbody a { color: var(--amber); text-decoration: underline; text-decoration-color: rgba(251,191,36,.4); }
  .postbody a:hover { color: #fcd34d; }
  .postbody img { max-width: 100%; border: 1px solid #3f3f46; }
  .postbody img[src^="/pgmfi/forum/assets/smilies/"] {
    border: 0; display: inline-block; vertical-align: text-bottom; image-rendering: pixelated;
  }
  .postbody img[src^="/pgmfi/forum/media/"] { @apply my-1; background: #0a0a0b; }
  .postbody blockquote, .postbody .quotecontent {
    border-left: 2px solid var(--red); background: rgba(255,255,255,.03);
    padding: .5rem .75rem; margin: .5rem 0; color: #a1a1aa; font-size: .9em;
  }
  /* phpBB emits "X wrote:" and the quote body as two sibling divs — render
     them as the cap and body of a single block. */
  .postbody .quotetitle {
    border-left: 2px solid var(--red); background: rgba(225,6,0,.08);
    padding: .3rem .75rem; margin: .5rem 0 0;
    font-family: var(--font-mono); font-size: .7em;
    text-transform: uppercase; letter-spacing: .06em; color: #d4d4d8;
  }
  .postbody .quotetitle + .quotecontent,
  .postbody .quotetitle + br + .quotecontent { margin-top: 0; }
  .postbody code, .postbody .codecontent, .postbody pre {
    font-family: var(--font-mono); background: #0a0a0b;
    border: 1px solid #27272a; padding: .1rem .35rem; font-size: .85em; color: #6ee7b7;
    display: inline-block; max-width: 100%; overflow-x: auto; vertical-align: bottom;
  }
  .img-missing {
    display: inline-flex; align-items: baseline; gap: .5rem; max-width: 100%;
    border: 1px dashed #52525b; background: rgba(255,255,255,.02);
    padding: .45rem .7rem; margin: .15rem 0;
    font-family: var(--font-mono); font-size: .72rem;
    color: #71717a; overflow-wrap: anywhere;
  }
  .img-missing::before { content: '◈'; color: var(--red); }
  .img-missing a { color: #a1a1aa; text-decoration: underline dotted; }
  .img-missing a:hover { color: var(--amber); }

  /* ---------- autocomplete ---------- */
  .ac-list {
    display: block;
    position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 50;
    background: #0c0c0e; border: 1px solid #3f3f46;
    box-shadow: 0 12px 32px rgba(0,0,0,.6); max-height: 21rem; overflow-y: auto;
  }
  .ac-list[hidden] { display: none; }
  .ac-item b { overflow-wrap: anywhere; }
  .ac-item { display: block; padding: .5rem .7rem; cursor: pointer; border-bottom: 1px solid rgba(63,63,70,.4); }
  .ac-item:last-child { border-bottom: 0; }
  .ac-item b { font-size: .8rem; color: #d4d4d8; display: block; font-weight: 400; }
  .ac-item small { font-family: var(--font-mono); font-size: .65rem; color: #71717a; }
  .ac-item.is-active, .ac-item:hover { background: rgba(251,191,36,.08); }
  .ac-item.is-active b, .ac-item:hover b { color: var(--amber); }
  .ac-item mark { background: transparent; color: var(--amber); }
  .ac-all {
    font-family: var(--font-mono); font-size: .68rem; color: #a1a1aa;
    padding: .45rem .7rem; display: block; cursor: pointer;
    border-top: 1px solid #3f3f46; background: rgba(255,255,255,.02);
  }
  .ac-all.is-active, .ac-all:hover { background: rgba(251,191,36,.08); color: var(--amber); }

  /* ---------- live patch shimmer ---------- */
  @keyframes sk-sweep { from { background-position: -200% 0; } to { background-position: 200% 0; } }
  .sk-flash {
    background-image: linear-gradient(90deg, transparent 25%, rgba(251,191,36,.14) 50%, transparent 75%);
    background-size: 200% 100%;
    animation: sk-sweep 1s linear 2;
  }

  /* ---------- footer ---------- */
  body > div > footer {
    font-family: var(--font-mono);
    @apply mt-12 border-t border-zinc-800 pt-4 flex flex-wrap justify-between gap-2 text-[11px] text-zinc-600;
  }
  body > div > footer b { @apply text-zinc-400 font-normal; }

  /* ---------- dead & archived links ---------- */
  .dead-link, a.dead-link {
    color: #71717a !important;
    text-decoration: line-through !important;
    cursor: not-allowed;
  }
  .dead-link::after, a.dead-link::after {
    content: ' [broken]';
    font-size: .72em;
    text-decoration: none !important;
    display: inline-block;
    margin-left: 2px;
  }
  a[href^="https://web.archive.org/web/"] {
    text-decoration: underline dotted !important;
    text-decoration-color: var(--amber) !important;
  }
  a[href^="https://web.archive.org/web/"]::after {
    content: ' 🏛️';
    font-size: .8em;
    opacity: .7;
    display: inline-block;
  }
</style>
<script>
// Image load failures don't bubble, so catch them in the capture phase and
// swap the dead <img> for a placeholder that keeps the original reference.
document.addEventListener('error', function (e) {
  var img = e.target;
  if (!(img instanceof HTMLImageElement) || !img.closest('.postbody')) return;
  var url = img.getAttribute('src') || '';
  var name = url.split('/').pop().split('?')[0] || url;
  var box = document.createElement('span');
  box.className = 'img-missing';
  box.appendChild(document.createTextNode('image not recovered: '));
  var a = document.createElement('a');
  a.href = url;
  a.rel = 'nofollow noopener';
  a.textContent = name.length > 60 ? name.slice(0, 57) + '…' : name;
  box.appendChild(a);
  img.replaceWith(box);
}, true);

// Topic title autocomplete for every search box on the page.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('form[role="search"]').forEach(function (form) {
    var input = form.querySelector('input[name="q"]');
    if (!input) return;
    var list = document.createElement('div');
    list.className = 'ac-list';
    list.hidden = true;
    form.appendChild(list);

    var active = -1, ctl = null, timer = null;

    function close() { list.hidden = true; list.innerHTML = ''; active = -1; }

    function highlight(title, q) {
      var span = document.createElement('span');
      var words = q.trim().split(/\s+/).filter(Boolean)
        .map(function (w) { return w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); });
      if (!words.length) { span.textContent = title; return span; }
      var re = new RegExp('(' + words.join('|') + ')', 'ig');
      title.split(re).forEach(function (part) {
        if (re.test(part)) { var m = document.createElement('mark'); m.textContent = part; span.appendChild(m); }
        else span.appendChild(document.createTextNode(part));
        re.lastIndex = 0;
      });
      return span;
    }

    function setActive(i) {
      var rows = list.querySelectorAll('.ac-item, .ac-all');
      rows.forEach(function (r) { r.classList.remove('is-active'); });
      active = i;
      if (i >= 0 && rows[i]) {
        rows[i].classList.add('is-active');
        rows[i].scrollIntoView({ block: 'nearest' });
      }
    }

    function render(data, q) {
      list.innerHTML = '';
      if (!data.length) { close(); return; }
      data.forEach(function (t) {
        var a = document.createElement('a');
        a.className = 'ac-item';
        a.href = '/pgmfi/forum/topic.php?id=' + t.id;
        var ti = document.createElement('b');
        ti.appendChild(highlight(t.title, q));
        a.appendChild(ti);
        if (t.forum) {
          var f = document.createElement('small');
          f.textContent = 'in ' + t.forum;
          a.appendChild(f);
        }
        list.appendChild(a);
      });
      var all = document.createElement('a');
      all.className = 'ac-all';
      all.href = '/pgmfi/forum/search.php?q=' + encodeURIComponent(q);
      all.textContent = '>> all results for "' + q + '"';
      list.appendChild(all);
      list.hidden = false;
      active = -1;
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 2) { close(); return; }
      timer = setTimeout(function () {
        if (ctl) ctl.abort();
        ctl = new AbortController();
        fetch('/pgmfi/forum/suggest.php?q=' + encodeURIComponent(q), { signal: ctl.signal })
          .then(function (r) { return r.json(); })
          .then(function (data) { render(data, q); })
          .catch(function () {});
      }, 150);
    });

    input.addEventListener('keydown', function (e) {
      if (list.hidden) return;
      var rows = list.querySelectorAll('.ac-item, .ac-all');
      if (e.key === 'ArrowDown') { e.preventDefault(); setActive((active + 1) % rows.length); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); setActive((active - 1 + rows.length) % rows.length); }
      else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); rows[active].click(); }
      else if (e.key === 'Escape') { close(); }
    });

    document.addEventListener('click', function (e) {
      if (!form.contains(e.target)) close();
    });
  });
});

// Live updates: poll a cheap page signature; when recovery adds content that
// affects this page, re-fetch it and patch only the elements that changed.
document.addEventListener('DOMContentLoaded', function () {
  var main = document.getElementById('live-main');
  if (!main) return;
  var liveQuery = main.dataset.live || 'mode=site';
  var sig = null, busy = false;

  function fmtN(n) { return n.toLocaleString('en-US'); }

  function flashDot() {
    var dot = document.getElementById('lv-dot');
    if (!dot) return;
    dot.hidden = false;
    setTimeout(function () { dot.hidden = true; }, 4000);
  }

  // Patch a region: reuse every child whose markup is unchanged (matched by
  // data-key, or by position for unkeyed chrome) and only insert/replace the
  // elements that actually differ.
  function patchRegion(oldC, newC) {
    var oldKids = Array.prototype.slice.call(oldC.children);
    var byKey = {};
    oldKids.forEach(function (ch) {
      var k = ch.getAttribute('data-key');
      if (k) byKey[k] = ch;
    });
    var frag = document.createDocumentFragment();
    Array.prototype.forEach.call(newC.children, function (nch, i) {
      var k = nch.getAttribute('data-key');
      var match = k ? byKey[k]
        : (oldKids[i] && !oldKids[i].hasAttribute('data-key') ? oldKids[i] : null);
      if (match && match.outerHTML === nch.outerHTML) {
        frag.appendChild(match);
      } else {
        var node = document.importNode(nch, true);
        node.classList.add('sk-flash');
        setTimeout(function () { node.classList.remove('sk-flash'); }, 2100);
        frag.appendChild(node);
      }
    });
    oldC.replaceChildren(frag);
  }

  function refreshPage() {
    if (busy) return;
    busy = true;
    fetch(window.location.href, { headers: { 'X-Requested-With': 'live' } })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var changed = false;
        main.querySelectorAll('[data-live-region]').forEach(function (region) {
          var name = region.getAttribute('data-live-region');
          var fresh = doc.querySelector('[data-live-region="' + name + '"]');
          if (!fresh || region.innerHTML === fresh.innerHTML) return;
          patchRegion(region, fresh);
          changed = true;
        });
        if (changed) flashDot();
      })
      .catch(function () {})
      .finally(function () { busy = false; });
  }

  function tick() {
    if (document.visibilityState !== 'visible') return;
    var sel = window.getSelection();
    if (sel && sel.type === 'Range') return;
    fetch('/pgmfi/forum/live.php?' + liveQuery, { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        ['topics', 'posts', 'authors'].forEach(function (k) {
          var el = document.getElementById('lv-' + k);
          if (el && el.textContent !== fmtN(d[k])) el.textContent = fmtN(d[k]);
        });
        if (sig === null) { sig = d.sig; return; }
        if (d.sig !== sig) { sig = d.sig; refreshPage(); }
      })
      .catch(function () {});
  }

  tick();
  setInterval(tick, 30000);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') tick();
  });
});
</script>
</head>
<body>
<div>

  <header class="site-header">
    <div>
      <a href="/pgmfi/forum/" class="brand">
        <small>// recovered archive</small>
        <h1>pgmfi<b>.</b>org</h1>
        <p>honda / acura engine management · phpBB capture via web.archive.org</p>
      </a>
      <form action="/pgmfi/forum/search.php" method="get" role="search">
        <div class="field">
          <input type="search" name="q" value="<?= h($_GET['q'] ?? '') ?>" placeholder="search topics_"
                 enterkeyhint="search" autocomplete="off">
          <button aria-label="Search">&gt;&gt;</button>
        </div>
      </form>
    </div>

    <nav class="crumbs" aria-label="Breadcrumb">
      <b>READ-ONLY</b>
      <ol>
        <?php foreach ($crumbs as [$label, $href]) : ?>
          <li>
            <?php if ($href !== null) : ?>
              <a href="<?= h($href) ?>"><?= h($label) ?></a>
            <?php else : ?>
              <span><?= h($label) ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
      <span id="lv-dot" hidden>updated</span>
    </nav>
  </header>

  <main id="live-main" data-live="<?= h($live) ?>">
  <?php
}

function layout_bottom(): void
{
    $counts = db()->query(
        'SELECT (SELECT COUNT(*) FROM topics) t, (SELECT COUNT(*) FROM posts) p, (SELECT COUNT(*) FROM authors) a'
    )->fetch();
    ?>
  </main>

  <footer>
    <p>
      RECOVERED&nbsp;SO&nbsp;FAR:
      <b id="lv-topics"><?= number_format((int) $counts['t']) ?></b> topics ·
      <b id="lv-posts"><?= number_format((int) $counts['p']) ?></b> posts ·
      <b id="lv-authors"><?= number_format((int) $counts['a']) ?></b> members
    </p>
    <p>archive viewer · live · recovery in progress · content © original authors</p>
  </footer>
  <?php
  $deadUrls = db()->query(
      "SELECT original_url FROM media WHERE state IN ('missing', 'error')"
  )->fetchAll(PDO::FETCH_COLUMN);
  ?>
  <script>
  (function() {
      var deadUrls = <?php echo json_encode($deadUrls); ?>;
      var deadSet = new Set(deadUrls);
      document.querySelectorAll('.postbody a').forEach(function(a) {
          var href = a.getAttribute('href');
          if (href) {
              var cleanHref = href.trim().replace(/\/$/, "");
              var isDead = deadSet.has(href) || deadSet.has(cleanHref);
              if (isDead) {
                  a.classList.add('dead-link');
                  a.setAttribute('title', 'This link is broken (no archived snapshot found)');
                  a.addEventListener('click', function(e) { e.preventDefault(); });
              }
          }
      });
  })();
  </script>
</div>
</body>
</html>
    <?php
}
