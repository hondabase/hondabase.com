<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Support\ArticleDocument;

$base = __DIR__ . '/../content/cars';
$files = [];
$dirIter = new RecursiveDirectoryIterator($base);
$iter = new RecursiveIteratorIterator($dirIter);

foreach ($iter as $file) {
    if ($file->isFile() && $file->getExtension() === 'md') {
        $files[] = $file->getRealPath();
    }
}
sort($files);

$unmapped = [];
foreach ($files as $file) {
    $raw = file_get_contents($file);
    $doc = ArticleDocument::parse($raw);
    $fm = $doc['fm'];
    $relative = str_replace($base . '/', '', $file);

    $applies = $fm['applies_to'] ?? [];
    if (!isset($applies['models']) && !isset($applies['chassis'])) {
        $unmapped[] = $relative;
    }
}

echo "Unmapped count: " . count($unmapped) . "\n";
// Print first 50
foreach (array_slice($unmapped, 0, 50) as $file) {
    echo "- $file\n";
}
