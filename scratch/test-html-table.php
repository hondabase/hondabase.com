<?php
$ROOT = '/var/www/hondabase/www';
$env = [];
foreach (file($ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v);
}
$pdo = new PDO(
    "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname=pgmfi_wiki_archive;charset=utf8mb4",
    $env['DB_USERNAME'], $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$st = $pdo->prepare("select body_html from topics where slug='ecu-definition-codes'");
$st->execute();
$html = $st->fetchColumn();

// Let's print out if there are <table> tags in the HTML
if (str_contains($html, '<table')) {
    echo "Found table tag!\n";
    // Print the first table tag and its structure
    preg_match('/<table[^>]*>.*?<\/table>/is', $html, $matches);
    echo "Table length: " . strlen($matches[0]) . "\n";
    echo "Table snippet:\n" . substr($matches[0], 0, 1000) . "\n";
} else {
    echo "No table tag found in HTML!\n";
    echo "HTML snippet:\n" . substr($html, 0, 1000) . "\n";
}
