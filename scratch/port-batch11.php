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
    'p14' => [
        'summary' => 'Hardware specifications, board layout, and conversion options for the JDM and USDM OBD1 P14 ECU (Prelude H23A non-VTEC).',
        'complexity' => 'intermediate',
        'tags' => ['ecu', 'reference'],
        'applies_to' => [
            'obd' => [1],
            'engines' => ['H23A'],
            'brand' => 'Honda'
        ]
    ],
    'hi-res-p72' => [
        'summary' => 'Detailed walkthrough on modifying the P72 OBD1 ROM code to support high-resolution ignition and fuel maps.',
        'complexity' => 'advanced',
        'tags' => ['tuning', 'rom', 'software'],
        'applies_to' => [
            'obd' => [1],
            'ecus' => ['P72'],
            'brand' => 'Acura'
        ]
    ],
    'chipping-jdmp30' => [
        'summary' => 'Step-by-step guide to socketing and chipping the Japanese Domestic Market (JDM) OBD1 P30 square-case DOHC VTEC ECU.',
        'complexity' => 'advanced',
        'tags' => ['ecu', 'chipping', 'hardware'],
        'applies_to' => [
            'obd' => [1],
            'ecus' => ['P30'],
            'brand' => 'Honda'
        ]
    ],
    'learning-to-solder' => [
        'summary' => 'An introductory guide to soldering techniques, thermal management, and solder choices for modifying Honda ECU circuit boards.',
        'complexity' => 'beginner',
        'tags' => ['hardware', 'education'],
        'applies_to' => [
            'obd' => [0, 1, 2]
        ]
    ],
    'rom-burner' => [
        'summary' => 'Overview of standard EEPROM and EPROM programming hardware (burners) used to write tuned ROM BINs to memory chips.',
        'complexity' => 'intermediate',
        'tags' => ['hardware', 'reference'],
        'applies_to' => [
            'obd' => [0, 1, 2]
        ]
    ],
    'hardware-for-one-wire-vtec' => [
        'summary' => 'Hardware modifications required to add 1-wire VTEC control to OBD0 Honda ECUs (such as the PM6).',
        'complexity' => 'advanced',
        'tags' => ['ecu', 'hardware', 'chipping'],
        'applies_to' => [
            'obd' => [0],
            'brand' => 'Honda'
        ]
    ],
    'pm7' => [
        'summary' => 'Detailed hardware specifications, pinouts, and tuning advice for the OBD0 PM7 DOHC non-VTEC ECU.',
        'complexity' => 'intermediate',
        'tags' => ['ecu', 'reference'],
        'applies_to' => [
            'obd' => [0],
            'engines' => ['D16A8', 'D16A9'],
            'brand' => 'Honda'
        ]
    ],
    'usb-to-serial-converter-second-gen' => [
        'summary' => 'Understanding and troubleshooting FTDI and Prolific USB-to-TTL serial converters for reliable Honda ECU datalogging.',
        'complexity' => 'intermediate',
        'tags' => ['datalogging', 'hardware', 'wiring'],
        'applies_to' => [
            'obd' => [0, 1]
        ]
    ],
    'rtp-truth-table' => [
        'summary' => 'Register and hardware logic truth tables for configuring Real-Time Programming (RTP) emulation hardware in Honda ECUs.',
        'complexity' => 'advanced',
        'tags' => ['hardware', 'tuning'],
        'applies_to' => [
            'obd' => [1]
        ]
    ],
    'full-duplex-datalogging' => [
        'summary' => 'How to configure full-duplex serial datalogging on OBD1 Honda ECUs by removing the J12 configuration jumper.',
        'complexity' => 'intermediate',
        'tags' => ['datalogging', 'hardware'],
        'applies_to' => [
            'obd' => [1]
        ]
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
             'CEL', 'MIL', 'SCS', 'EGT', 'TTL', 'USB', 'ROM', 'RAM', 'FTDI', 'RTP'];
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
