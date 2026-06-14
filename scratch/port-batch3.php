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
    'ecu-definition-codes'         => ['summary' => 'A reference table of Honda and Acura OBD0, OBD1, and OBD2 ECU part numbers, their markets, and corresponding code designations.', 'obd' => [0, 1, 2], 'complexity' => 'beginner', 'tags' => ['ecu', 'reference']],
    'ecu-chipping-wirelist'        => ['summary' => 'Pin connections and traces between the MCU, latch, and external EPROM socket on OBD1 Honda ECUs.', 'obd' => [1], 'complexity' => 'advanced', 'tags' => ['ecu', 'chipping', 'hardware', 'wiring']],
    'kurts-obd0-obd1'              => ['summary' => 'Step-by-step instructions for converting an OBD0 chassis wiring harness to run an OBD1 ECU.', 'obd' => [0, 1], 'complexity' => 'advanced', 'tags' => ['conversion', 'wiring', 'hardware']],
    'd16z6-4g-swapnotes'           => ['summary' => 'Detailed notes on swapping an OBD1 D16Z6 engine into a 4th generation (1988-1991) OBD0 Civic or CRX, focusing on mechanicals, distributor, and wiring.', 'obd' => [0, 1], 'complexity' => 'advanced', 'tags' => ['swap', 'vtec', 'wiring', 'engine']],
    'wide-band-o2'                 => ['summary' => 'How wideband oxygen sensors differ from narrowband sensors, integration with aftermarket controllers, and datalogging setups.', 'obd' => [0, 1, 2], 'complexity' => 'intermediate', 'tags' => ['sensor', 'fueling', 'diagnostics']],
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
