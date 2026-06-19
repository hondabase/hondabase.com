<?php
require 'vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Article;

// Slugs of articles I corrected
$correctedSlugs = [
    '6260a-testing', 'add-knock-to-p30g00', 'label-decode', 'obd0-code-compatibility', 'p2p',
    'popol-vuh', 'super-pro-z', 'text-formatting-rules', 'what-you-need', 'chipping-obd1-big-case',
    'new-markup-test-page', 'vise-grip', 'wiki', 'injector-sizing', 'obd1p08-auto-manual', 'p27',
    '02d011f0-1500', 'p2e', 'p2n', 'p2t', 'cpu', 'd1780', 'g-spot', 'most-popular', 'octal',
    'oz-dm', 'pj7', 'square-brackets', 'wot', '5128xram', '66k-resources', 'blue-loctite',
    'disable-vtec-vss-check-p28', 'dual-maps', 'dual-tables', 'ecu-boost-controller', 'ideal-gas-law',
    'inter-wiki', 'internal-rom', 'java-script', 'launch-control', 'obd0-edit', 'obd1-8bit-fuel',
    'p1j', 'p1k', 'red-loctite', 'turbo-compressor-map', 'uv-erase', 'warning-about-adding-a-knock-sensor',
    'full-throttle-shift', '66k-assembler-routines', 'chipping-obd1-small-case', 'disable-vtec-vss-check',
    'ect', 'electronic-air-control-vale', 'engine-simulator', 'erm', 'fuel-octane', 'iat', 'io',
    'jdm', 'latches', 'lego-zoo', 'p0c', 'p11', 'p5m', 'p5p', 'p75', 'p76', 'p84', 'pa-sensor',
    'pr7', 'pt5', 'pwm', 'rtfm', 'sectors', 'service-manual', 'uber-data', 'usdm', 'vss',
    '02d01980-1500', '5050s', 'edm', 'maf-sensor', 'obd1-conversion-formulae', 'obd1-oki66207-reader-plcc68',
    'obd1cn2', 'obd2-oki66507-reader-nico', 'p07', 'pgsrc-translation', 'rm11', 'rom-maps', 'rtp-project',
    'ecu-troubleshooting', 'honda-error-codes', '02d01720-1500'
];

$data = [];
foreach ($correctedSlugs as $slug) {
    $articles = Article::where('slug', $slug)->get();
    $entry = ['slug' => $slug, 'summaries' => []];
    foreach ($articles as $a) {
        $entry['summaries'][$a->locale] = $a->summary;
    }
    if (!empty($entry['summaries'])) {
        $data[] = $entry;
    }
}

file_put_contents('scratch/subagents_payload.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Payload generated with " . count($data) . " articles.\n";
