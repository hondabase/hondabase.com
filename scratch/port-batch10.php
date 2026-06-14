<?php
require '/var/www/hondabase/www/vendor/autoload.php';
require __DIR__ . '/clean-html-to-md.php';

use Symfony\Component\Yaml\Yaml;

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

$MEDIA_BASE = $ROOT . '/public/pgmfi/wiki/media';
$OUT_BASE   = $ROOT . '/content/cars/electronics';

$FM = [
    'doc-ecu-school' => [
        'summary' => 'An educational guide explaining the fundamental architecture of Honda engine control units, microcontroller operation, memory mapping, and digital circuits.',
        'obd' => [0, 1],
        'complexity' => 'intermediate',
        'tags' => ['ecu', 'hardware', 'education']
    ],
    'chipping-obd2' => [
        'summary' => 'Step-by-step instructions, board preparations, and component requirements for adding external memory chip sockets to OBD2 Honda ECUs.',
        'obd' => [2],
        'complexity' => 'advanced',
        'tags' => ['ecu', 'chipping', 'hardware']
    ],
    'acura-integra-pr4ecu-pinout-and-schematics' => [
        'summary' => 'Electrical pinouts, internal board schematics, and component layouts for the OBD0 and OBD1 Acura Integra PR4 ECU.',
        'obd' => [0, 1],
        'complexity' => 'intermediate',
        'tags' => ['ecu', 'pinout', 'schematic']
    ],
    'knock-board' => [
        'summary' => 'Technical explanation of the plug-in sub-board used for knock sensor processing in VTEC Honda ECUs, including component details and pinouts.',
        'obd' => [1],
        'complexity' => 'advanced',
        'tags' => ['ecu', 'sensor', 'hardware']
    ],
    'p72-debug-mode' => [
        'summary' => 'How to enable the diagnostic debug mode on OBD1 P72 ECUs to stream internal RAM variables and sensor telemetry.',
        'obd' => [1],
        'complexity' => 'advanced',
        'tags' => ['ecu', 'diagnostics', 'rom']
    ],
    'ecu-hardware-mods' => [
        'summary' => 'Common physical hardware modifications for OBD0 and OBD1 Honda ECUs, including datalogging headers, VTEC additions, and boost control hardware.',
        'obd' => [0, 1],
        'complexity' => 'advanced',
        'tags' => ['ecu', 'hardware', 'chipping']
    ],
    'chipping-obd0pm6' => [
        'summary' => 'Guide to chipping the OBD0 PM6 ECU (from the 1988-1991 Civic/CRX Si) to accept external EPROMs and custom tunes.',
        'obd' => [0],
        'complexity' => 'advanced',
        'tags' => ['ecu', 'chipping', 'hardware']
    ],
    'howto-add-extra-features-in-crome' => [
        'summary' => 'Detailed walkthrough on configuring advanced software options like launch control, full-throttle shift, boost control, and custom maps in the Crome ROM editor.',
        'obd' => [1],
        'complexity' => 'intermediate',
        'tags' => ['tuning', 'rom', 'software']
    ],
    'nokia-cable-datalogging' => [
        'summary' => 'How to modify a legacy Nokia FBUS mobile phone serial cable to create a low-cost TTL serial interface for Honda ECU datalogging.',
        'obd' => [0, 1],
        'complexity' => 'advanced',
        'tags' => ['datalogging', 'hardware', 'wiring']
    ],
    'common-te-problems' => [
        'summary' => 'Troubleshooting common issues, software bugs, and connection errors encountered when using the TurboEdit OBD0 editing suite.',
        'obd' => [0],
        'complexity' => 'intermediate',
        'tags' => ['tuning', 'rom', 'software', 'diagnostics']
    ],
];

// Slugs that exist
$ported = array_map('basename', glob("$OUT_BASE/*", GLOB_ONLYDIR) ?: []);
$ported = array_values(array_unique(array_merge($ported, array_keys($FM))));

$attStmt = $pdo->prepare('SELECT filename, local_path FROM attachments WHERE topic_id=? ORDER BY filename');

$slugs = array_keys($FM);
$in    = implode(',', array_fill(0, count($slugs), '?'));
$st    = $pdo->prepare("SELECT * FROM topics WHERE web='library' AND is_webhome=0 AND slug IN ($in)");
$st->execute($slugs);
$topics = $st->fetchAll();

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

foreach ($topics as $t) {
    $slug = $t['slug'];
    $title = $t['title'] !== '' ? $t['title'] : ucwords(str_replace('-', ' ', $slug));
    
    // Normalize acronym casing for title
    $CASE = ['OBD0', 'OBD1', 'OBD2', 'OBD', 'VTEC', 'SOHC', 'DOHC', 'ECU', 'ECM', 'TPS', 'IAT',
             'ECT', 'EGR', 'ELD', 'LAF', 'VSS', 'IAC', 'EACV', 'TDC', 'CYP', 'CKP', 'CKF', 'TCU',
             'CEL', 'MIL', 'SCS', 'EGT'];
    foreach ($CASE as $tc) {
        $title = preg_replace('/(?<![\w\/-])' . preg_quote($tc, '/') . '(?![\w\/-])/i', $tc, $title);
    }
    
    $attStmt->execute([$t['topic_id']]);
    $atts = $attStmt->fetchAll();

    // Convert raw body using our table-preserving converter
    $md = convert_wiki_html($t['body_html'], $ported);

    $copied = [];
    foreach ($atts as $a) {
        $fn = basename($a['filename']);
        $md = str_replace(['/pgmfi/wiki/media/' . $a['local_path'], $a['local_path']], $fn, $md);
        $disk = $MEDIA_BASE . '/' . $a['local_path'];
        if (is_file($disk)) $copied[$fn] = $disk;
    }

    $body = build_frontmatter($FM[$slug] ?? []) . "# {$title}\n\n" . $md;

    $dir = "$OUT_BASE/$slug";
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    file_put_contents("$dir/$slug.md", $body);
    foreach ($copied as $fn => $disk) @copy($disk, "$dir/$fn");
    
    echo "Wrote {$slug} (length=" . strlen($body) . ", images=" . count($copied) . ")\n";
}
