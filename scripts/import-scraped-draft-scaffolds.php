#!/usr/bin/env php
<?php

use App\Models\ArticleDraft;
use App\Models\User;
use App\Services\ArticleService;
use App\Support\ArticleDocument;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$apply = in_array('--apply', $argv, true);
$user = User::query()->whereRaw('LOWER(discord_username) = ?', ['viruxe'])->sole();
$service = app(ArticleService::class);

$allCars = [
    'models' => ['accord', 'civic', 'crx', 'del-sol', 'integra', 'prelude'],
];
$civic = ['models' => ['civic'], 'chassis' => ['eg', 'ek']];
$civicEk = ['models' => ['civic'], 'chassis' => ['ek']];
$prelude = ['models' => ['prelude'], 'chassis' => ['bb']];
$bSeries = [
    'models' => ['civic', 'crx', 'del-sol', 'integra'],
    'engines' => ['b16', 'b17', 'b18', 'b20'],
];
$hfSeries = [
    'models' => ['accord', 'prelude'],
    'engines' => ['f20', 'f22', 'f23', 'h22', 'h23'],
];

$source = static fn (string $site, string $title, string $url): array => [
    'name' => $site,
    'title' => $title,
    'url' => $url,
    'license' => 'All rights reserved',
    'license_url' => $url,
    'adapted' => false,
];

$topic = static function (
    string $site,
    string $sourcePath,
    string $title,
    string $category,
    string $slug,
    string $kind,
    array $tags,
    array $appliesTo,
    string $complexity = 'intermediate',
    ?string $sourceTitle = null,
    array $additionalSources = [],
) use ($source): array {
    $url = $site === 'Nthefastlane'
        ? 'https://www.nthefastlane.com/'.ltrim($sourcePath, '/')
        : $sourcePath;

    return [
        'site' => $site,
        'title' => $title,
        'category' => $category,
        'slug' => $slug,
        'kind' => $kind,
        'tags' => $tags,
        'applies_to' => $appliesTo,
        'complexity' => $complexity,
        'sources' => [
            $source($site, $sourceTitle ?? $title, $url),
            ...array_map(
                static fn (array $item): array => $source($site, $item['title'], $item['url']),
                $additionalSources,
            ),
        ],
    ];
};

$topics = [];

// Nthefastlane: every gathered Honda/Acura technical page that is not already represented by
// an article, a storefront page, or a non-Honda page. Closely related specification pages stay
// separate because each engine or transmission family has distinct applicability and data.
$topics[] = $topic('Nthefastlane', 'ac-b-series-ej-coupe', 'Keeping Air Conditioning in a B-Series-Swapped EJ Civic', 'tuning', 'b-series-ej-civic-air-conditioning', 'modification', ['air-conditioning', 'engine-swap', 'b-series', 'civic'], $civicEk, 'advanced', 'Honda B-series Keep A/C In EJ Coupe');
$topics[] = $topic('Nthefastlane', 'ac-compressor-drier-repair-civic', '1996–2000 Honda Civic A/C Compressor and Receiver-Drier Replacement', 'reference', '1996-2000-civic-ac-compressor-drier', 'repair', ['air-conditioning', 'compressor', 'receiver-drier', 'civic'], $civicEk, 'advanced', 'A/C Compressor/Drier Repair Civic "96-00"');
$topics[] = $topic('Nthefastlane', 'ac-drier-condenser-repair-accord', '1994–1998 Honda Accord A/C Condenser and Receiver-Drier Replacement', 'reference', '1994-1998-accord-ac-condenser-drier', 'repair', ['air-conditioning', 'condenser', 'receiver-drier', 'accord'], ['models' => ['accord'], 'chassis' => ['cb-cd']], 'advanced', 'A/C Drier & Condenser Repair Accord');
$topics[] = $topic('Nthefastlane', 'ac-hot-cold-civic-fix', 'Diagnosing Intermittent Hot and Cold A/C in a Honda Civic', 'diagnostics', 'civic-intermittent-ac-temperature', 'diagnostic', ['air-conditioning', 'diagnostics', 'civic'], $civic, 'intermediate', 'A/C Hot & Cold Possible FREE "FIX"');
$topics[] = $topic('Nthefastlane', 'acura-coolant-nipple-delete-tap', 'B-Series Coolant Port Delete and Threaded Fitting Conversion', 'tuning', 'b-series-coolant-port-delete', 'modification', ['cooling-system', 'b-series', 'fabrication', 'integra'], $bSeries, 'advanced', 'Acura GSR Coolant Nipple DELETE & TAP');
$topics[] = $topic('Nthefastlane', 'b-series-torque-specs', 'Honda B-Series Torque Specifications and Tightening Sequences', 'reference', 'b-series-torque-specifications', 'reference', ['b-series', 'torque-specifications', 'engine-rebuild'], $bSeries, 'advanced', 'B-series Torque Specs - Nthefastlane');
$topics[] = $topic('Nthefastlane', 'b-series-transmission-clean-reseal', 'Honda B-Series Manual Transmission Cleaning and Case Resealing', 'transmissions', 'b-series-transmission-reseal', 'repair', ['b-series', 'manual-transmission', 'reseal', 'leak'], $bSeries, 'advanced', 'B-series Transmission Clean & Re-seal');
$topics[] = $topic('Nthefastlane', 'boost-controller-obd1-ecu-chipping', 'OBD1 Honda ECU Hardware for Boost-by-Gear Control', 'ecu', 'obd1-boost-by-gear-hardware', 'modification', ['ecu', 'boost-control', 'boost-by-gear', 'chipping', 'obd1'], $allCars, 'advanced', 'Boost Controller OBD1 ECU Chipping Guide');
$topics[] = $topic('Nthefastlane', 'change-rear-disc-brake-pads-civic', '2005–2011 Honda Civic Rear Disc Brake Pad Replacement', 'reference', '2005-2011-civic-rear-brake-pads', 'repair', ['brakes', 'brake-pads', 'civic'], ['models' => ['civic'], 'years' => ['2005-2011']], 'intermediate', 'Change Rear Disc Brake Pads Civic 05-11');
$topics[] = $topic('Nthefastlane', 'condenser-fan-replacement-civic', '1992–2000 Honda Civic A/C Condenser Fan Diagnosis and Replacement', 'diagnostics', '1992-2000-civic-condenser-fan', 'diagnostic', ['air-conditioning', 'cooling-fan', 'diagnostics', 'civic'], $civic, 'intermediate', 'Condenser Fan Replacement (Honda Civic)');
$topics[] = $topic('Nthefastlane', 'cv-axle-regrease-honda-how-to', 'Honda CV Axle Inspection, Cleaning, and Regreasing', 'transmissions', 'cv-axle-cleaning-regreasing', 'repair', ['cv-axle', 'drivetrain', 'grease', 'inspection'], $allCars, 'intermediate', 'CV Axle Re-Grease Honda "How To"');
$topics[] = $topic('Nthefastlane', 'd-series-torque-specs', 'Honda D-Series Torque Specifications and Tightening Sequences', 'reference', 'd-series-torque-specifications', 'reference', ['d-series', 'torque-specifications', 'engine-rebuild'], ['models' => ['civic', 'crx', 'del-sol'], 'engines' => ['d13', 'd14', 'd15', 'd16', 'd17']], 'advanced', 'D-series Torque Specs - Nthefastlane');
$topics[] = $topic('Nthefastlane', 'diy-honda-braided-fuel-line', 'Building a Braided Honda Fuel Feed Line with an OEM Banjo Fitting', 'fueling', 'braided-fuel-feed-line', 'modification', ['fuel-line', 'braided-hose', 'banjo-fitting', 'fueling'], $allCars, 'advanced', '"DIY" Honda Braided Fuel Line');
$topics[] = $topic('Nthefastlane', 'drive-axles-transmission-seal-honda', 'Honda Drive Axle and Manual Transmission Output Seal Replacement', 'transmissions', 'drive-axle-transmission-output-seals', 'repair', ['cv-axle', 'transmission-seal', 'manual-transmission', 'fluid'], $allCars, 'advanced', 'Drive Axles & Transmission Seals Honda');
$topics[] = $topic('Nthefastlane', 'f-series-torque-specs', 'Honda F-Series Torque Specifications and Tightening Sequences', 'reference', 'f-series-torque-specifications', 'reference', ['f-series', 'torque-specifications', 'engine-rebuild'], $hfSeries, 'advanced', 'F-series Torque Specs - Nthefastlane');
$topics[] = $topic('Nthefastlane', 'front-brake-pads-honda-civic', '1996–2000 Honda Civic Front Brake Pad Replacement', 'reference', '1996-2000-civic-front-brake-pads', 'repair', ['brakes', 'brake-pads', 'civic'], $civicEk, 'intermediate', 'Repair Front Brake Pads Honda Civic 96-00');
$topics[] = $topic('Nthefastlane', 'gauge-cluster-replacement-civic', '1992–2000 Honda Civic Gauge Cluster Removal and Replacement', 'wiring', '1992-2000-civic-gauge-cluster-replacement', 'repair', ['gauge-cluster', 'instrument-panel', 'civic', 'wiring'], $civic, 'intermediate', 'Gauge Cluster Replacement Civic 96-00');
$topics[] = $topic('Nthefastlane', 'h-series-torque-specs', 'Honda H-Series Torque Specifications and Tightening Sequences', 'reference', 'h-series-torque-specifications', 'reference', ['h-series', 'torque-specifications', 'engine-rebuild'], $hfSeries, 'advanced', 'H-series Torque Specs - Nthefastlane');
$topics[] = $topic('Nthefastlane', 'head-gasket-how-to-honda-b-series', 'Honda B-Series Head Gasket Diagnosis and Replacement', 'reference', 'b-series-head-gasket-replacement', 'repair', ['b-series', 'head-gasket', 'engine-repair'], $bSeries, 'advanced', 'Head Gasket How To Honda B-Series');
$topics[] = $topic('Nthefastlane', 'honda-a-series-engine-specs-info', 'Honda A-Series Engine Specifications and Identification', 'reference', 'a-series-engine-specifications', 'reference', ['a-series', 'engine-specifications', 'engine-identification'], ['models' => ['accord', 'prelude'], 'engines' => ['a16', 'a18', 'a20']], 'beginner', 'Honda A-series Engine Specs Information');
$topics[] = $topic('Nthefastlane', 'honda-b-series-engine-specs-info', 'Honda B-Series Engine Specifications and Identification', 'reference', 'b-series-engine-specifications', 'reference', ['b-series', 'engine-specifications', 'engine-identification'], $bSeries, 'beginner', 'Honda B-series Engine Specs Information');
$topics[] = $topic('Nthefastlane', 'honda-b-series-oil-pump-seal', 'Honda B-Series Oil Pump Seal Identification and Replacement', 'reference', 'b-series-oil-pump-seal', 'repair', ['b-series', 'oil-pump', 'oil-leak', 'seal'], $bSeries, 'advanced', 'Honda B-series Oil Pump Seal');
$topics[] = $topic('Nthefastlane', 'honda-bearing-color-thickness-chart', 'Honda Main and Connecting-Rod Bearing Color Thickness Chart', 'reference', 'bearing-color-thickness-chart', 'reference', ['engine-bearings', 'bearing-clearance', 'engine-rebuild', 'reference'], $allCars, 'advanced', 'Honda Bearing Color Thickness Chart');
$topics[] = $topic('Nthefastlane', 'honda-c-series-engine-specs-info', 'Honda C-Series Engine Specifications and Identification', 'reference', 'c-series-engine-specifications', 'reference', ['c-series', 'engine-specifications', 'v6', 'engine-identification'], ['models' => ['legend', 'nsx'], 'engines' => ['c20', 'c25', 'c27', 'c30', 'c32', 'c35']], 'beginner', 'Honda C-series Engine Specs Information');
$topics[] = $topic('Nthefastlane', 'honda-civic-oil-pan-gasket', '1992–2000 Honda Civic Oil Pan Gasket Replacement', 'reference', '1992-2000-civic-oil-pan-gasket', 'repair', ['oil-pan', 'oil-leak', 'gasket', 'civic'], $civic, 'intermediate', 'Replacing Honda Civic 92-00 Oil Pan Gasket');
$topics[] = $topic('Nthefastlane', 'honda-d-series-engine-specs-info', 'Honda D-Series Engine Specifications and Identification', 'reference', 'd-series-engine-specifications', 'reference', ['d-series', 'engine-specifications', 'engine-identification'], ['models' => ['civic', 'crx', 'del-sol'], 'engines' => ['d13', 'd14', 'd15', 'd16', 'd17']], 'beginner', 'Honda D-series Engine Specs Information');
$topics[] = $topic('Nthefastlane', 'honda-f-series-engine-specs-info', 'Honda F-Series Engine Specifications and Identification', 'reference', 'f-series-engine-specifications', 'reference', ['f-series', 'engine-specifications', 'engine-identification'], $hfSeries, 'beginner', 'Honda F-series Engine Specs Information');
$topics[] = $topic('Nthefastlane', 'honda-f-series-transmission-specs', 'Honda F-Series Manual Transmission Specifications', 'transmissions', 'f-series-manual-transmission-specifications', 'reference', ['f-series', 'manual-transmission', 'gear-ratios', 'reference'], $hfSeries, 'intermediate', 'Honda F-series Transmission Specs');
$topics[] = $topic('Nthefastlane', 'honda-fuel-filter-replacement', 'Honda High-Pressure Fuel Filter Replacement', 'fueling', 'high-pressure-fuel-filter-replacement', 'repair', ['fuel-filter', 'fuel-pressure', 'maintenance', 'fueling'], $allCars, 'intermediate', 'Honda Fuel Filter Replacement "How To"');
$topics[] = $topic('Nthefastlane', 'honda-g-series-engine-specs-info', 'Honda G-Series Engine Specifications and Identification', 'reference', 'g-series-engine-specifications', 'reference', ['g-series', 'engine-specifications', 'inline-five', 'engine-identification'], ['models' => ['accord', 'vigor'], 'engines' => ['g20', 'g25']], 'beginner', 'Honda G-series Engine Specs Information');
$topics[] = $topic('Nthefastlane', 'honda-gauge-cluster-leds-swap', '1992–2000 Honda Civic Gauge Cluster LED and Warning-Lens Conversion', 'wiring', '1992-2000-civic-cluster-led-conversion', 'modification', ['gauge-cluster', 'led', 'instrument-panel', 'civic'], $civic, 'intermediate', 'Gauge Cluster LED\'S & Notification Lenses Swap');
$topics[] = $topic('Nthefastlane', 'honda-h-series-engine-specs-info', 'Honda H-Series Engine Specifications and Identification', 'reference', 'h-series-engine-specifications', 'reference', ['h-series', 'engine-specifications', 'engine-identification'], $hfSeries, 'beginner', 'Honda H-series Engine Specs Information');
$topics[] = $topic('Nthefastlane', 'honda-h-series-transmission-specs', 'Honda H-Series Manual Transmission Specifications', 'transmissions', 'h-series-manual-transmission-specifications', 'reference', ['h-series', 'manual-transmission', 'gear-ratios', 'reference'], $hfSeries, 'intermediate', 'Honda H-series Transmission Specs');
$topics[] = $topic('Nthefastlane', 'honda-ignition-switch-module-repair', 'Honda Ignition Control Module Diagnosis and Replacement', 'ignition', 'ignition-control-module-replacement', 'diagnostic', ['ignition-control-module', 'igniter', 'no-start', 'diagnostics'], $allCars, 'intermediate', 'Honda Ignition Switch Module Replacement');
$topics[] = $topic('Nthefastlane', 'honda-j-series-engine-specs-info', 'Honda J-Series Engine Specifications and Identification', 'reference', 'j-series-engine-specifications', 'reference', ['j-series', 'engine-specifications', 'v6', 'engine-identification'], ['models' => ['accord', 'legend', 'odyssey', 'pilot', 'tl'], 'engines' => ['j25', 'j30', 'j32', 'j35', 'j37']], 'beginner', 'Honda J-series Engine Specs Information');
$topics[] = $topic('Nthefastlane', 'honda-k-series-engine-specs-info', 'Honda K-Series Engine Specifications and Identification', 'reference', 'k-series-engine-specifications', 'reference', ['k-series', 'engine-specifications', 'engine-identification'], ['models' => ['accord', 'civic', 'cr-v', 'integra', 'rsx'], 'engines' => ['k20', 'k23', 'k24']], 'beginner', 'Honda K-series Engine Specs Information');
$topics[] = $topic('Nthefastlane', 'honda-l-series-engine-specs-info', 'Honda L-Series Engine Specifications and Identification', 'reference', 'l-series-engine-specifications', 'reference', ['l-series', 'engine-specifications', 'engine-identification'], ['models' => ['fit', 'civic'], 'engines' => ['l12', 'l13', 'l15']], 'beginner', 'Honda L-series Engine Specs Information');
$topics[] = $topic('Nthefastlane', 'honda-n-series-engine-specs-info', 'Honda N-Series Diesel Engine Specifications and Identification', 'reference', 'n-series-diesel-engine-specifications', 'reference', ['n-series', 'diesel', 'engine-specifications', 'engine-identification'], ['models' => ['accord', 'civic', 'cr-v'], 'engines' => ['n16', 'n22']], 'beginner', 'Honda N-series Engine Specs Information');
$topics[] = $topic('Nthefastlane', 'honda-r-series-engine-specs-info', 'Honda R-Series Engine Specifications and Identification', 'reference', 'r-series-engine-specifications', 'reference', ['r-series', 'engine-specifications', 'engine-identification'], ['models' => ['civic', 'cr-v', 'hr-v'], 'engines' => ['r16', 'r18', 'r20']], 'beginner', 'Honda R-series Engine Specs Information');
$topics[] = $topic('Nthefastlane', 'how-to-degree-cams-honda-vtec', 'How to Degree Honda VTEC Camshafts', 'tuning', 'degreeing-vtec-camshafts', 'modification', ['camshaft', 'cam-timing', 'vtec', 'degree-wheel'], $allCars, 'advanced', 'How To Degree Cams "Honda Vtec"');
$topics[] = $topic('Nthefastlane', 'identifying-honda-bseries-trans', 'Identifying Honda B-Series Manual Transmissions', 'transmissions', 'b-series-transmission-identification', 'reference', ['b-series', 'manual-transmission', 'identification', 'case-code'], $bSeries, 'beginner', 'Identifying Honda B-series Transmission');
$topics[] = $topic('Nthefastlane', 'j-series-torque-specs', 'Honda J-Series Torque Specifications and Tightening Sequences', 'reference', 'j-series-torque-specifications', 'reference', ['j-series', 'torque-specifications', 'engine-rebuild'], ['models' => ['accord', 'legend', 'odyssey', 'pilot', 'tl'], 'engines' => ['j30', 'j32', 'j35', 'j37']], 'advanced', 'J-series Torque Specs - Nthefastlane');
$topics[] = $topic('Nthefastlane', 'mistaken-head-gasket-leak-honda', 'Diagnosing a VTEC Spool-Valve Oil Leak Mistaken for a Head-Gasket Leak', 'diagnostics', 'vtec-spool-valve-oil-leak', 'diagnostic', ['vtec', 'oil-leak', 'spool-valve', 'diagnostics'], $bSeries, 'intermediate', 'Mistaken Head Gasket Leak Honda VTEC');
$topics[] = $topic('Nthefastlane', 'oil-pump-how-to-port-shim-honda', 'Honda Oil Pump Porting and Pressure-Relief Shimming', 'tuning', 'oil-pump-porting-pressure-relief-shimming', 'modification', ['oil-pump', 'oil-pressure', 'porting', 'engine-build'], $allCars, 'advanced', 'Oil Pump How To Port & Shim (Honda)');
$topics[] = $topic('Nthefastlane', 'oil-pump-install-honda-b-series', 'Honda B-Series Oil Pump Installation', 'reference', 'b-series-oil-pump-installation', 'repair', ['b-series', 'oil-pump', 'engine-rebuild'], $bSeries, 'advanced', 'Oil Pump Install Honda B-series');
$topics[] = $topic('Nthefastlane', 'power-window-off-track-civic', '1996–2000 Honda Civic Window Glass Off-Track Diagnosis and Repair', 'diagnostics', '1996-2000-civic-window-off-track', 'diagnostic', ['power-window', 'window-track', 'civic', 'diagnostics'], $civicEk, 'intermediate', 'Power Window: Off Track Solution (Civic)');
$topics[] = $topic('Nthefastlane', 'spark-plug-gap-for-low-high-boost', 'Spark Plug Gap Selection for Low- and High-Boost Honda Engines', 'ignition', 'spark-plug-gap-for-boost', 'reference', ['spark-plug', 'forced-induction', 'boost', 'ignition'], $allCars, 'intermediate', 'Spark Plug Gap For Low And High Boost');
$topics[] = $topic('Nthefastlane', 'stop-honda-civic-beeping-noise', '1996–2000 Honda Civic Key-in-Ignition Door Chime Diagnosis and Disable Options', 'wiring', '1996-2000-civic-door-chime', 'modification', ['door-chime', 'ignition-key', 'civic', 'wiring'], $civicEk, 'beginner', 'Stop Honda Civic\'s Door Beeping Noise');
$topics[] = $topic('Nthefastlane', 'thermostat-bypass-hose-honda', 'Honda B-Series Thermostat Bypass Hose Replacement', 'reference', 'b-series-thermostat-bypass-hose', 'repair', ['b-series', 'cooling-system', 'thermostat', 'hose'], $bSeries, 'intermediate', 'Thermostat Bypass Hose Honda');
$topics[] = $topic('Nthefastlane', 'thermostat-replacement-honda-acura', 'Honda and Acura Thermostat Diagnosis and Replacement', 'reference', 'thermostat-diagnosis-replacement', 'repair', ['thermostat', 'cooling-system', 'overheating', 'maintenance'], $allCars, 'intermediate', 'Thermostat Replacement: Honda/Acura');
$topics[] = $topic('Nthefastlane', 'timing-belt-water-pump-honda-b', 'Honda B-Series Timing Belt and Water Pump Replacement', 'reference', 'b-series-timing-belt-water-pump', 'repair', ['b-series', 'timing-belt', 'water-pump', 'maintenance'], $bSeries, 'advanced', 'Timing Belt & Water Pump Honda B-series');
$topics[] = $topic('Nthefastlane', 'transmission-clutch-civic-integra', 'Honda B-Series Manual Transmission and Clutch Removal and Installation', 'transmissions', 'b-series-transmission-clutch-replacement', 'repair', ['b-series', 'manual-transmission', 'clutch', 'civic', 'integra'], $bSeries, 'advanced', 'Transmission & Clutch (Civic,Integra,B18)');
$topics[] = $topic('Nthefastlane', 'valve-lash-adjustment-civic-integra', 'Honda Civic and Integra Valve Lash Inspection and Adjustment', 'reference', 'civic-integra-valve-lash-adjustment', 'repair', ['valve-lash', 'valvetrain', 'civic', 'integra'], ['models' => ['civic', 'integra']], 'intermediate', 'Valve Lash Adjustment (Civic/Integra)');
$topics[] = $topic('Nthefastlane', 'window-regulator-replace-civic', '1996–2000 Honda Civic Coupe Window Regulator Replacement', 'reference', '1996-2000-civic-coupe-window-regulator', 'repair', ['power-window', 'window-regulator', 'civic'], $civicEk, 'intermediate', 'Window Regulator: How To Replace Civic');

// Icelord: static Honda reference pages plus technical WordPress series. Reposts and numbered
// build-log installments are merged into one article scaffold with every gathered part retained
// as source metadata.
$topics[] = $topic('Icelord', 'https://icelord.net/honda/vtec/todo.html', 'H23 Block and H22 VTEC Cylinder Head Hybrid Conversion', 'tuning', 'h23-h22-vtec-hybrid-conversion', 'modification', ['h22', 'h23', 'vtec', 'engine-build', 'prelude'], $prelude + ['engines' => ['h22', 'h23']], 'advanced', 'H23VTEC Swap how-to');
$topics[] = $topic('Icelord', 'https://icelord.net/honda/gates.html', 'Honda Prelude Accessory, Timing, and Balance-Shaft Belt Application Guide', 'reference', 'prelude-belt-application-guide', 'reference', ['prelude', 'timing-belt', 'accessory-belt', 'parts-reference'], $prelude, 'beginner', 'Gates belt applications for Honda Prelude');
$topics[] = $topic('Icelord', 'https://icelord.net/honda/area/', 'Honda Market Destination Area Codes', 'reference', 'honda-market-destination-area-codes', 'reference', ['market-code', 'area-code', 'vehicle-identification', 'reference'], $allCars, 'beginner', 'Honda area codes');
$topics[] = $topic('Icelord', 'https://icelord.net/honda/ngk.html', 'Honda NGK Spark Plug Application and Gap Guide', 'ignition', 'ngk-spark-plug-application-guide', 'reference', ['spark-plug', 'ngk', 'plug-gap', 'parts-reference'], $allCars, 'beginner', 'NGK spark plugs for Honda', [
    ['title' => 'NGK Part Finder for 1993 Honda Prelude Si H23A2', 'url' => 'https://icelord.net/honda/my/ngk_bb2.html'],
]);
$topics[] = $topic('Icelord', 'https://icelord.net/honda/caraudio/subwoofer/', 'Fourth-Generation Honda Prelude Stealth Subwoofer Enclosure', 'reference', 'fourth-gen-prelude-stealth-subwoofer', 'modification', ['prelude', 'car-audio', 'subwoofer', 'enclosure'], $prelude, 'intermediate', 'Honda Prelude Generation IV stealth subwoofer');
$topics[] = $topic('Icelord', 'https://icelord.net/honda/my/xenon/', 'Fourth-Generation Prelude Projector Headlamp Retrofit and Beam Verification', 'wiring', 'fourth-gen-prelude-projector-headlamp-retrofit', 'modification', ['prelude', 'headlamp', 'projector', 'hid', 'led'], $prelude, 'advanced', 'High Intensity Discharge Lighting', [
    ['title' => 'Prelude lighting retrofit', 'url' => 'https://home.icelord.net/wordpress/?p=16978'],
]);
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=11972', 'Honda Prelude Headlamp Washer Retrofit', 'wiring', 'prelude-headlamp-washer-retrofit', 'modification', ['prelude', 'headlamp-washer', 'wiring', 'washer-pump'], $prelude, 'advanced', 'омывалочка');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=11694', 'JDM Honda 180 km/h Speed-Limiter VSS Signal Conditioner', 'wiring', 'jdm-speed-limiter-vss-signal-conditioner', 'modification', ['jdm', 'speed-limiter', 'vss', 'signal-conditioner', 'wiring'], $allCars, 'advanced', 'JDM HONDA 180km/h Speed Limiter remover');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=11842', 'Honda Prelude External Automatic-Transmission Cooler Installation', 'transmissions', 'prelude-external-automatic-transmission-cooler', 'modification', ['prelude', 'automatic-transmission', 'transmission-cooler', 'fluid'], $prelude, 'advanced', 'Custom Automatic Transmission Cooler', [
    ['title' => 'Automatic-transmission thermostat, filter, and cooler', 'url' => 'https://home.icelord.net/wordpress/?p=17314'],
]);
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=12031', 'Honda Oxygen Sensor OEM and NGK/NTK Cross-Reference Guide', 'sensors', 'oxygen-sensor-oem-ntk-cross-reference', 'reference', ['oxygen-sensor', 'ntk', 'ngk', 'parts-reference'], $allCars, 'beginner', 'экономим семейный бюджет');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=12055', 'Honda Prelude Equal-Length Axle and Intermediate-Shaft Conversion', 'transmissions', 'prelude-equal-length-axle-conversion', 'modification', ['prelude', 'cv-axle', 'intermediate-shaft', 'automatic-transmission'], $prelude, 'advanced', 'Equal-length axle conversion', [
    ['title' => 'Equal-length axle conversion seal note', 'url' => 'https://home.icelord.net/wordpress/?p=12058'],
    ['title' => 'Automatic-to-manual intermediate-shaft conversion', 'url' => 'https://home.icelord.net/wordpress/?p=17002'],
]);
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=12108', 'JDM Automatic Climate-Control Retrofit for a Left-Hand-Drive Prelude', 'wiring', 'prelude-jdm-automatic-climate-control-retrofit', 'modification', ['prelude', 'climate-control', 'jdm', 'wiring'], $prelude, 'advanced', 'JDM climate control in a left-hand-drive car', [
    ['title' => 'JDM climate-control component inventory', 'url' => 'https://home.icelord.net/wordpress/?p=16934'],
    ['title' => 'JDM climate-control preparation', 'url' => 'https://home.icelord.net/wordpress/?p=16939'],
    ['title' => 'JDM climate control in Euro Prelude: first steps', 'url' => 'https://home.icelord.net/wordpress/?p=16983'],
    ['title' => 'JDM climate control in Euro Prelude: internal components', 'url' => 'https://home.icelord.net/wordpress/?p=16984'],
]);
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=16940', 'Distributorless Coil-on-Plug Conversion for Honda H- and F-Series Engines', 'ignition', 'h-f-series-coil-on-plug-conversion', 'modification', ['coil-on-plug', 'h-series', 'f-series', 'ignition', 'microcontroller'], $hfSeries, 'advanced', 'CoilOnPlug for H- and F-series engines', array_map(
    static fn (int $id): array => ['title' => "Coil-on-plug development part {$id}", 'url' => "https://home.icelord.net/wordpress/?p={$id}"],
    [16941, 16942, 16950, 16951, 16952, 16980, 16989, 16991, 17013, 17015, 17041, 17051, 17204],
));
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=16943', 'Honda OBD1 ECU Solenoid Driver Transistor Diagnosis and Repair', 'ecu', 'obd1-ecu-solenoid-driver-repair', 'diagnostic', ['ecu', 'solenoid-driver', 'transistor', 'board-repair', 'obd1'], $allCars, 'advanced', 'Ремонт ECU двигателя OBD-1');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=16956', 'Honda H22A P13 and P5M Camshaft Comparison', 'reference', 'h22a-p13-p5m-camshaft-comparison', 'reference', ['h22a', 'camshaft', 'p13', 'p5m', 'prelude'], $prelude + ['engines' => ['h22a']], 'intermediate', 'Отличия валов Черноголового P13 и Красноголового P5M — H22A');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=16957', 'Plastic Headlamp Lens Wet-Sanding and UV-Resistant Refinishing', 'reference', 'headlamp-lens-wet-sanding-uv-refinish', 'repair', ['headlamp', 'lens-restoration', 'wet-sanding', 'uv-coating'], $allCars, 'intermediate', 'Полировка фар — это просто');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=16964', 'Honda H- and F-Series Balance-Shaft Delete and Oil-Passage Plugging', 'tuning', 'h-f-series-balance-shaft-delete', 'modification', ['balance-shaft', 'h-series', 'f-series', 'oil-pump', 'engine-build'], $hfSeries, 'advanced', 'Удаляй баланс-валы правильно', array_map(
    static fn (int $id): array => ['title' => "Balance-shaft delete series part {$id}", 'url' => "https://home.icelord.net/wordpress/?p={$id}"],
    [16965, 16967, 16973],
));
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=16966', 'Aged Honda Engine Harness Cleaning, Inspection, and Rewrapping', 'wiring', 'engine-harness-cleaning-rewrapping', 'repair', ['engine-harness', 'wiring', 'connector-inspection', 'rewrap'], $prelude, 'intermediate', 'Реновация моторной косы');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=16969', 'Honda H22/H23 Cylinder Head Inspection and Rebuild', 'reference', 'h22-h23-cylinder-head-rebuild', 'repair', ['h22', 'h23', 'cylinder-head', 'valves', 'engine-rebuild'], $prelude + ['engines' => ['h22', 'h23']], 'advanced', 'Ребилд головы');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=16972', 'Static Weight Matching of Connecting Rod and Piston Assemblies', 'reference', 'rod-piston-static-weight-matching', 'repair', ['connecting-rod', 'piston', 'balancing', 'engine-rebuild'], $allCars, 'advanced', 'Статическая балансировка шатунно-поршневой группы');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=16977', 'Honda Door Hinge Pin and Bushing Repair Kit Installation', 'reference', 'honda-door-hinge-pin-bushing-repair', 'repair', ['door-hinge', 'hinge-pin', 'bushing', 'body-repair'], $allCars, 'intermediate', 'Полезняшки: ремкомплект дверных петель');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=16928', 'Honda Prelude Interior and Instrument LED Bulb Conversion', 'wiring', 'prelude-interior-instrument-led-conversion', 'modification', ['prelude', 'led', 'instrument-cluster', 'interior-lighting'], $prelude, 'intermediate', 'LEDы всякие разные', [
    ['title' => 'Instrument and control LED conversion', 'url' => 'https://home.icelord.net/wordpress/?p=16929'],
    ['title' => 'Indiglo and LED illumination', 'url' => 'https://home.icelord.net/wordpress/?p=16930'],
    ['title' => 'Control-button LED replacement', 'url' => 'https://home.icelord.net/wordpress/?p=16931'],
    ['title' => 'Instrument-cluster LED boards', 'url' => 'https://home.icelord.net/wordpress/?p=16954'],
]);
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=16982', 'Non-Contact Brake Rotor Temperature Sensor Installation', 'sensors', 'non-contact-brake-rotor-temperature-sensor', 'modification', ['brakes', 'temperature-sensor', 'mlx90614', 'instrumentation'], $prelude, 'advanced', 'PIR сенсор температуры тормозных дисков');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=16938', 'Custom Honda Prelude Auxiliary Gauge and Sensor Display', 'wiring', 'prelude-custom-auxiliary-gauge-display', 'modification', ['prelude', 'gauge-cluster', 'oil-pressure', 'egt', 'afr', 'microcontroller'], $prelude, 'advanced', 'Колхозная приборка', array_map(
    static fn (int $id): array => ['title' => "Custom gauge project part {$id}", 'url' => "https://home.icelord.net/wordpress/?p={$id}"],
    [16944, 16945, 16946, 16947, 16949, 16986, 17102],
));
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=16937', 'Microcontroller-Based Digital Odometer for a Honda Prelude', 'wiring', 'prelude-digital-odometer', 'modification', ['prelude', 'odometer', 'vss', 'microcontroller', 'instrument-cluster'], $prelude, 'advanced', 'Цифровой одометр', [
    ['title' => 'Digital odometer part 2', 'url' => 'https://home.icelord.net/wordpress/?p=16987'],
]);
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=16999', 'Honda Turn-Signal Flasher Modification for LED Bulbs', 'wiring', 'led-turn-signal-flasher-modification', 'modification', ['turn-signal', 'flasher-relay', 'led', 'wiring'], $allCars, 'advanced', 'Переделка реле поворотов под светодиоды');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=17003', 'Honda H22A Oil Temperature Sensor Installation', 'sensors', 'h22a-oil-temperature-sensor-installation', 'modification', ['h22a', 'oil-temperature', 'sensor', 'prelude'], $prelude + ['engines' => ['h22a']], 'advanced', 'Установка датчика температуры масла на H22A');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=17306', 'Honda Prelude ATTS Delete Intermediate-Shaft Adapter Plate', 'transmissions', 'prelude-atts-delete-adapter-plate', 'modification', ['prelude', 'atts', 'intermediate-shaft', 'fabrication'], $prelude, 'advanced', 'Переходная пластина ATTS (ATTS delete plate) часть 1', [
    ['title' => 'ATTS delete plate part 2', 'url' => 'https://home.icelord.net/wordpress/?p=17307'],
]);
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=17305', 'Exhaust Header and Downpipe Heat-Wrap Installation', 'reference', 'exhaust-heat-wrap-installation', 'modification', ['exhaust', 'heat-wrap', 'thermal-management', 'installation'], $allCars, 'intermediate', 'Термо-лента на выпуск');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=17310', 'Honda Prelude Hood Release Cable End Repair', 'reference', 'prelude-hood-release-cable-end-repair', 'repair', ['prelude', 'hood-release', 'control-cable', 'body-repair'], $prelude, 'intermediate', 'Ремонт тросика приводного механизма');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=17382', 'Fourth-Generation Honda Prelude Fuel Tank Removal, Inspection, and Replacement', 'fueling', 'fourth-gen-prelude-fuel-tank-replacement', 'repair', ['prelude', 'fuel-tank', 'corrosion', 'fueling'], $prelude, 'advanced', 'Ремонт бака', array_map(
    static fn (int $id): array => ['title' => "Fuel-tank replacement series part {$id}", 'url' => "https://home.icelord.net/wordpress/?p={$id}"],
    [17383, 17388, 17390, 17391, 17392, 17400],
));
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=17362', 'Universal Rubber Bumper Lip Installation on a Honda Prelude', 'reference', 'prelude-universal-rubber-bumper-lip', 'modification', ['prelude', 'bumper-lip', 'body', 'installation'], $prelude, 'beginner', 'Силиконовые губы');
$topics[] = $topic('Icelord', 'https://home.icelord.net/wordpress/?p=17400', 'Fourth-Generation Honda Prelude Exhaust System Replacement and Leak Testing', 'diagnostics', 'fourth-gen-prelude-exhaust-replacement-leak-test', 'repair', ['prelude', 'exhaust', 'leak-test', 'muffler'], $prelude, 'advanced', 'Закончил одно, и продолжим', [
    ['title' => 'Exhaust replacement continuation', 'url' => 'https://home.icelord.net/wordpress/?p=17401'],
    ['title' => 'Exhaust installation and leak test', 'url' => 'https://home.icelord.net/wordpress/?p=17402'],
]);

// These gathered pages were deliberately not converted into drafts because the named article
// already provides the same answer. This is semantic de-duplication, not only a slug check.
$knownDuplicateSources = [
    'Nthefastlane: Honda OBD0/OBD1 ECU Pinouts' => 'cars/wiring/obd0-pm6-pinout',
    'Nthefastlane: Honda OBD2A/OBD2B ECU Pinouts' => 'cars/wiring/obd2a-obd2b-pinouts',
    'Nthefastlane: K-series Torque Specs' => 'cars/engines/k-series/torque-specs',
    'Nthefastlane: Honda manual transmission ratio material' => 'cars/transmissions/manual-gear-ratios',
    'Nthefastlane: main relay no-start material' => 'cars/diagnostics/main-relay-repair',
    'Nthefastlane: OBD1/OBD2 troubleshooting codes' => 'error-codes widget and existing diagnostics articles',
    'Nthefastlane: ECU list' => 'existing ECU identification and definition-code articles',
    'Nthefastlane: Civic ECU chipping' => 'existing OBD1 chipping articles',
    'Nthefastlane: TPS installation/calibration' => 'cars/wiring/throttle-position-sensor-adjustment',
    'Nthefastlane: VTEC operation and engagement troubleshooting' => 'cars/wiring/vtec-solenoid',
    'Nthefastlane: crank-position no-start diagnosis' => 'cars/ignition/crankshaft-position-sensor',
    'Nthefastlane: electronic boost-controller installation' => 'cars/tuning/electronic-boost-controller',
    'Icelord: P14 chipping and dual-ROM material' => 'cars/ecu/p14 and cars/tuning/dual-roms',
    'Icelord: Homebrew Honda ECU RTP material' => 'existing RTP and datalogging articles',
    'Icelord: knock-board retrofit material' => 'existing knock-board and knock-sensor articles',
];

$bodyFor = static function (array $item): string {
    $title = $item['title'];
    $verification = <<<'MARKDOWN'
> [!IMPORTANT]
> This is a source-attributed article scaffold. Confirm all model years, part numbers, measurements, torque values, wiring, fluid specifications, and safety steps against the applicable factory service manual and current manufacturer data before submission.
MARKDOWN;

    if ($item['kind'] === 'reference') {
        return <<<MARKDOWN
# {$title}

{$verification}

## Scope and Applicability

- TODO: Define the exact models, years, chassis, engine or transmission variants, and market differences covered.
- TODO: Record exclusions and known mid-generation changes.

## Identification

- TODO: Explain how to identify the relevant component or variant before relying on the data.
- TODO: Add casting numbers, labels, connector details, or other visual identifiers where applicable.

## Specifications

| Item | Specification | Applies to | Verification source |
| :--- | :--- | :--- | :--- |
| TODO | TODO | TODO | Factory service information required |

## Variant and Interchange Notes

- TODO: Separate confirmed interchangeability from visually similar parts.
- TODO: Note regional, trim, transmission, and production-date differences.

## How to Use This Reference

1. TODO: Identify the vehicle and component variant.
2. TODO: Cross-check the applicable row against a primary technical source.
3. TODO: Record any measurement conditions, unit conversions, or tightening sequence.

## Verification Checklist

- [ ] Applicability confirmed against factory information.
- [ ] Units and conversions independently checked.
- [ ] Part numbers checked for supersession.
- [ ] Safety-critical data reviewed by a second contributor.
MARKDOWN;
    }

    if ($item['kind'] === 'diagnostic') {
        return <<<MARKDOWN
# {$title}

{$verification}

## Symptoms

- TODO: List the specific symptoms this diagnosis addresses.
- TODO: Separate similar symptoms that require a different diagnostic path.

## Applicability and Prerequisites

- TODO: Confirm models, years, engines, wiring variants, and required baseline checks.
- TODO: Record relevant DTCs without treating a code as proof of a failed part.

## Tools and Test Conditions

- TODO: List meters, pressure gauges, scan tools, service information, and back-probing equipment.
- TODO: Define engine temperature, key position, battery voltage, and connector state for each test.

## Diagnostic Procedure

1. TODO: Perform the least invasive visual and baseline checks.
2. TODO: Verify power, ground, signal, pressure, or mechanical condition as applicable.
3. TODO: Compare readings with verified specifications.
4. TODO: Isolate wiring, control-unit, and component faults before replacing parts.

## Results and Next Actions

| Test result | Likely cause | Next check |
| :--- | :--- | :--- |
| TODO | TODO | TODO |

## Repair Verification

- TODO: Clear stored faults only after recording them.
- TODO: Repeat the failed test and confirm operation through a complete drive or heat cycle.
MARKDOWN;
    }

    if ($item['kind'] === 'repair') {
        return <<<MARKDOWN
# {$title}

{$verification}

## Symptoms and Diagnosis

- TODO: Explain how to confirm the part or assembly actually requires service.
- TODO: List common misdiagnoses and related components to inspect first.

## Applicability

- TODO: Confirm models, years, chassis, engine or transmission variants, and regional differences.

## Parts, Tools, and Consumables

- TODO: List verified OEM part numbers and acceptable equivalents.
- TODO: List required tools, fluids, sealants, replacement fasteners, and personal protective equipment.

## Preparation and Safety

> [!WARNING]
> TODO: Add procedure-specific hazards, lifting points, pressure-release steps, electrical isolation, fluid handling, and fire precautions.

1. TODO: Place the vehicle in a safe service condition.
2. TODO: Record settings, routing, and connector locations before disassembly.

## Removal and Inspection

1. TODO: Add the verified removal sequence.
2. TODO: Identify one-time-use fasteners, seals, clips, and alignment marks.
3. TODO: Measure and inspect related components before installing replacements.

## Installation

1. TODO: Add the verified installation sequence.
2. TODO: Include tightening order, torque values, fluid specifications, and adjustment values only after primary-source verification.

## Post-Repair Checks

- [ ] All connectors, hoses, grounds, guards, and fasteners restored.
- [ ] Fluids filled and leaks checked under operating conditions.
- [ ] Repair verified through the required heat cycle, road test, or functional test.
MARKDOWN;
    }

    return <<<MARKDOWN
# {$title}

{$verification}

## Goal and Applicability

- TODO: Define the problem this modification solves and the exact supported vehicle configurations.
- TODO: State when a factory or commercially engineered alternative is preferable.

## Compatibility and Design Requirements

- TODO: Verify mechanical fit, electrical loading, materials, temperature range, pressure range, and fail-safe behavior.
- TODO: Identify every factory function that may be changed or lost.

## Parts, Tools, and Fabrication

- TODO: Add verified component specifications and part numbers.
- TODO: Add required tools, test equipment, fabrication processes, and personal protective equipment.

## Procedure

> [!WARNING]
> TODO: Document modification-specific risks, legal restrictions, and a safe return-to-stock plan before adding procedural detail.

1. TODO: Record the original configuration and baseline measurements.
2. TODO: Add the verified mechanical, wiring, or fabrication sequence.
3. TODO: Protect wiring, hoses, fasteners, and modified surfaces for the operating environment.

## Calibration and Validation

1. TODO: Define initial power-up or first-start checks.
2. TODO: Record expected readings and acceptable tolerances.
3. TODO: Test failure modes as well as normal operation.

## Safety, Reliability, and Reversibility

- [ ] No safety system or required road equipment has been defeated.
- [ ] Heat, vibration, current, pressure, and clearance margins verified.
- [ ] Modification is documented and can be safely reversed or serviced.
MARKDOWN;
};

$publishedTitles = [];
$contentRoot = rtrim((string) config('hondabase.content_path'), '/');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($contentRoot, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if (! $file->isFile() || $file->getExtension() !== 'md' || str_contains($path, '/pt/') || str_contains($path, '/_partials/')) {
        continue;
    }
    $raw = file_get_contents($path);
    if (preg_match('/^#\s+(.+)$/m', (string) $raw, $match)) {
        $publishedTitles[Str::lower(preg_replace('/[^\pL\pN]+/u', '', $match[1]))] = $path;
    }
}

$seen = [];
$created = [];
$skipped = [];

DB::transaction(function () use ($topics, $bodyFor, $user, $service, $publishedTitles, &$seen, &$created, &$skipped, $apply): void {
    foreach ($topics as $item) {
        $key = "cars/{$item['category']}/{$item['slug']}";
        if (isset($seen[$key])) {
            throw new RuntimeException("Duplicate manifest path: {$key}");
        }
        $seen[$key] = true;

        if ($service->exists('cars', $item['category'], $item['slug'])) {
            $skipped[] = "{$key} (published path exists)";

            continue;
        }

        $normalizedTitle = Str::lower(preg_replace('/[^\pL\pN]+/u', '', $item['title']));
        if (isset($publishedTitles[$normalizedTitle])) {
            $skipped[] = "{$key} (published title exists at {$publishedTitles[$normalizedTitle]})";

            continue;
        }

        if (ArticleDraft::query()->where('type', 'cars')->where('category', $item['category'])->where('slug', $item['slug'])->exists()) {
            $skipped[] = "{$key} (draft path exists)";

            continue;
        }

        $summary = Str::limit("Article scaffold for {$item['title']}.", 158, '.');
        $document = ArticleDocument::compose([
            'summary' => $summary,
            'tags' => $item['tags'],
            'applies_to' => $item['applies_to'],
            'complexity' => $item['complexity'],
            'sources' => $item['sources'],
        ], $bodyFor($item));

        if ($apply) {
            $draft = ArticleDraft::query()->create([
                'user_id' => $user->id,
                'title' => $item['title'],
                'type' => 'cars',
                'category' => $item['category'],
                'slug' => $item['slug'],
                'document' => $document,
                'note' => "{$item['site']} source-inventory scaffold. Write original copy, preserve source attribution, and verify all technical claims against primary service information before submission.",
            ]);
            $created[] = "#{$draft->id} {$key}";
        } else {
            $created[] = "[dry-run] {$key}";
        }
    }
});

echo ($apply ? 'Created' : 'Would create').': '.count($created).PHP_EOL;
echo 'Skipped by path/title/draft check: '.count($skipped).PHP_EOL;
echo 'Curated semantic duplicate source groups: '.count($knownDuplicateSources).PHP_EOL;
echo 'Owner: #'.$user->id.' '.$user->name.PHP_EOL;

foreach ($skipped as $line) {
    echo 'SKIP '.$line.PHP_EOL;
}
foreach ($created as $line) {
    echo 'DRAFT '.$line.PHP_EOL;
}
