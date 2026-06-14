<?php
require __DIR__ . '/../vendor/autoload.php';

$ROOT = dirname(__DIR__);
$env = [];
foreach (file($ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v);
}
$pdo = new PDO(
    "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname=pgmfi_wiki_archive;charset=utf8mb4",
    $env['DB_USERNAME'], $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$OUT_BASE = $ROOT . '/content/cars/electronics';
$ported = array_map('basename', glob("$OUT_BASE/*", GLOB_ONLYDIR) ?: []);

$st = $pdo->query("SELECT topic_id, slug, title, length(body_html) as len, body_html FROM topics WHERE web='library' AND is_webhome=0 ORDER BY len DESC");
$all = $st->fetchAll(PDO::FETCH_ASSOC);

echo "Total topics in library: " . count($all) . "\n";
echo "Already ported topics: " . count($ported) . "\n";

$unported = [];
foreach ($all as $t) {
    if (in_array($t['slug'], $ported, true)) continue;
    $unported[] = $t;
}

echo "Unported topics: " . count($unported) . "\n\n";

foreach ($unported as $i => $t) {
    $text = strip_tags($t['body_html']);
    $preview = mb_substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 100);
    printf("[%3d] %-35s len=%-6d preview=\"%s\"\n", $i + 1, $t['slug'], $t['len'], $preview);
}
