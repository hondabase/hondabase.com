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
        return '-';
    }
    $ts = strtotime($dt);
    return $ts ? gmdate('d M Y', $ts) : '-';
}

function time_tag(?string $dt): string
{
    if (!$dt || !($ts = strtotime($dt))) {
        return '<span>-</span>';
    }
    return '<time datetime="' . gmdate('c', $ts) . '">' . gmdate('d M Y', $ts) . '</time>';
}

function fmt_bytes(int $n): string
{
    if ($n >= 1048576) {
        return round($n / 1048576, 1) . ' MB';
    }
    if ($n >= 1024) {
        return (int) round($n / 1024) . ' KB';
    }
    return $n . ' B';
}

function attachment_icon(string $filename): string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $known = ['bmp', 'exe', 'pdf', 'zip'];
    return '/pgmfi/wiki/assets/icn/' . (in_array($ext, $known, true) ? $ext : 'else') . '.gif';
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
    $out = '<nav aria-label="Pagination"><span>PAGE</span>';
    foreach (range(1, $pages) as $p) {
        $out .= $p === $page
            ? '<strong aria-current="page">' . $p . '</strong>'
            : '<a href="' . h($base . $sep . 'page=' . $p) . '">' . $p . '</a>';
    }
    $out .= '</nav>';
    return $out;
}

function layout_top(string $title, array $crumbs = [], string $description = '', string $canonical = '', bool $noindex = false): void
{
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> · pgmfi.org wiki archive</title>
<?php if ($description !== '') : ?>
<meta name="description" content="<?= h($description) ?>">
<meta property="og:description" content="<?= h($description) ?>">
<?php endif; ?>
<?php if ($noindex) : ?>
<meta name="robots" content="noindex, follow">
<?php endif; ?>
<?php if ($canonical !== '') : ?>
<link rel="canonical" href="<?= h($canonical) ?>">
<meta property="og:url" content="<?= h($canonical) ?>">
<?php endif; ?>
<meta property="og:title" content="<?= h($title) ?>">
<meta property="og:type" content="article">
<meta property="og:site_name" content="HondaBase · pgmfi.org archive">
<meta name="twitter:card" content="summary">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=IBM+Plex+Mono:ital,wght@0,400;0,500;0,600;1,400&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --red: #e10600;
  --amber: #fbbf24;
  --amber-soft: rgba(251, 191, 36, .85);
  --bg: #09090b;
  --panel: rgba(24, 24, 27, .6);
  --border: #27272a;
  --border-soft: rgba(63, 63, 70, .6);
  --text: #d4d4d8;
  --muted: #71717a;
  --font-display: "Chakra Petch", sans-serif;
  --font-mono: "IBM Plex Mono", monospace;
}

* { box-sizing: border-box; }
html { background: var(--bg); }
body {
  font-family: "IBM Plex Sans", sans-serif;
  color: var(--text);
  margin: 0 auto;
  max-width: 72rem;
  min-height: 100vh;
  padding: 0 1rem 4rem;
  position: relative;
  -webkit-font-smoothing: antialiased;
}
body::before {
  content: '';
  position: fixed; inset: 0; z-index: -1; pointer-events: none;
  background-image: repeating-linear-gradient(0deg, rgba(255,255,255,.022) 0 1px, transparent 1px 3px);
}
body::after {
  content: ''; position: fixed; inset: 0; z-index: -1; pointer-events: none;
  background:
    radial-gradient(1100px 500px at 85% -10%, rgba(225,6,0,.07), transparent 60%),
    radial-gradient(900px 420px at -10% 0%, rgba(251,191,36,.05), transparent 55%);
}
a { color: var(--amber-soft); text-decoration: none; }
a:hover { color: #fcd34d; }

/* ---------- site header ---------- */
body > header { padding: 2rem 0 1.5rem; }
body > header > div {
  display: flex; flex-direction: column; gap: 1rem;
  border-bottom: 2px solid var(--border); padding-bottom: 1rem;
}
@media (min-width: 640px) {
  body > header > div { flex-direction: row; align-items: flex-end; justify-content: space-between; }
}
body > header div div p { margin: 0; }
body > header strong {
  font-family: var(--font-display);
  font-size: 2rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .05em; color: #f4f4f5;
}
body > header strong a { color: inherit; }
body > header strong a:hover { color: #fcd34d; }
body > header strong b { color: var(--red); }
body > header div div p + p { font-family: var(--font-mono); font-size: .75rem; color: var(--muted); margin-top: .25rem; }
body > header small {
  font-family: var(--font-mono); display: block;
  font-size: .69rem; letter-spacing: .25em; text-transform: uppercase; color: var(--red);
}

body > header form { position: relative; }
body > header form > p {
  display: flex; margin: 0;
  border: 1px solid var(--border-soft); background: rgba(24,24,27,.8);
}
body > header form > p:focus-within { border-color: rgba(251,191,36,.6); }
body > header input {
  font-family: var(--font-mono); font-size: .8rem;
  flex: 1; min-width: 0; width: 13rem;
  background: transparent; border: 0; outline: none;
  color: #e4e4e7; padding: .5rem .75rem;
}
body > header input::placeholder { color: #52525b; }
body > header button {
  font-family: var(--font-mono); font-size: .8rem;
  background: none; border: 0; cursor: pointer;
  color: var(--amber); padding: 0 .9rem;
}
body > header button:hover { background: #27272a; }
input[type="search"]::-webkit-search-cancel-button {
  -webkit-appearance: searchfield-cancel-button;
  filter: invert(.7) sepia(1) saturate(3) hue-rotate(5deg);
}

/* autocomplete menu (created by JS inside the search form) */
body > header form menu {
  list-style: none; margin: 0; padding: 0;
  position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 50;
  background: #0c0c0e; border: 1px solid #3f3f46;
  box-shadow: 0 12px 32px rgba(0,0,0,.6); max-height: 21rem; overflow-y: auto;
}
body > header form menu li + li { border-top: 1px solid rgba(63,63,70,.4); }
body > header form menu a { display: block; padding: .5rem .7rem; }
body > header form menu b { display: block; font-size: .8rem; font-weight: 400; color: #d4d4d8; overflow-wrap: anywhere; }
body > header form menu small { font-family: var(--font-mono); font-size: .65rem; color: var(--muted); letter-spacing: normal; text-transform: none; }
body > header form menu mark { background: transparent; color: var(--amber); }
body > header form menu li[aria-selected="true"] a,
body > header form menu a:hover { background: rgba(251,191,36,.08); }
body > header form menu li[aria-selected="true"] b,
body > header form menu a:hover b { color: var(--amber); }

body > header nav {
  font-family: var(--font-mono); font-size: .75rem; color: var(--muted);
  display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;
  margin-top: .75rem;
}
body > header nav > b {
  display: flex; align-items: center; gap: .5rem; font-weight: 400; color: #a1a1aa;
}
body > header nav > b::before {
  content: ''; display: inline-block; width: .375rem; height: .375rem;
  background: var(--amber); animation: pulse 2s infinite;
}
@keyframes pulse { 50% { opacity: .4; } }
body > header nav ol { display: flex; flex-wrap: wrap; gap: .5rem; list-style: none; margin: 0; padding: 0; }
body > header nav ol::before, body > header nav li + li::before { content: '/'; color: #3f3f46; }
body > header nav li { display: flex; gap: .5rem; }

/* ---------- page furniture ---------- */
main h1, main h2 {
  font-family: var(--font-display);
  font-size: 1.25rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .03em; color: #f4f4f5; margin: 0 0 .25rem;
}
main > article > header { margin-bottom: 1rem; }
main > article > header p { font-family: var(--font-mono); font-size: .7rem; color: var(--muted); margin: .35rem 0 0; }
main > article > header p b { color: #a1a1aa; font-weight: 400; }

main section { margin: 2rem 0; }
main section > h3 {
  font-family: var(--font-display); font-size: .85rem;
  text-transform: uppercase; letter-spacing: .15em; color: #f4f4f5;
  background: #18181b; border-left: 4px solid var(--red);
  padding: .5rem .75rem; margin: 0;
}
main section > ul, main section > ol {
  list-style: none; margin: 0; padding: 0;
  border: 1px solid var(--border); border-top: 0;
}
main section li { padding: .65rem .75rem; }
main section li + li { border-top: 1px solid rgba(39,39,42,.8); }
main section li:hover { background: var(--panel); }
main section li p { margin: .2rem 0 0; font-size: .8rem; color: var(--muted); }
main section li small { font-family: var(--font-mono); font-size: .68rem; color: var(--muted); }
main section li mark { background: transparent; color: var(--amber); }
@media (min-width: 640px) {
  main section li { display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
  main section li > :first-child { min-width: 0; flex: 1; }
  main section li > small { flex-shrink: 0; text-align: right; }
}
main section img { vertical-align: text-bottom; margin-right: .4rem; image-rendering: pixelated; }

main nav[aria-label="Pagination"] {
  font-family: var(--font-mono); font-size: .75rem;
  display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: .25rem; margin-top: .75rem;
}
main nav[aria-label="Pagination"] span { color: #52525b; margin-right: .5rem; }
main nav[aria-label="Pagination"] a { padding: .25rem .5rem; }
main nav[aria-label="Pagination"] a:hover { background: #27272a; }
main nav[aria-label="Pagination"] strong { padding: .25rem .5rem; background: var(--amber); color: var(--bg); }

/* ---------- imported wiki content ---------- */
main article > div {
  border: 1px solid var(--border); background: var(--panel);
  padding: 1rem 1.25rem; font-size: .92rem; line-height: 1.65;
  overflow-wrap: anywhere;
}
main article > div a { text-decoration: underline; text-decoration-color: rgba(251,191,36,.4); }
main article > div h1, main article > div h2, main article > div h3,
main article > div h4, main article > div h5, main article > div h6 {
  font-family: var(--font-display); color: #f4f4f5; text-transform: none; letter-spacing: 0;
  margin: 1.2em 0 .4em; line-height: 1.3;
}
main article > div h1 { font-size: 1.4rem; border-bottom: 1px solid var(--border); padding-bottom: .3rem; }
main article > div h2 { font-size: 1.2rem; }
main article > div h3 { font-size: 1.05rem; }
main article > div h4, main article > div h5, main article > div h6 { font-size: .95rem; }
main article > div hr { border: 0; border-top: 1px solid var(--border); }
main article > div p { margin: .55em 0; }
main article > div ul, main article > div ol { padding-left: 1.5rem; }
main article > div li { margin: .2em 0; }
main article > div img {
  max-width: 100%; height: auto; border: 1px solid #3f3f46; background: #0a0a0b; margin: .25rem 0;
}
main article > div img[src^="/pgmfi/wiki/assets/icn/"] { border: 0; background: none; image-rendering: pixelated; }
/* Imported tables carry hardcoded width="768" attributes: cap them at the
   viewport and scroll horizontally instead of overflowing the page. */
main article > div table {
  border-collapse: collapse; margin: .75rem 0;
  display: block; width: fit-content; max-width: 100%;
  overflow-x: auto; -webkit-overflow-scrolling: touch;
}
main article > div th, main article > div td {
  border: 1px solid var(--border-soft); padding: .35rem .6rem; text-align: left;
  /* the content area sets overflow-wrap:anywhere, which would let columns
     crush to one character; break at spaces only and let the table scroll */
  overflow-wrap: normal; word-break: normal;
}
main article > div th { background: #18181b; font-family: var(--font-mono); font-size: .78rem; color: #a1a1aa; }
main article > div pre, main article > div code, main article > div tt {
  font-family: var(--font-mono); font-size: .85em; color: #6ee7b7;
  background: #0a0a0b; border: 1px solid var(--border);
  padding: .1rem .35rem; max-width: 100%; overflow-x: auto;
}
main article > div pre { display: block; padding: .6rem .8rem; }
main article > div blockquote {
  border-left: 2px solid var(--red); background: rgba(255,255,255,.03);
  padding: .5rem .75rem; margin: .5rem 0; color: #a1a1aa; font-size: .9em;
}

.dead-link, .dead-file { color: var(--muted); }
.dead-link { border-bottom: 1px dotted #52525b; cursor: help; }
a.dead-file { text-decoration: underline dotted; }
main article > div span[data-missing] {
  display: inline-flex; align-items: baseline; gap: .5rem; max-width: 100%;
  border: 1px dashed #52525b; background: rgba(255,255,255,.02);
  padding: .45rem .7rem; margin: .15rem 0;
  font-family: var(--font-mono); font-size: .72rem; color: var(--muted); overflow-wrap: anywhere;
}
main article > div span[data-missing]::before { content: '◈'; color: var(--red); }

/* ---------- landing cards ---------- */
main > nav { display: grid; gap: 1rem; margin-bottom: 2rem; }
@media (min-width: 640px) { main > nav { grid-template-columns: 1fr 1fr; } }
main > nav a {
  display: block; border: 1px solid var(--border); background: var(--panel); padding: 1rem 1.25rem;
}
main > nav a:hover { border-color: rgba(251,191,36,.5); }
main > nav h3 { font-family: var(--font-display); font-size: 1.05rem; text-transform: uppercase; color: #f4f4f5; margin: 0; }
main > nav p { font-size: .8rem; color: var(--muted); margin: .35rem 0 0; }
main > nav small { font-family: var(--font-mono); font-size: .68rem; color: var(--amber-soft); }

/* ---------- small screens ---------- */
@media (max-width: 640px) {
  body { padding: 0 .75rem 3rem; }
  body > header { padding: 1.25rem 0 1rem; }
  body > header strong { font-size: 1.5rem; }
  /* 16px stops iOS Safari from zooming the page when the field gets focus */
  body > header input { font-size: 1rem; padding: .65rem .75rem; }
  body > header button { padding: 0 1.1rem; }
  main h1, main h2 { font-size: 1.1rem; }
  main article > div { padding: .75rem .85rem; font-size: .88rem; }
  main article > div ul, main article > div ol { padding-left: 1.1rem; }
}

/* ---------- footer ---------- */
body > footer {
  font-family: var(--font-mono); font-size: .69rem; color: #52525b;
  display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem;
  border-top: 1px solid var(--border); margin-top: 3rem; padding-top: 1rem;
}
body > footer b { color: #a1a1aa; font-weight: 400; }
</style>
<script>
// Image load failures don't bubble, so catch them in the capture phase and
// swap the dead <img> for a placeholder that keeps the original reference.
document.addEventListener('error', function (e) {
  var img = e.target;
  if (!(img instanceof HTMLImageElement) || !img.closest('main article > div')) return;
  var url = img.getAttribute('src') || '';
  var name = url.split('/').pop().split('?')[0] || url;
  var box = document.createElement('span');
  box.setAttribute('data-missing', '');
  box.appendChild(document.createTextNode('image not recovered: '));
  var a = document.createElement('a');
  a.href = url;
  a.rel = 'nofollow noopener';
  a.textContent = decodeURIComponent(name.length > 60 ? name.slice(0, 57) + '…' : name);
  box.appendChild(a);
  img.replaceWith(box);
}, true);

// Topic title autocomplete for the header search box.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('form[role="search"]').forEach(function (form) {
    var input = form.querySelector('input[name="q"]');
    if (!input) return;
    var list = document.createElement('menu');
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
      var rows = list.querySelectorAll('li');
      rows.forEach(function (r) { r.removeAttribute('aria-selected'); });
      active = i;
      if (i >= 0 && rows[i]) {
        rows[i].setAttribute('aria-selected', 'true');
        rows[i].scrollIntoView({ block: 'nearest' });
      }
    }

    function render(data, q) {
      list.innerHTML = '';
      if (!data.length) { close(); return; }
      data.forEach(function (t) {
        var li = document.createElement('li');
        var a = document.createElement('a');
        a.href = t.url;
        var ti = document.createElement('b');
        ti.appendChild(highlight(t.title, q));
        a.appendChild(ti);
        var f = document.createElement('small');
        f.textContent = 'in ' + t.web;
        a.appendChild(f);
        li.appendChild(a);
        list.appendChild(li);
      });
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
        fetch('/pgmfi/wiki/suggest?q=' + encodeURIComponent(q), { signal: ctl.signal })
          .then(function (r) { return r.json(); })
          .then(function (data) { render(data, q); })
          .catch(function () {});
      }, 150);
    });

    input.addEventListener('keydown', function (e) {
      if (list.hidden) return;
      var rows = list.querySelectorAll('li');
      if (e.key === 'ArrowDown') { e.preventDefault(); setActive((active + 1) % rows.length); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); setActive((active - 1 + rows.length) % rows.length); }
      else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); rows[active].querySelector('a').click(); }
      else if (e.key === 'Escape') { close(); }
    });

    document.addEventListener('click', function (e) {
      if (!form.contains(e.target)) close();
    });
  });
});
</script>
</head>
<body>

<header>
  <div>
    <div>
      <small>// recovered archive</small>
      <p><strong><a href="/pgmfi/wiki/">pgmfi<b>.</b>org wiki</a></strong></p>
      <p>honda / acura ecu development · TWiki library capture · <a href="/pgmfi/forum/">forum archive →</a></p>
    </div>
    <form action="/pgmfi/wiki/search" method="get" role="search">
      <p>
        <input type="search" name="q" value="<?= h($_GET['q'] ?? '') ?>" placeholder="search topics_"
               enterkeyhint="search" autocomplete="off">
        <button aria-label="Search">&gt;&gt;</button>
      </p>
    </form>
  </div>

  <nav aria-label="Breadcrumb">
    <b>READ-ONLY</b>
    <ol>
      <li><a href="/pgmfi/">pgmfi</a></li>
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
  </nav>
</header>

<main>
<?php
}

function layout_bottom(): void
{
    $c = db()->query(
        "SELECT
            (SELECT COUNT(*) FROM topics WHERE web='library') l,
            (SELECT COUNT(*) FROM topics WHERE web='home') h,
            (SELECT COUNT(*) FROM attachments) a"
    )->fetch();
    ?>
</main>

<footer>
  <p>
    ARCHIVED:
    <b><?= number_format((int) $c['l']) ?></b> library topics ·
    <b><?= number_format((int) $c['h']) ?></b> member pages ·
    <b><?= number_format((int) $c['a']) ?></b> attachments
  </p>
  <p>TWiki capture · static archive · content © original authors</p>
</footer>
</body>
</html>
    <?php
}
