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

$ecuMap = [];
$engineMap = [];
$obdMap = [];
$brandMap = [];

foreach ($files as $file) {
    $raw = file_get_contents($file);
    $doc = ArticleDocument::parse($raw);
    $fm = $doc['fm'];
    $applies = $fm['applies_to'] ?? [];

    if (isset($applies['ecus'])) {
        foreach ((array)$applies['ecus'] as $ecu) {
            $ecuMap[$ecu] = ($ecuMap[$ecu] ?? 0) + 1;
        }
    }
    if (isset($applies['engines'])) {
        foreach ((array)$applies['engines'] as $engine) {
            $engineMap[$engine] = ($engineMap[$engine] ?? 0) + 1;
        }
    }
    if (isset($applies['obd'])) {
        foreach ((array)$applies['obd'] as $obd) {
            $obdMap[$obd] = ($obdMap[$obd] ?? 0) + 1;
        }
    }
    if (isset($applies['brand'])) {
        $brand = is_array($applies['brand']) ? implode('/', $applies['brand']) : $applies['brand'];
        $brandMap[$brand] = ($brandMap[$brand] ?? 0) + 1;
    }
}

echo "=== ECUS ===\n";
arsort($ecuMap);
print_r($ecuMap);

echo "\n=== ENGINES ===\n";
arsort($engineMap);
print_r($engineMap);

echo "\n=== OBD ===\n";
arsort($obdMap);
print_r($obdMap);

echo "\n=== BRANDS ===\n";
arsort($brandMap);
print_r($brandMap);
