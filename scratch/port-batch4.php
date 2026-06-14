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
    'p30'                          => ['summary' => 'A complete hardware and software reference guide for the JDM and USDM OBD1 P30 DOHC VTEC ECU (from B16A engines).', 'obd' => [1], 'complexity' => 'intermediate', 'tags' => ['ecu', 'vtec', 'reference']],
    'p28'                          => ['summary' => 'A hardware and software reference guide for the USDM and EDM OBD1 P28 SOHC VTEC ECU (from D16Z6 engines), the most popular ECU for custom tuning.', 'obd' => [1], 'complexity' => 'intermediate', 'tags' => ['ecu', 'vtec', 'reference']],
    'ecu-families'                 => ['summary' => 'An overview of different generations and families of Honda engine control units (OBD0, OBD1, and OBD2) and their compatibility.', 'obd' => [0, 1, 2], 'complexity' => 'beginner', 'tags' => ['ecu', 'reference']],
    'crome-faq'                    => ['summary' => 'Frequently asked questions about Crome, a popular ROM editing and tuning software for OBD1 Honda ECUs.', 'obd' => [1], 'complexity' => 'intermediate', 'tags' => ['tuning', 'rom', 'software']],
    'understanding-maps'           => ['summary' => 'An introductory guide explaining how fuel and ignition tables (maps) are structured and interpreted inside Honda ECU ROMs.', 'obd' => [0, 1, 2], 'complexity' => 'beginner', 'tags' => ['tuning', 'rom', 'fueling', 'ignition']],
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
