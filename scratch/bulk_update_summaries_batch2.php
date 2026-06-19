<?php
require 'vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Article;
use App\Support\ArticleDocument;

$newSummaries = [
    'full-throttle-shift' => 'A guide to implementing Full Throttle Shift (FTS) in Honda OBD1 ECUs, allowing for faster gear changes by maintaining boost and RPM during shifts.',
    '66k-assembler-routines' => 'A collection of Oki 66K assembly language routines and code snippets for modifying Honda OBD1 and OBD2 ECU firmware.',
    'chipping-obd1-small-case' => 'Step-by-step instructions for socketing and chipping small-case Honda OBD1 ECUs, commonly found in JDM and some European models.',
    'disable-vtec-vss-check' => 'Learn how to disable the VTEC Vehicle Speed Sensor (VSS) check in Honda OBD1 ECUs, enabling VTEC engagement without a working speed signal.',
    'ect' => 'Technical overview of the Engine Coolant Temperature (ECT) sensor in Honda engines, including its role in fueling, ignition timing, and cold start enrichment.',
    'electronic-air-control-vale' => 'A detailed explanation of the Electronic Air Control Valve (EACV), also known as the Idle Air Control Valve (IACV), and its function in maintaining Honda idle speed.',
    'engine-simulator' => 'Guide to using or building an engine simulator to bench-test Honda OBD0 and OBD1 ECUs without a vehicle.',
    'erm' => 'Information on the UberData Engine Management (ERM) system, a legacy tuning solution for Honda OBD1 ECUs.',
    'fuel-octane' => 'Understanding fuel octane ratings and their impact on Honda engine performance, knock resistance, and ignition timing optimization.',
    'iat' => 'Technical overview of the Intake Air Temperature (IAT) sensor and its critical role in air density calculations and fueling corrections for Honda ECUs.',
    'io' => 'Basic overview of Input/Output (I/O) ports in Honda ECU microcontrollers and how they interface with engine sensors and actuators.',
    'jdm' => 'A reference for JDM (Japanese Domestic Market) Honda ECUs, highlighting hardware differences such as the lack of ELD and knock sensors compared to USDM models.',
    'latches' => 'Technical explanation of address latches (e.g., 74HC373) used in Honda OBD1 ECUs to interface the MCU with external EPROM memory.',
    'lego-zoo' => 'A historical community page dedicated to the early days of the PGMFI.org wiki and the contributors who helped build the Honda tuning knowledgebase.',
    'p0c' => 'Technical specifications and pinout information for the P0C 92-95 Honda Accord 2.2L OBD1 ECU.',
    'p11' => 'Technical specifications and hardware overview for the P11 92-95 Honda Prelude 2.0i (BB3) OBD1 ECU.',
    'p5m' => 'Hardware analysis and component specifications for the P5M 97+ Honda Prelude 2.2VTi (EDM) OBD2 ECU.',
    'p5p' => 'Overview of the P5P JDM Honda Prelude Type-S OBD2 ECU, including its unique hardware features and performance mapping.',
    'p75' => 'Technical comparison of OBD2 Honda Integra LS/GS P75 ECUs, including their transition from OBD1 and hardware variations.',
    'p76' => 'Technical overview of the P76 JDM Honda Integra SOHC ZC OBD1 ECU and its application in tuning.',
    'p84' => 'Specifications for the P84 JDM Honda Civic ETi OBD1 ECU, designed for VTEC-E fuel economy engines with automatic transmissions.',
    'pa-sensor' => 'Technical guide to the Atmospheric Pressure (PA) sensor in Honda ECUs, explaining its role in altitude compensation and fueling adjustments.',
    'pr7' => 'Hardware overview and technical specifications for the PR7 91-94 Honda NSX OBD1 ECU.',
    'pt5' => 'Analysis of the PT5 EDM Honda Accord OBD1 ECU, covering its shared PCB architecture with other Accord units.',
    'pwm' => 'Explanation of Pulse Width Modulation (PWM) and its use in Honda ECUs for controlling solenoids like the IACV, boost controllers, and VTEC.',
    'rtfm' => 'A humorous but important reminder to consult the available documentation and manuals before performing complex Honda ECU modifications.',
    'sectors' => 'Understanding memory sectors in EPROMs and Flash chips used for Honda ECU tuning and data storage.',
    'service-manual' => 'Information on obtaining and using factory service manuals (Helms) for accurate Honda vehicle wiring and mechanical repair.',
    'uber-data' => 'Historical overview of the UberData tuning software, one of the original free platforms for Honda OBD1 ECU remapping.',
    'usdm' => 'Reference for USDM (United States Domestic Market) Honda ECUs, known for their comprehensive feature sets including ELD and knock control.',
    'vss' => 'Technical guide to the Vehicle Speed Sensor (VSS) in Honda vehicles, covering signal types, troubleshooting, and its impact on VTEC.',
    '02d01980-1500' => 'Detailed parts list and hardware modification guide for converting the 02D01980-1500 Honda ECU board for VTEC and IAB support.',
    '5050s' => 'Troubleshooting and installation guide for using 5050s components in Honda VTEC conversion circuits, including MIL code 21 fixes.',
    'edm' => 'Reference for EDM (European Domestic Market) Honda ECUs, detailing common hardware differences such as the lack of an injector test circuit.',
    'maf-sensor' => 'Technical explanation of Mass Air Flow (MAF) sensors and why Honda primarily uses MAP-based speed-density systems instead.',
    'obd1-conversion-formulae' => 'A comprehensive list of mathematical formulas for converting raw ROM hex values into human-readable units like RPM, pressure, and temperature.',
    'obd1-oki66207-reader-plcc68' => 'Advanced guide for building a hardware reader to dump Internal ROM data from PLCC68 Oki 66207 processors found in Honda OBD1 ECUs.',
    'obd1cn2' => 'Pinout and wiring guide for the CN2 serial datalogging port on Honda OBD1 ECUs, essential for real-time tuning and diagnostics.',
    'obd2-oki66507-reader-nico' => 'Technical guide to building a hardware dumper for reading internal memory from Oki 66507 microcontrollers in Honda OBD2 ECUs.',
    'p07' => 'Technical analysis of the P07 Honda Civic VX OBD1 ECU, featuring unique dual-processor architecture for lean-burn control.',
    'pcb' => 'An introduction to Printed Circuit Board (PCB) design and construction as it relates to Honda engine control units.',
    'pgsrc-translation' => 'A reference matrix for translated page names within the PGMFI technical documentation project.',
    'rm11' => 'Technical fix for replacing the RM11 resistor network in Honda ECUs with individual resistors for VTEC conversions.',
    'rom-maps' => 'A collaborative index of memory addresses and ROM maps for Honda OBD1 Civic and Integra ECUs, essential for firmware reverse engineering.',
    'rtp-project' => 'Information on the Real-Time Programming (RTP) project, aiming to enable live ECU tuning for Honda OBD1 platforms.',
];

$updatedCount = 0;

foreach ($newSummaries as $slug => $summary) {
    $article = Article::where('slug', $slug)->where('locale', 'en')->first();
    if (!$article) continue;
    
    $repoPath = 'content/' . $article->repo_path;
    if (!file_exists($repoPath)) continue;
    
    $raw = file_get_contents($repoPath);
    $doc = ArticleDocument::parse($raw);
    
    $doc['fm']['summary'] = $summary;
    $newRaw = ArticleDocument::compose($doc['fm'], $doc['body']);
    
    file_put_contents($repoPath, $newRaw);
    
    // Update DB
    $article->summary = $summary;
    $article->save();
    $updatedCount++;
}

echo "Successfully updated $updatedCount English articles with SEO-friendly summaries.\n";
