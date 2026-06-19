<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Support\ArticleDocument;

$enBase = __DIR__ . '/../content/cars';
$ptBase = __DIR__ . '/../content/pt/cars';

$files = [];
$dirIter = new RecursiveDirectoryIterator($enBase);
$iter = new RecursiveIteratorIterator($dirIter);

foreach ($iter as $file) {
    if ($file->isFile() && $file->getExtension() === 'md') {
        $files[] = $file->getRealPath();
    }
}
sort($files);

// ECU to Specific Models and Chassis Mapping
$ecuToCompat = [
    'PG6' => ['models' => ['integra'], 'chassis' => ['da']],
    'PM5' => ['models' => ['civic', 'crx'], 'chassis' => ['ef']],
    'PM6' => ['models' => ['civic', 'crx'], 'chassis' => ['ef']],
    'PM7' => ['models' => ['civic', 'crx'], 'chassis' => ['ef']],
    'PM8' => ['models' => ['crx'], 'chassis' => ['ef']],
    'PS9' => ['models' => ['civic'], 'chassis' => ['ef']],
    'PW0' => ['models' => ['civic', 'crx', 'integra'], 'chassis' => ['ef', 'da']],
    'PR3' => ['models' => ['civic', 'crx', 'integra'], 'chassis' => ['ef', 'da']],
    'PR2' => ['models' => ['civic', 'crx'], 'chassis' => ['ef']],
    'PR4' => ['models' => ['integra'], 'chassis' => ['da', 'dc2']],
    'P05' => ['models' => ['civic'], 'chassis' => ['eg']],
    'P06' => ['models' => ['civic'], 'chassis' => ['eg']],
    'P07' => ['models' => ['civic'], 'chassis' => ['eg']],
    'P08' => ['models' => ['civic'], 'chassis' => ['eg']],
    'P27' => ['models' => ['civic'], 'chassis' => ['eg']],
    'P28' => ['models' => ['civic'], 'chassis' => ['eg']],
    'P30' => ['models' => ['civic', 'del-sol'], 'chassis' => ['eg', 'eg-eh']],
    'P2N' => ['models' => ['civic'], 'chassis' => ['ek']],
    'P2P' => ['models' => ['civic'], 'chassis' => ['ek']],
    'P2E' => ['models' => ['civic'], 'chassis' => ['ek']],
    'P2M' => ['models' => ['civic'], 'chassis' => ['ek']],
    'P2T' => ['models' => ['civic'], 'chassis' => ['ek']],
    'P54' => ['models' => ['accord'], 'chassis' => ['cb-cd']],
    'P61' => ['models' => ['integra'], 'chassis' => ['dc2']],
    'P72' => ['models' => ['integra'], 'chassis' => ['dc2']],
    'P73' => ['models' => ['integra'], 'chassis' => ['dc2']],
    'P74' => ['models' => ['integra'], 'chassis' => ['dc2']],
    'P75' => ['models' => ['integra'], 'chassis' => ['dc2']],
    'P13' => ['models' => ['prelude'], 'chassis' => ['bb']],
    'P14' => ['models' => ['prelude'], 'chassis' => ['bb']],
    'P0A' => ['models' => ['accord'], 'chassis' => ['cb-cd']],
    'P5P' => ['models' => ['prelude'], 'chassis' => ['bb']],
    'PCT' => ['models' => ['civic', 'integra'], 'chassis' => ['ek', 'dc2']],
    'PCX' => ['models' => ['s2000'], 'chassis' => ['ap1', 'ap2']],
    'DC5' => ['models' => ['rsx'], 'chassis' => ['dc5']],
];

// Engine to Specific Models and Chassis Mapping
$engineToCompat = [
    'B16' => ['models' => ['civic', 'crx', 'del-sol', 'integra'], 'chassis' => ['ef', 'eg', 'ek', 'eg-eh', 'da', 'dc2']],
    'B18A' => ['models' => ['integra'], 'chassis' => ['da', 'dc2']],
    'B18B' => ['models' => ['integra'], 'chassis' => ['dc2']],
    'B18C' => ['models' => ['integra'], 'chassis' => ['dc2']],
    'B18' => ['models' => ['integra'], 'chassis' => ['da', 'dc2']],
    'B20' => ['models' => ['civic', 'integra'], 'chassis' => ['eg', 'ek', 'dc2']],
    'D15' => ['models' => ['civic'], 'chassis' => ['ef', 'eg']],
    'D16A' => ['models' => ['civic', 'crx'], 'chassis' => ['ef']],
    'D16Z' => ['models' => ['civic', 'del-sol'], 'chassis' => ['eg', 'eg-eh']],
    'D16Y' => ['models' => ['civic'], 'chassis' => ['ek']],
    'D16' => ['models' => ['civic', 'crx', 'del-sol'], 'chassis' => ['ef', 'eg', 'ek', 'eg-eh']],
    'H22' => ['models' => ['prelude'], 'chassis' => ['bb']],
    'H23' => ['models' => ['prelude'], 'chassis' => ['bb']],
    'F22' => ['models' => ['accord'], 'chassis' => ['cb-cd']],
    'F20C' => ['models' => ['s2000'], 'chassis' => ['ap1', 'ap2']],
    'F22C' => ['models' => ['s2000'], 'chassis' => ['ap1', 'ap2']],
    'C30' => ['models' => ['nsx'], 'chassis' => ['na1-na2']],
    'C32' => ['models' => ['nsx'], 'chassis' => ['na1-na2']],
];

// OBD to general Chassis/Models Map
$obdToCompat = [
    0 => [
        'models' => ['civic', 'crx', 'integra'],
        'chassis' => ['ef', 'da'],
    ],
    1 => [
        'models' => ['civic', 'del-sol', 'integra', 'prelude', 'accord'],
        'chassis' => ['eg', 'eg-eh', 'da', 'dc2', 'bb', 'cb-cd'],
    ],
    2 => [
        'models' => ['civic', 'integra', 'rsx', 'prelude', 'accord', 's2000', 'nsx'],
        'chassis' => ['ek', 'em-ep', 'dc2', 'dc5', 'bb', 'cb-cd', 'ap1', 'ap2', 'na1-na2'],
    ],
];

$updatedCount = 0;

foreach ($files as $enFile) {
    $raw = file_get_contents($enFile);
    $doc = ArticleDocument::parse($raw);
    $fm = $doc['fm'];
    $body = $doc['body'];

    $relative = str_replace($enBase . '/', '', $enFile);

    $detectedModels = [];
    $detectedChassis = [];

    // Analyze content + existing frontmatter
    $textToSearch = strtolower($enFile . "\n" . ($fm['summary'] ?? '') . "\n" . $body);

    // 1. Check ECUs
    if (isset($fm['applies_to']['ecus'])) {
        foreach ((array)$fm['applies_to']['ecus'] as $ecu) {
            $ecu = strtoupper($ecu);
            if (isset($ecuToCompat[$ecu])) {
                $detectedModels = array_merge($detectedModels, $ecuToCompat[$ecu]['models']);
                $detectedChassis = array_merge($detectedChassis, $ecuToCompat[$ecu]['chassis']);
            }
        }
    }
    // Also scan text for specific ECUs
    foreach ($ecuToCompat as $ecu => $info) {
        if (preg_match('/\b' . preg_quote($ecu, '/') . '\b/i', $textToSearch)) {
            $detectedModels = array_merge($detectedModels, $info['models']);
            $detectedChassis = array_merge($detectedChassis, $info['chassis']);
        }
    }

    // 2. Check Engines
    if (isset($fm['applies_to']['engines'])) {
        foreach ((array)$fm['applies_to']['engines'] as $eng) {
            $eng = strtoupper($eng);
            foreach ($engineToCompat as $prefix => $info) {
                if (strpos($eng, $prefix) === 0) {
                    $detectedModels = array_merge($detectedModels, $info['models']);
                    $detectedChassis = array_merge($detectedChassis, $info['chassis']);
                }
            }
        }
    }
    // Also scan text for specific Engines
    foreach ($engineToCompat as $eng => $info) {
        if (preg_match('/\b' . preg_quote($eng, '/') . '/i', $textToSearch)) {
            $detectedModels = array_merge($detectedModels, $info['models']);
            $detectedChassis = array_merge($detectedChassis, $info['chassis']);
        }
    }

    // 3. Keyword heuristic mapping for chassis/models in text
    // Models
    if (strpos($textToSearch, 'civic') !== false) {
        $detectedModels[] = 'civic';
    }
    if (strpos($textToSearch, 'crx') !== false) {
        $detectedModels[] = 'crx';
    }
    if (strpos($textToSearch, 'del sol') !== false || strpos($textToSearch, 'delsol') !== false) {
        $detectedModels[] = 'del-sol';
    }
    if (strpos($textToSearch, 'integra') !== false) {
        $detectedModels[] = 'integra';
    }
    if (strpos($textToSearch, 'prelude') !== false) {
        $textToSearchContainsPrelude = true;
        $detectedModels[] = 'prelude';
    }
    if (strpos($textToSearch, 'accord') !== false) {
        $detectedModels[] = 'accord';
    }
    if (strpos($textToSearch, 's2000') !== false) {
        $detectedModels[] = 's2000';
    }
    if (strpos($textToSearch, 'nsx') !== false) {
        $detectedModels[] = 'nsx';
    }
    if (strpos($textToSearch, 'rsx') !== false) {
        $detectedModels[] = 'rsx';
    }

    // Chassis
    if (preg_match('/\b(ef[89]?|ed[34679]?|ee[89]?)\b/', $textToSearch)) {
        $detectedChassis[] = 'ef';
    }
    if (preg_match('/\b(eg[1234569]|eh[2369]|ej[12])\b/', $textToSearch) || strpos($textToSearch, 'eg civic') !== false) {
        $detectedChassis[] = 'eg';
        if (preg_match('/\b(eg1|eg2|eh6|ej1)\b/', $textToSearch)) {
            $detectedChassis[] = 'eg-eh';
        }
    }
    if (preg_match('/\b(ek[49]|ej[678])\b/', $textToSearch) || strpos($textToSearch, 'ek civic') !== false) {
        $detectedChassis[] = 'ek';
    }
    if (preg_match('/\b(ep3|em2|es1|es2)\b/', $textToSearch)) {
        $detectedChassis[] = 'em-ep';
    }
    if (preg_match('/\b(da[35679]|db1|db2)\b/', $textToSearch)) {
        $detectedChassis[] = 'da';
    }
    if (preg_match('/\b(dc2|dc4|db8)\b/', $textToSearch)) {
        $detectedChassis[] = 'dc2';
    }
    if (preg_match('/\b(dc5)\b/', $textToSearch)) {
        $detectedChassis[] = 'dc5';
    }
    if (preg_match('/\b(bb[12468])\b/', $textToSearch)) {
        $detectedChassis[] = 'bb';
    }
    if (preg_match('/\b(cb[79]|cd[57])\b/', $textToSearch)) {
        $detectedChassis[] = 'cb-cd';
    }
    if (preg_match('/\b(ap1)\b/', $textToSearch)) {
        $detectedChassis[] = 'ap1';
    }
    if (preg_match('/\b(ap2)\b/', $textToSearch)) {
        $detectedChassis[] = 'ap2';
    }
    if (preg_match('/\b(na1|na2)\b/', $textToSearch)) {
        $detectedChassis[] = 'na1-na2';
    }

    // 4. OBD Fallback: If no chassis or models resolved yet, map based on obd array
    if (empty($detectedModels) && empty($detectedChassis)) {
        if (isset($fm['applies_to']['obd'])) {
            foreach ((array)$fm['applies_to']['obd'] as $obd) {
                if (isset($obdToCompat[$obd])) {
                    $detectedModels = array_merge($detectedModels, $obdToCompat[$obd]['models']);
                    $detectedChassis = array_merge($detectedChassis, $obdToCompat[$obd]['chassis']);
                }
            }
        }
    }

    // De-duplicate
    $detectedModels = array_unique($detectedModels);
    $detectedChassis = array_unique($detectedChassis);

    // Sort to be deterministic
    sort($detectedModels);
    sort($detectedChassis);

    // If still empty (e.g. no metadata at all), let's fall back to general OBD 0,1,2 since they are all cars
    if (empty($detectedModels) && empty($detectedChassis)) {
        $detectedModels = ['civic', 'crx', 'del-sol', 'integra', 'prelude', 'accord', 's2000', 'nsx'];
        $detectedChassis = ['ef', 'eg', 'ek', 'da', 'dc2', 'bb', 'cb-cd', 'ap1', 'ap2', 'na1-na2'];
    }

    // Update en frontmatter
    $fm['applies_to'] = $fm['applies_to'] ?? [];
    $fm['applies_to']['models'] = $detectedModels;
    $fm['applies_to']['chassis'] = $detectedChassis;

    $newEnRaw = ArticleDocument::compose($fm, $body);
    file_put_contents($enFile, $newEnRaw);

    // Mirror to PT if file exists
    $ptFile = $ptBase . '/' . $relative;
    if (file_exists($ptFile)) {
        $ptRaw = file_get_contents($ptFile);
        $ptDoc = ArticleDocument::parse($ptRaw);
        $ptFm = $ptDoc['fm'];
        $ptBody = $ptDoc['body'];

        $ptFm['applies_to'] = $ptFm['applies_to'] ?? [];
        $ptFm['applies_to']['models'] = $detectedModels;
        $ptFm['applies_to']['chassis'] = $detectedChassis;

        $newPtRaw = ArticleDocument::compose($ptFm, $ptBody);
        file_put_contents($ptFile, $newPtRaw);
    }

    $updatedCount++;
}

echo "Successfully analyzed and updated $updatedCount en + pt articles with accurate chassis/model compatibility metadata.\n";
