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
    'chips-for-ec-us' => [
        'summary' => 'Component part database and retail sourcing guide for custom Honda ECU chipping, including logic latches and memory chips.',
        'complexity' => 'intermediate',
        'tags' => ['ecu', 'chipping', 'hardware'],
        'applies_to' => ['obd' => [0, 1, 2]]
    ],
    '27c256' => [
        'summary' => 'Detailed hardware specifications, pinouts, and timing requirements for standard 27C256 EPROM memory chips.',
        'complexity' => 'intermediate',
        'tags' => ['hardware', 'reference'],
        'applies_to' => ['obd' => [0, 1]]
    ],
    'ecu-connections' => [
        'summary' => 'Electrical connector pin assignments and wire layouts for connecting external sensors and dataloggers to Honda ECUs.',
        'complexity' => 'intermediate',
        'tags' => ['ecu', 'pinout', 'wiring'],
        'applies_to' => ['obd' => [0, 1, 2]]
    ],
    'desoldering-station' => [
        'summary' => 'Overview of professional desoldering stations and thermal settings recommended for safely removing factory ROM chips from ECU boards.',
        'complexity' => 'intermediate',
        'tags' => ['hardware', 'education'],
        'applies_to' => ['obd' => [0, 1, 2]]
    ],
    'p14-to-p13' => [
        'summary' => 'Walkthrough on converting a non-VTEC P14 ECU to run VTEC engine calibrations matching the P13 specifications.',
        'complexity' => 'advanced',
        'tags' => ['ecu', 'hardware', 'conversion'],
        'applies_to' => [
            'obd' => [1],
            'ecus' => ['P14', 'P13'],
            'brand' => 'Honda'
        ]
    ],
    'desoldering-iron' => [
        'summary' => 'How to use a vacuum-assisted desoldering iron to clear solder from through-holes without lifting delicate PCB pads.',
        'complexity' => 'beginner',
        'tags' => ['hardware', 'education'],
        'applies_to' => ['obd' => [0, 1, 2]]
    ],
    'difference-us-can-pm6' => [
        'summary' => 'Hardware and component differences between USDM and Canadian-market OBD0 PM6 Civic/CRX Si ECUs, focusing on ELD circuitry.',
        'complexity' => 'intermediate',
        'tags' => ['ecu', 'reference'],
        'applies_to' => [
            'obd' => [0],
            'ecus' => ['PM6'],
            'brand' => 'Honda'
        ]
    ],
    'obd0pr4' => [
        'summary' => 'Detailed hardware board configurations, pins, and chip modifications specific to the OBD0 Acura Integra PR4 ECU.',
        'complexity' => 'intermediate',
        'tags' => ['ecu', 'reference'],
        'applies_to' => [
            'obd' => [0],
            'ecus' => ['PR4'],
            'brand' => 'Acura'
        ]
    ],
    'epromer5' => [
        'summary' => 'Sourcing, assembling, and using the budget-friendly EPROMer5 DIY programmer kit for writing 27C256 chips.',
        'complexity' => 'intermediate',
        'tags' => ['hardware', 'reference'],
        'applies_to' => ['obd' => [0, 1, 2]]
    ],
    'pre-ignition' => [
        'summary' => 'Explains the physical causes, symptoms, and severe engine damage risks associated with combustion pre-ignition and engine knock.',
        'complexity' => 'beginner',
        'tags' => ['tuning', 'diagnostics'],
        'applies_to' => ['obd' => [0, 1, 2]]
    ],
    'dlc-communication' => [
        'summary' => 'Technical details of the Data Link Connector (DLC) communication protocol, baud rates, and serial request standards for Honda ECUs.',
        'complexity' => 'advanced',
        'tags' => ['datalogging', 'software'],
        'applies_to' => ['obd' => [1]]
    ],
    'dual-roms' => [
        'summary' => 'How to install and wire a dual-ROM setup using a 27C512 chip and a toggle switch to select between two separate tunes on the fly.',
        'complexity' => 'advanced',
        'tags' => ['ecu', 'hardware', 'tuning'],
        'applies_to' => ['obd' => [0, 1]]
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
        if (!empty($f['applies_to'][$k])) $at[$k] = $f['applies_to'][$k];
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
             'CEL', 'MIL', 'SCS', 'EGT', 'TTL', 'USB', 'ROM', 'RAM', 'FTDI', 'RTP', 'DLC'];
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
