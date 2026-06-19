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

$noMetaFiles = [];
$uniqueAppliesTo = [];

foreach ($files as $file) {
    $raw = file_get_contents($file);
    $doc = ArticleDocument::parse($raw);
    $fm = $doc['fm'];
    $relative = str_replace($base . '/', '', $file);

    $applies = $fm['applies_to'] ?? null;
    $fits = $fm['fits'] ?? null;

    if (!$applies && !$fits) {
        $noMetaFiles[] = $relative;
    } else if ($applies) {
        $key = json_encode($applies);
        if (!isset($uniqueAppliesTo[$key])) {
            $uniqueAppliesTo[$key] = [];
        }
        $uniqueAppliesTo[$key][] = $relative;
    }
}

echo "=== NO METADATA FILES ===\n";
foreach ($noMetaFiles as $file) {
    echo "- $file\n";
}

echo "\n=== UNIQUE APPLIES_TO SHAPES ===\n";
foreach ($uniqueAppliesTo as $shape => $list) {
    echo "$shape (count: " . count($list) . ")\n";
    // Print first 3 examples
    for ($i = 0; $i < min(3, count($list)); $i++) {
        echo "  - " . $list[$i] . "\n";
    }
}
