<?php
$ROOT = "/var/www/hondabase/www";
$env = [];
foreach (file($ROOT . "/.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line[0] === "#" || !str_contains($line, "=")) continue;
    [$k, $v] = explode("=", $line, 2);
    $env[trim($k)] = trim($v);
}
$pdo = new PDO(
    "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname=pgmfi_wiki_archive;charset=utf8mb4",
    $env['DB_USERNAME'], $env['DB_PASSWORD']
);
$st = $pdo->query("SELECT slug, title, body_html FROM topics WHERE web='library' AND is_webhome=0");
$stubs = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
    $text = trim(strip_tags($t['body_html']));
    if (strlen($text) < 100) {
        $stubs[] = [
            'slug' => $t['slug'],
            'title' => $t['title'],
            'length' => strlen($text),
            'content' => preg_replace("/\s+/", " ", $text)
        ];
    }
}

echo "Found " . count($stubs) . " stub topics:\n\n";
foreach ($stubs as $i => $s) {
    printf("[%2d] Slug: %-30s (len=%d)\n     Content: \"%s\"\n\n", $i + 1, $s['slug'], $s['length'], $s['content']);
}
