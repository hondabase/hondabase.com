<?php
require '/var/www/hondabase/www/vendor/autoload.php';

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

use League\HTMLToMarkdown\HtmlConverter;
$conv = new HtmlConverter([
    'header_style' => 'atx',
    'remove_nodes' => 'script style',
    'hard_break' => true,
    'use_autolinks' => false
]);

$md = $conv->convert($html);

$lines = explode("\n", $md);
$matchCount = 0;
foreach ($lines as $line) {
    if (str_contains($line, '|')) {
        echo "MATCH: $line\n";
        if (++$matchCount >= 20) break;
    }
}
