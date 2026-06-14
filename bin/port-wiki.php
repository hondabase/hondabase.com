<?php
/**
 * Port + adapt pgmfi wiki topics (DB: pgmfi_wiki_archive) into Hondabase article bundles.
 *
 * Adapts to the Hondabase format (content/docs/article-format.md), not legacy wiki
 * semantics: strips TWiki auto-link soup (repointing to our articles where they exist),
 * normalizes terminology casing, and injects curated `applies_to` frontmatter.
 *
 *   content/cars/electronics/<slug>/<slug>.md  + co-located image assets
 *
 * Usage:
 *   php bin/port-wiki.php --dry-run [--slug=ecu] [--limit=5]
 *   php bin/port-wiki.php --write [--all-electronics]
 */

require __DIR__ . '/../vendor/autoload.php';

use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Yaml\Yaml;

$opt      = getopt('', ['dry-run', 'write', 'all-electronics', 'slug:', 'limit:']);
$dry      = !isset($opt['write']);
$onlySlug = $opt['slug']  ?? null;
$limit    = isset($opt['limit']) ? (int) $opt['limit'] : 0;
$allElec  = isset($opt['all-electronics']);

$ROOT       = dirname(__DIR__);
$MEDIA_BASE = $ROOT . '/public/pgmfi/wiki/media';
$OUT_BASE   = $ROOT . '/content/cars/electronics';

// Hand-maintained articles the porter must not overwrite.
$SKIP = ['knock-sensor'];

// Curated frontmatter per slug. engines: no-digit value = whole family (B-Series), with a
// digit = specific engine (B18C).
$FM = [
    'ecu'                          => ['summary' => 'The engine control unit reads the sensors and drives fuel, ignition and idle on Honda PGM-FI engines.', 'obd' => [0, 1, 2], 'scope' => 'all-honda-cars', 'complexity' => 'beginner', 'tags' => ['ecu', 'pgm-fi']],
    'ecu-sensors'                  => ['summary' => 'The input sensors a Honda ECU reads to run fuel and ignition.', 'obd' => [0, 1, 2], 'complexity' => 'beginner', 'tags' => ['ecu', 'sensors']],
    'ecu-hardware'                 => ['summary' => 'Inside a Honda ECU: the boards, chips and circuits.', 'obd' => [0, 1], 'complexity' => 'advanced', 'tags' => ['ecu', 'hardware']],
    'ecu-trouble-codes'            => ['summary' => 'Reading and interpreting OBD1 ECU diagnostic trouble codes.', 'obd' => [1], 'complexity' => 'beginner', 'tags' => ['ecu', 'obd1', 'diagnostics']],
    'ecu-troubleshooting'          => ['summary' => 'Diagnosing a Honda ECU that will not run the car.', 'obd' => [0, 1], 'complexity' => 'intermediate', 'tags' => ['ecu', 'diagnostics']],
    'knock-sensor-voltages'        => ['summary' => 'Expected signal voltages from the knock sensor on a running engine.', 'obd' => [1, 2], 'engines' => ['B-Series', 'H-Series'], 'complexity' => 'advanced', 'tags' => ['knock', 'sensor']],
    'crankshaft-position-sensor'   => ['summary' => 'The crank position (CKP) sensor and how the ECU uses it.', 'tags' => ['sensor', 'ignition']],
    'cylinder-position-sensor'     => ['summary' => 'The cylinder position (CYP) sensor and its role in injection timing.', 'tags' => ['sensor']],
    'intake-air-temperature-sensor' => ['summary' => 'The intake air temperature (IAT) sensor and its effect on fueling.', 'obd' => [0, 1, 2], 'tags' => ['sensor']],
    'exhaust-gas-temp-sensor'      => ['summary' => 'The exhaust gas temperature (EGT) sensor and what it tells you.', 'tags' => ['sensor', 'tuning']],
    'injector-sizing'              => ['summary' => 'How to size fuel injectors for your power target.', 'complexity' => 'intermediate', 'tags' => ['injectors', 'fueling', 'tuning']],
    'high-impedance-injectors'     => ['summary' => 'High-impedance (saturated) injectors and where they are used.', 'tags' => ['injectors']],
    'low-impedance-injectors'      => ['summary' => 'Low-impedance (peak-and-hold) injectors and where they are used.', 'tags' => ['injectors']],
    'ignition-coil'                => ['summary' => 'The ignition coil in Honda distributor ignition.', 'tags' => ['ignition']],
    'introduction-to-ecu-chipping' => ['summary' => 'A practical introduction to socketing and chipping OBD0/OBD1 Honda ECUs.', 'obd' => [0, 1], 'engines' => ['B-Series', 'D-Series'], 'ecus' => ['P28', 'P30', 'PM6'], 'complexity' => 'advanced', 'tags' => ['ecu', 'chipping', 'tuning']],
    'add-a-knock-sensor'           => ['summary' => 'Adding a knock sensor to an engine that did not come with one.', 'obd' => [1], 'complexity' => 'intermediate', 'tags' => ['knock', 'sensor', 'wiring']],
    'obd1-civic-integra-auto-manual' => ['summary' => 'Convert USDM and JDM OBD1 Civic/Integra ECUs between automatic and manual configurations by changing resistor board values.', 'obd' => [1], 'complexity' => 'advanced', 'tags' => ['ecu', 'obd1', 'hardware', 'conversion']],
    'troubleshooting-solid-cel'    => ['summary' => 'How to diagnose a solid Check Engine Light (CEL/MIL) on Honda OBD0/OBD1 ECUs, which usually indicates limp mode or a bad ROM/connection.', 'obd' => [0, 1], 'complexity' => 'intermediate', 'tags' => ['ecu', 'diagnostics', 'troubleshooting']],
    'map-sensor'                   => ['summary' => 'The Manifold Absolute Pressure (MAP) sensor measures engine vacuum and boost, acting as the primary load input for Honda speed-density fueling.', 'obd' => [0, 1, 2], 'complexity' => 'beginner', 'tags' => ['sensor', 'fueling']],
    'tps-sensor'                   => ['summary' => 'How the Throttle Position Sensor (TPS) works, how to calibrate/adjust it, and troubleshooting common TPS issues.', 'obd' => [0, 1, 2], 'complexity' => 'intermediate', 'tags' => ['sensor', 'diagnostics']],
    'oxygen-sensor'                => ['summary' => 'How the stock narrowband oxygen sensor works, troubleshooting O2 sensor CELs, and converting between 1-wire and 4-wire sensors.', 'obd' => [0, 1, 2], 'complexity' => 'intermediate', 'tags' => ['sensor', 'fueling', 'diagnostics']],
    'vtec-solenoid'                => ['summary' => 'The VTEC spool valve solenoid activates high-cam lobe profiles. How the ECU triggers VTEC, pressure switches, and troubleshooting VTEC engagement.', 'obd' => [0, 1, 2], 'complexity' => 'intermediate', 'tags' => ['vtec', 'wiring', 'hardware']],
    'vehicle-speed-sensor'         => ['summary' => 'The Vehicle Speed Sensor (VSS) sends speed pulses to the cluster and ECU. Diagnosing speedo failures and VSS-related VTEC engagement issues.', 'obd' => [0, 1, 2], 'complexity' => 'intermediate', 'tags' => ['sensor', 'diagnostics', 'wiring']],
    'ecu-definition-codes'         => ['summary' => 'A reference table of Honda and Acura OBD0, OBD1, and OBD2 ECU part numbers, their markets, and corresponding code designations.', 'obd' => [0, 1, 2], 'complexity' => 'beginner', 'tags' => ['ecu', 'reference']],
    'ecu-chipping-wirelist'        => ['summary' => 'Pin connections and traces between the MCU, latch, and external EPROM socket on OBD1 Honda ECUs.', 'obd' => [1], 'complexity' => 'advanced', 'tags' => ['ecu', 'chipping', 'hardware', 'wiring']],
    'kurts-obd0-obd1'              => ['summary' => 'Step-by-step instructions for converting an OBD0 chassis wiring harness to run an OBD1 ECU.', 'obd' => [0, 1], 'complexity' => 'advanced', 'tags' => ['conversion', 'wiring', 'hardware']],
    'd16z6-4g-swapnotes'           => ['summary' => 'Detailed notes on swapping an OBD1 D16Z6 engine into a 4th generation (1988-1991) OBD0 Civic or CRX, focusing on mechanicals, distributor, and wiring.', 'obd' => [0, 1], 'complexity' => 'advanced', 'tags' => ['swap', 'vtec', 'wiring', 'engine']],
    'wide-band-o2'                 => ['summary' => 'How wideband oxygen sensors differ from narrowband sensors, integration with aftermarket controllers, and datalogging setups.', 'obd' => [0, 1, 2], 'complexity' => 'intermediate', 'tags' => ['sensor', 'fueling', 'diagnostics']],
    'p30'                          => ['summary' => 'A complete hardware and software reference guide for the JDM and USDM OBD1 P30 DOHC VTEC ECU (from B16A engines).', 'obd' => [1], 'complexity' => 'intermediate', 'tags' => ['ecu', 'vtec', 'reference']],
    'p28'                          => ['summary' => 'A hardware and software reference guide for the USDM and EDM OBD1 P28 SOHC VTEC ECU (from D16Z6 engines), the most popular ECU for custom tuning.', 'obd' => [1], 'complexity' => 'intermediate', 'tags' => ['ecu', 'vtec', 'reference']],
    'ecu-families'                 => ['summary' => 'An overview of different generations and families of Honda engine control units (OBD0, OBD1, and OBD2) and their compatibility.', 'obd' => [0, 1, 2], 'complexity' => 'beginner', 'tags' => ['ecu', 'reference']],
    'crome-faq'                    => ['summary' => 'Frequently asked questions about Crome, a popular ROM editing and tuning software for OBD1 Honda ECUs.', 'obd' => [1], 'complexity' => 'intermediate', 'tags' => ['tuning', 'rom', 'software']],
    'understanding-maps'           => ['summary' => 'An introductory guide explaining how fuel and ignition tables (maps) are structured and interpreted inside Honda ECU ROMs.', 'obd' => [0, 1, 2], 'complexity' => 'beginner', 'tags' => ['tuning', 'rom', 'fueling', 'ignition']],
    'p72'                          => ['summary' => 'A comprehensive hardware and software reference guide for the OBD1 P72 Integra GS-R DOHC VTEC ECU, including RAM and ROM mapping.', 'obd' => [1], 'complexity' => 'intermediate', 'tags' => ['ecu', 'vtec', 'reference']],
    'p13'                          => ['summary' => 'A complete hardware and software reference guide for the OBD1 P13 Prelude DOHC VTEC H22A ECU, covering diagnostic RAM and ROM parameters.', 'obd' => [1], 'complexity' => 'intermediate', 'tags' => ['ecu', 'vtec', 'reference']],
    'pw0'                          => ['summary' => 'A complete hardware and software reference guide for the JDM OBD0 PW0 DOHC VTEC ECU, featuring RAM and ROM address calibrations.', 'obd' => [0], 'complexity' => 'intermediate', 'tags' => ['ecu', 'vtec', 'reference']],
    'pr3'                          => ['summary' => 'Hardware and software reference guide for the OBD0 and OBD1 PR3 DOHC VTEC ECU used in B16A-equipped Integra and Civic models.', 'obd' => [0, 1], 'complexity' => 'intermediate', 'tags' => ['ecu', 'vtec', 'reference']],
    'ecu-tuning'                   => ['summary' => 'An introduction to fuel and ignition tuning on Honda ECUs. Learn how air-fuel ratio, timing advance, and engine load maps are calibrated.', 'obd' => [0, 1, 2], 'complexity' => 'intermediate', 'tags' => ['tuning', 'ecu', 'fueling', 'ignition']],
    'obd'                          => ['summary' => 'An overview of Honda On-Board Diagnostics generations (OBD0, OBD1, OBD2a, OBD2b). Learn their differences, connector layouts, and compatibility.', 'obd' => [0, 1, 2], 'complexity' => 'beginner', 'tags' => ['obd', 'reference', 'wiring']],
    'serial-communication'         => ['summary' => 'Learn how Honda OBD1 ECUs communicate with tuning laptops via serial TTL signals (CN2 header), including pinouts and baud rates.', 'obd' => [1], 'complexity' => 'advanced', 'tags' => ['ecu', 'datalogging', 'serial']],
    'second-gen-usb-to-serial-converter' => ['summary' => 'How to configure and troubleshoot second-generation USB-to-serial converters (such as FTDI chips) for reliable ECU datalogging.', 'obd' => [1], 'complexity' => 'advanced', 'tags' => ['datalogging', 'serial', 'hardware']],
    'tuning-timing'                => ['summary' => 'A guide to safe ignition timing calibration on Honda ECUs. Learn how timing advance affects cylinder pressure and power output.', 'obd' => [0, 1, 2], 'complexity' => 'intermediate', 'tags' => ['tuning', 'ignition', 'maps']],
    'tuning-for-smog'              => ['summary' => 'How to adjust fuel and ignition maps in your Honda ROM to pass tailpipe emissions and smog inspections.', 'obd' => [0, 1, 2], 'complexity' => 'intermediate', 'tags' => ['tuning', 'emissions', 'fueling']],
    'honda-error-codes'            => ['summary' => 'A complete reference list of Honda OBD0, OBD1, and OBD2 diagnostic trouble codes (DTCs), including CEL flash counts and descriptions.', 'obd' => [0, 1, 2], 'complexity' => 'beginner', 'tags' => ['diagnostics', 'obd', 'reference']],
    'air-fuel-ratio'               => ['summary' => 'An introduction to Air-Fuel Ratio (AFR) in internal combustion engines, explaining stoichiometric targets, rich vs. lean conditions, and sensor scales.', 'obd' => [0, 1, 2], 'complexity' => 'beginner', 'tags' => ['fueling', 'tuning', 'sensor']],
    'debugging-data-logging'       => ['summary' => 'Troubleshooting common Honda ECU datalogging issues, including connection drops, noise interference, and incorrect driver configurations.', 'obd' => [1], 'complexity' => 'advanced', 'tags' => ['datalogging', 'diagnostics', 'serial']],
    'turbo-edit'                   => ['summary' => 'An introduction to TurboEdit, the free open-source ROM editing and tuning software for OBD0 Honda ECUs (PM6/PM7 codebases).', 'obd' => [0], 'complexity' => 'intermediate', 'tags' => ['tuning', 'rom', 'software']],
    'basic-concepts'               => ['summary' => 'An introductory guide to electronic engine management concepts for Honda enthusiasts, covering air, fuel, spark, and sensor basics.', 'obd' => [0, 1, 2], 'complexity' => 'beginner', 'tags' => ['tuning', 'ecu', 'reference']],
    'oki6260a'                     => ['summary' => 'Technical specifications and pinout details for the OKI 6260A microcontroller, the central processing unit in OBD1 Honda ECUs.', 'obd' => [1], 'complexity' => 'advanced', 'tags' => ['ecu', 'hardware', 'microcontroller']],
    'upd7004c'                     => ['summary' => 'Pinout and programming details for the NEC uPD7004C Analog-to-Digital Converter (ADC) chip found in OBD0 MPFI Honda ECUs.', 'obd' => [0], 'complexity' => 'advanced', 'tags' => ['ecu', 'hardware', 'adc']],
    '82c55'                        => ['summary' => 'Technical reference for the OKI MSM82C55 / Intel 82C55 Programmable Peripheral Interface chip used in OBD0 and OBD1 Honda ECUs.', 'obd' => [0, 1], 'complexity' => 'advanced', 'tags' => ['ecu', 'hardware', 'interface']],
    'willem'                       => ['summary' => 'Guide to configuring and using Willem EPROM programmers for burning custom ROMs for chipped Honda ECUs.', 'obd' => [0, 1], 'complexity' => 'intermediate', 'tags' => ['chipping', 'hardware', 'rom']],
    'sim'                          => ['summary' => 'How to build and use an engine simulator (ECU stimulator) to bench-test chipped Honda ECUs.', 'obd' => [0, 1, 2], 'complexity' => 'advanced', 'tags' => ['bench-testing', 'hardware', 'diagnostics']],
    'proposed-datalogging-protocol' => ['summary' => 'Detailed specification of the standard datalogging protocol used to communicate with Honda OBD1 ECUs.', 'obd' => [1], 'complexity' => 'advanced', 'tags' => ['datalogging', 'serial', 'protocol']],
    'dlc'                          => ['summary' => 'Overview of the Honda Data Link Connector (DLC), including OBD0 and OBD1 pinouts and communication signals.', 'obd' => [0, 1], 'complexity' => 'intermediate', 'tags' => ['diagnostics', 'wiring', 'serial']],
    'obd0pm6pm7ram-locations'      => ['summary' => 'A reference table of RAM memory address locations for OBD0 PM6 and PM7 Honda ECU codebases.', 'obd' => [0], 'complexity' => 'advanced', 'tags' => ['rom', 'reference', 'obd0']],
    'add-iab-to-p28'                => ['summary' => 'Step-by-step guide to adding Intake Air Bypass (IAB) secondary runner controls to an OBD1 P28 Civic ECU.', 'obd' => [1], 'complexity' => 'advanced', 'tags' => ['ecu', 'hardware', 'iab']],
    'obd0ecuautotomanualwithoutremoveanyhardware' => ['summary' => 'How to convert an automatic OBD0 Honda ECU to manual transmission mode using custom ROM software edits.', 'obd' => [0], 'complexity' => 'advanced', 'tags' => ['ecu', 'rom', 'conversion']],
    'crome-script'                  => ['summary' => 'Comprehensive reference for the Crome ROM editor JavaScript scripting API and plugin development.', 'obd' => [1], 'complexity' => 'advanced', 'tags' => ['tuning', 'rom', 'software']],
    'accord-auto-obd2-obd1'         => ['summary' => 'Wiring and pinout conversion guide to run an automatic OBD2a Accord using an automatic OBD1 Integra ECU and TCU.', 'obd' => [1, 2], 'complexity' => 'advanced', 'tags' => ['conversion', 'wiring', 'hardware']],
    'easy-rtp-v10'                  => ['summary' => 'Installation and assembly guide for the DIY Easy RTP v1.0 real-time programming hardware for Honda ECUs.', 'obd' => [0, 1], 'complexity' => 'advanced', 'tags' => ['hardware', 'rom', 'chipping']],
    'chipping-an88-89ecu'           => ['summary' => 'Socketing and chipping guide for early 1988-1989 Honda Accord and Acura Integra AN88/AN89 OBD0 ECUs.', 'obd' => [0], 'complexity' => 'advanced', 'tags' => ['ecu', 'chipping', 'hardware']],
    'begginers-faq'                 => ['summary' => 'Frequently asked questions and introductory guide to Honda ECU chipping and tuning for beginners.', 'obd' => [0, 1, 2], 'complexity' => 'beginner', 'tags' => ['tuning', 'ecu', 'reference']],
    'parts-for-ec-us'               => ['summary' => 'Part numbers and specifications for transistors, diodes, capacitors, and ICs used on Honda ECU boards.', 'obd' => [0, 1], 'complexity' => 'advanced', 'tags' => ['ecu', 'hardware', 'reference']],
    'uber-data-faq'                 => ['summary' => 'Frequently asked questions and troubleshooting guides for UberData, a legacy OBD1 Honda ROM editor.', 'obd' => [1], 'complexity' => 'intermediate', 'tags' => ['tuning', 'rom', 'software']],
    'obd1-8bit-m-bar'               => ['summary' => 'Calibration tables and mathematical formulas for stock MAP sensor voltage-to-millibar scaling on OBD1 Honda ECUs.', 'obd' => [1], 'complexity' => 'intermediate', 'tags' => ['tuning', 'sensor', 'maps']],
];

// Acronyms to normalize. Multi-form (OBD2..) listed before the base form (OBD).
$CASE = ['OBD0', 'OBD1', 'OBD2', 'OBD', 'VTEC', 'SOHC', 'DOHC', 'ECU', 'ECM', 'TPS', 'IAT',
         'ECT', 'EGR', 'ELD', 'LAF', 'VSS', 'IAC', 'EACV', 'TDC', 'CYP', 'CKP', 'CKF', 'TCU',
         'CEL', 'MIL', 'SCS', 'EGT'];

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

// Slugs that exist (or will exist) as our articles -> wiki links to them become relative.
$ported = array_map('basename', glob("$OUT_BASE/*", GLOB_ONLYDIR) ?: []);
$ported = array_values(array_unique(array_merge($ported, array_keys($FM), $SKIP)));

$conv = new HtmlConverter(['header_style' => 'atx', 'strip_tags' => true, 'remove_nodes' => 'script style', 'hard_break' => true, 'use_autolinks' => false]);
$attStmt = $pdo->prepare('SELECT filename, local_path FROM attachments WHERE topic_id=? ORDER BY filename');

// ----- selection -----
if ($onlySlug) {
    $st = $pdo->prepare("SELECT * FROM topics WHERE web='library' AND is_webhome=0 AND slug=?");
    $st->execute([$onlySlug]);
} elseif ($allElec) {
    $kw = ['ecu', 'sensor', 'injector', 'ignition', 'knock', 'wiring', 'distributor', 'coil', 'electronic', 'obd', 'cel', 'chipping', 'solenoid'];
    $likes = implode(' OR ', array_map(fn ($k) => 'slug LIKE ' . $pdo->quote("%$k%") . ' OR title LIKE ' . $pdo->quote("%$k%"), $kw));
    $st = $pdo->query("SELECT * FROM topics WHERE web='library' AND is_webhome=0 AND ($likes) ORDER BY title");
} else {
    $slugs = array_keys($FM);
    $in    = implode(',', array_fill(0, count($slugs), '?'));
    $st    = $pdo->prepare("SELECT * FROM topics WHERE web='library' AND is_webhome=0 AND slug IN ($in)");
    $st->execute($slugs);
}
$topics = $st->fetchAll();
if ($limit > 0) $topics = array_slice($topics, 0, $limit);

function clean_html(string $h): string {
    $h = str_replace('\\n', "\n", $h);
    return preg_replace('#<p>\s*</p>#i', '', $h);
}

/** Repoint wiki links to our articles where they exist; otherwise drop to plain text. */
function delink(string $md, array $ported): string {
    return preg_replace_callback('#\[([^\]]+)\]\(/pgmfi/wiki/library/([^)\s]+)\s*\)#', function ($m) use ($ported) {
        $text = $m[1];
        $slug = rtrim($m[2], '/');
        return in_array($slug, $ported, true) ? "[$text](/cars/electronics/$slug)" : $text;
    }, $md);
}

/** Normalize acronym casing without touching URLs, slugs or word interiors. */
function fix_casing(string $s, array $terms): string {
    foreach ($terms as $t) {
        $s = preg_replace('/(?<![\w\/-])' . preg_quote($t, '/') . '(?![\w\/-])/i', $t, $s);
    }
    return $s;
}

function tidy_md(string $md): string {
    return trim(preg_replace("/\n{3,}/", "\n\n", $md)) . "\n";
}

function build_frontmatter(array $f): string {
    $at = [];
    foreach (['brand', 'models', 'model', 'chassis', 'trims', 'trim', 'engines', 'ecus', 'obd', 'years', 'scope'] as $k) {
        if (!empty($f[$k])) $at[$k] = $f[$k];
    }
    $data = [];
    if (!empty($f['summary']))    $data['summary']    = $f['summary'];
    if ($at)                      $data['applies_to'] = $at;
    if (!empty($f['complexity'])) $data['complexity'] = $f['complexity'];
    if (!empty($f['tags']))       $data['tags']       = $f['tags'];
    return $data ? "---\n" . Yaml::dump($data, 2, 2) . "---\n\n" : '';
}

$summary = [];
foreach ($topics as $t) {
    $slug = $t['slug'];
    if (in_array($slug, $SKIP, true)) { $summary[] = ['slug' => $slug, 'note' => 'skipped (hand-maintained)']; continue; }

    $title = fix_casing($t['title'] !== '' ? $t['title'] : ucwords(str_replace('-', ' ', $slug)), $CASE);
    $attStmt->execute([$t['topic_id']]);
    $atts = $attStmt->fetchAll();

    $md = tidy_md(fix_casing(delink($conv->convert(clean_html($t['body_html'])), $ported), $CASE));

    $copied = [];
    $missing = [];
    foreach ($atts as $a) {
        $fn = basename($a['filename']);
        $md = str_replace(['/pgmfi/wiki/media/' . $a['local_path'], $a['local_path']], $fn, $md);
        $disk = $MEDIA_BASE . '/' . $a['local_path'];
        if (is_file($disk)) {
            $copied[$fn] = $disk;
        } else {
            $missing[] = $fn;
        }
    }

    $body = build_frontmatter($FM[$slug] ?? []) . "# {$title}\n\n" . $md;
    $summary[] = [
        'slug' => $slug,
        'md' => strlen($md),
        'fm' => isset($FM[$slug]) ? 'yes' : 'no',
        'assets' => count($copied),
        'missing' => $missing,
    ];

    if ($dry) {
        if ($onlySlug || count($topics) <= 2) echo str_repeat('=', 70) . "\n$slug\n" . str_repeat('=', 70) . "\n" . substr($body, 0, 1800) . "\n\n";
        continue;
    }
    $dir = "$OUT_BASE/$slug";
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    file_put_contents("$dir/$slug.md", $body);
    foreach ($copied as $fn => $disk) @copy($disk, "$dir/$fn");
}

echo "\n" . ($dry ? '[DRY RUN] ' : '[WROTE] ') . count($summary) . " topics\n";
foreach ($summary as $s) {
    echo isset($s['note'])
        ? sprintf("  %-32s %s\n", $s['slug'], $s['note'])
        : sprintf(
            "  %-32s md=%-6d fm=%-3s assets=%d missing=%s\n",
            $s['slug'],
            $s['md'],
            $s['fm'],
            $s['assets'],
            $s['missing'] ? implode(',', $s['missing']) : 'none'
        );
}
