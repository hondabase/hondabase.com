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
    'eprommer5' => [
        'summary' => 'Alternate spelling directory stub for the EPROMer5 parallel-port device programmer.',
        'complexity' => 'beginner',
        'tags' => ['hardware', 'reference'],
        'applies_to' => ['obd' => [0, 1, 2]]
    ],
    '28c256' => [
        'summary' => 'Technical details, pinout configuration, and compatibility of the 28C256 EEPROM memory chip in Honda ECUs.',
        'complexity' => 'intermediate',
        'tags' => ['hardware', 'reference'],
        'applies_to' => ['obd' => [0, 1]]
    ],
    'uber-p72' => [
        'summary' => 'Detailed walkthrough on configuring and tuning the UberData custom ROM codebase for P72 OBD1 ECUs.',
        'complexity' => 'advanced',
        'tags' => ['tuning', 'rom', 'software'],
        'applies_to' => [
            'obd' => [1],
            'ecus' => ['P72'],
            'brand' => 'Acura'
        ]
    ],
    'desoldering-tool' => [
        'summary' => 'Overview and comparison of manual desoldering tools including desoldering pumps, braids, and bulbs.',
        'complexity' => 'beginner',
        'tags' => ['hardware', 'education'],
        'applies_to' => ['obd' => [0, 1, 2]]
    ],
    'soldering-iron' => [
        'summary' => 'How to select and use a soldering iron for ECU modification, covering wattages, tip shapes, and temperature settings.',
        'complexity' => 'beginner',
        'tags' => ['hardware', 'education'],
        'applies_to' => ['obd' => [0, 1, 2]]
    ],
    'chip-max' => [
        'summary' => 'Technical specifications, software configuration, and chip settings for the EETools ChipMax USB device programmer.',
        'complexity' => 'intermediate',
        'tags' => ['hardware', 'reference'],
        'applies_to' => ['obd' => [0, 1, 2]]
    ],
    'obd1pr4' => [
        'summary' => 'Hardware layouts, component locations, and chipping instructions for the OBD1 Acura Integra PR4 ECU.',
        'complexity' => 'intermediate',
        'tags' => ['ecu', 'reference'],
        'applies_to' => [
            'obd' => [1],
            'ecus' => ['PR4'],
            'brand' => 'Acura'
        ]
    ],
    '91pm6-ecu-led-cel' => [
        'summary' => 'How to read and interpret the flashing diagnostic LED code pulses on the OBD0 PM6 Civic/CRX Si ECU.',
        'complexity' => 'beginner',
        'tags' => ['diagnostics', 'ecu'],
        'applies_to' => [
            'obd' => [0],
            'ecus' => ['PM6'],
            'brand' => 'Honda'
        ]
    ],
    'desoldering-tips' => [
        'summary' => 'Practical tips, tricks, and best practices for desoldering components from delicate ECU circuit boards.',
        'complexity' => 'intermediate',
        'tags' => ['hardware', 'education'],
        'applies_to' => ['obd' => [0, 1, 2]]
    ],
    'pm6' => [
        'summary' => 'Detailed hardware specifications, pinouts, and custom codebase support for the OBD0 PM6 Civic/CRX Si SOHC ECU.',
        'complexity' => 'intermediate',
        'tags' => ['ecu', 'reference'],
        'applies_to' => [
            'obd' => [0],
            'ecus' => ['PM6'],
            'brand' => 'Honda'
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
             'CEL', 'MIL', 'SCS', 'EGT', 'TTL', 'USB', 'ROM', 'RAM', 'FTDI', 'RTP', 'DLC', 'LED'];
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
