<?php
declare(strict_types=1);

try {
    require_once '/var/www/tracker.php';
    track_page_view('PGMFI Archive Hub');
} catch (Throwable $e) {
    error_log('Tracking failed: ' . $e->getMessage());
}

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function archive_counts(string $configPath, string $sql): array
{
    try {
        $cfg = require $configPath;
        $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        return $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('pgmfi hub counts failed: ' . $e->getMessage());
        return [];
    }
}

$forum = archive_counts(__DIR__ . '/forum/inc/config.php',
    'SELECT (SELECT COUNT(*) FROM topics) t, (SELECT COUNT(*) FROM posts) p, (SELECT COUNT(*) FROM authors) a');
$wiki = archive_counts(__DIR__ . '/wiki/inc/config.php',
    'SELECT (SELECT COUNT(*) FROM topics) t, (SELECT COUNT(*) FROM attachments) a');

$n = fn($v) => number_format((int) ($v ?? 0));
$title = 'PGMFI.org Archive - Honda / Acura ECU Development';
$description = 'The recovered pgmfi.org archive: ' . $n($forum['t'] ?? 0) . ' forum topics and '
    . $n($wiki['t'] ?? 0) . ' wiki articles on grassroots Honda and Acura ECU development, '
    . 'chipping and tuning, preserved by HondaBase.';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?></title>
<meta name="description" content="<?= h($description) ?>">
<link rel="canonical" href="https://www.hondabase.com/pgmfi/">
<meta property="og:title" content="<?= h($title) ?>">
<meta property="og:description" content="<?= h($description) ?>">
<meta property="og:url" content="https://www.hondabase.com/pgmfi/">
<meta property="og:type" content="website">
<meta property="og:site_name" content="HondaBase · pgmfi.org archive">
<meta name="twitter:card" content="summary">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=IBM+Plex+Mono:ital,wght@0,400;0,500;0,600;1,400&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --red: #e10600;
  --amber: #fbbf24;
  --bg: #09090b;
  --border: #27272a;
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
  margin: 0 auto; max-width: 56rem; min-height: 100vh;
  padding: 0 1rem 4rem; position: relative;
  -webkit-font-smoothing: antialiased;
}
body::before {
  content: ''; position: fixed; inset: 0; z-index: -1; pointer-events: none;
  background-image: repeating-linear-gradient(0deg, rgba(255,255,255,.022) 0 1px, transparent 1px 3px);
}
body::after {
  content: ''; position: fixed; inset: 0; z-index: -1; pointer-events: none;
  background:
    radial-gradient(1100px 500px at 85% -10%, rgba(225,6,0,.07), transparent 60%),
    radial-gradient(900px 420px at -10% 0%, rgba(251,191,36,.05), transparent 55%);
}
a { color: rgba(251,191,36,.85); text-decoration: none; }
a:hover { color: #fcd34d; }

body > header { padding: 3.5rem 0 1.5rem; border-bottom: 2px solid var(--border); }
body > header small {
  font-family: var(--font-mono); display: block;
  font-size: .69rem; letter-spacing: .25em; text-transform: uppercase; color: var(--red);
}
body > header h1 {
  font-family: var(--font-display);
  font-size: 2.6rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .05em; color: #f4f4f5; margin: .2rem 0 0;
}
body > header h1 b { color: var(--red); }
body > header p { font-family: var(--font-mono); font-size: .78rem; color: var(--muted); margin: .4rem 0 0; }

main > p { line-height: 1.7; font-size: .95rem; margin: 1.5rem 0 2rem; max-width: 44rem; }
main > p b { color: #f4f4f5; font-weight: 600; }

main nav { display: grid; gap: 1rem; }
@media (min-width: 640px) { main nav { grid-template-columns: 1fr 1fr; } }
main nav a {
  display: block; border: 1px solid var(--border);
  background: rgba(24,24,27,.6); padding: 1.5rem 1.5rem 1.25rem;
}
main nav a:hover { border-color: rgba(251,191,36,.5); }
main nav h2 {
  font-family: var(--font-display); font-size: 1.4rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .04em; color: #f4f4f5; margin: 0;
}
main nav h2::after { content: ' →'; color: var(--amber); }
main nav p { font-size: .85rem; color: var(--muted); line-height: 1.6; margin: .5rem 0 .9rem; }
main nav small { font-family: var(--font-mono); font-size: .7rem; color: rgba(251,191,36,.85); }

body > footer {
  font-family: var(--font-mono); font-size: .69rem; color: #52525b;
  display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem;
  border-top: 1px solid var(--border); margin-top: 3rem; padding-top: 1rem;
}
body > footer a { color: #a1a1aa; }

@media (max-width: 640px) {
  body { padding: 0 .75rem 3rem; }
  body > header { padding-top: 2rem; }
  body > header h1 { font-size: 1.8rem; }
}
</style>
</head>
<body>

<header>
  <small>// recovered archive</small>
  <h1>pgmfi<b>.</b>org</h1>
  <p>honda / acura grassroots ecu development · 1999–2008 · preserved by <a href="https://www.hondabase.com">hondabase</a></p>
</header>

<main>
  <p>
    <b>pgmfi.org</b> ("Programmed Fuel Injection") was the home of the grassroots Honda and Acura
    ECU development community - the people who reverse-engineered OBD0/OBD1/OBD2 engine computers,
    documented their hardware, and built the free chipping and tuning tools that much of the scene
    still stands on. The original site is long gone; what could be recovered from mirrors and the
    Internet Archive lives on here, read-only.
  </p>

  <nav aria-label="Archives">
    <a href="/pgmfi/forum/">
      <h2>Forum</h2>
      <p>The phpBB board: development threads, Q&amp;A, datalogging, ROM trading and a decade of
         collective debugging, recovered post by post.</p>
      <small><?= $n($forum['t'] ?? 0) ?> topics · <?= $n($forum['p'] ?? 0) ?> posts · <?= $n($forum['a'] ?? 0) ?> members</small>
    </a>
    <a href="/pgmfi/wiki/">
      <h2>Wiki</h2>
      <p>The TWiki library: structured technical documentation - ECU hardware, definition codes,
         sensors, chipping guides and tool downloads.</p>
      <small><?= $n($wiki['t'] ?? 0) ?> articles · <?= $n($wiki['a'] ?? 0) ?> attachments</small>
    </a>
  </nav>
</main>

<footer>
  <p>content © original authors · <a href="http://creativecommons.org/licenses/by-nc-sa/1.0/" rel="nofollow noopener">CC BY-NC-SA</a></p>
  <p><a href="https://www.hondabase.com">hondabase.com</a> - community-driven honda knowledgebase</p>
</footer>
</body>
</html>
