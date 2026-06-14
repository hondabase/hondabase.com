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

// Slugs that are already ported
$ported = array_map('basename', glob("$OUT_BASE/*", GLOB_ONLYDIR) ?: []);

// Fetch all topics
$topics = $pdo->query("SELECT * FROM topics WHERE web='library' AND is_webhome=0")->fetchAll();

$attStmt = $pdo->prepare('SELECT filename, local_path FROM attachments WHERE topic_id=? ORDER BY filename');

function clean_text_artifacts(string $md): string
{
    // Clean up HTML entities and encoding artifacts
    $md = str_replace(
        ['&#146;', 'Â’', 'â\x80\x99', 'Ã¢â\x82¬â\x84¢', 'â€™', 'Ã¢â‚¬â„¢'],
        "'",
        $md
    );
    $md = str_replace(
        ['&#147;', '&#148;', 'Â“', 'Â”', 'â€œ', 'â€ ', 'Ã¢â‚¬Å“', 'Ã¢â‚¬?'],
        '"',
        $md
    );
    $md = str_replace('Â', '', $md);
    $md = str_replace('&amp;', '&', $md);
    $md = str_replace('&gt;', '>', $md);
    $md = str_replace('&lt;', '<', $md);
    $md = str_replace('&nbsp;', ' ', $md);

    // Clean up escaped Markdown characters
    $md = str_replace(['\*\*\*', '\*\*'], '**', $md);
    $md = str_replace(['\\[', '\\]'], ['[', ']'], $md);
    $md = str_replace('\\_', '_', $md);
    $md = str_replace('\\*', '*', $md);

    // Strip raw <a> tags without href
    $md = preg_replace('/<a>(.*?)<\/a>/is', '$1', $md);

    // Strip anchor tags inside headings
    $md = preg_replace('/^(#+)\s*<a\b[^>]*>(.*?)<\/a>\s*(.*)/mi', '$1 $2$3', $md);
    $md = preg_replace('/^(#+)\s*<a\b[^>]*>\s*<\/a>\s*(.*)/mi', '$1 $2', $md);

    // Clean up duplicate anchors or names
    $md = preg_replace('/<a name=".*?">\s*<\/a>/i', '', $md);

    // Clean up personal signatures
    $md = preg_replace('/-=\s*dave\b.*/i', '', $md);
    $md = preg_replace('/--\s*DaveJohnson\b.*/i', '', $md);

    // Fix alert callouts format
    $md = preg_replace('/^>\s*\[!NOTE\]/mi', '> **Note:**', $md);
    $md = preg_replace('/^>\s*\[!WARNING\]/mi', '> **Warning:**', $md);
    $md = preg_replace('/^>\s*\[!IMPORTANT\]/mi', '> **Important:**', $md);
    $md = preg_replace('/^>\s*\[!TIP\]/mi', '> **Tip:**', $md);

    return trim($md) . "\n";
}

function generate_summary(string $md): string
{
    // Strip markdown symbols to get plain text
    $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $md); // links
    $text = preg_replace('/[#*`>_\-|]/', '', $text); // headers, list items, tables, quotes, code block markers
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    // Get first 160 characters ending on a space or sentence boundary
    if (strlen($text) <= 150) {
        return $text !== '' ? $text : 'Technical documentation for PGMFI ECU tuning and diagnostics.';
    }

    $pos = strpos($text, '.', 100);
    if ($pos !== false && $pos < 200) {
        return substr($text, 0, $pos + 1);
    }

    $pos = strpos($text, ' ', 150);
    if ($pos !== false && $pos < 180) {
        return substr($text, 0, $pos) . '...';
    }

    return substr($text, 0, 150) . '...';
}

function generate_frontmatter(string $slug, string $title, string $md): array
{
    $allText = strtolower($slug . ' ' . $title . ' ' . $md);

    // Complexity
    $complexity = 'intermediate';
    if (strlen($md) > 3000 || preg_match('/(assembly|registers|instruction|timing-charts|timing-diagrams|83c154|66207)/i', $allText)) {
        $complexity = 'advanced';
    } elseif (strlen($md) < 800 || preg_match('/(solder-iron|desolder-tips|multimeter|visual-check|basic|beginner)/i', $allText)) {
        $complexity = 'beginner';
    }

    // Tags
    $tags = [];
    if (preg_match('/(solder|desolder|iron|braid|tool|equipment|station|wick)/i', $allText)) {
        $tags[] = 'hardware';
        $tags[] = 'education';
    }
    if (preg_match('/(ecu|chipping|board|jumper|resistor|capacitor|transistor|plug|harness|obd)/i', $allText)) {
        $tags[] = 'ecu';
        if (!in_array('hardware', $tags, true)) $tags[] = 'reference';
    }
    if (preg_match('/(rom|tune|crome|edit|bin|datalog|datalogger|datalogging|address|map|tables|boost|launch|2-step)/i', $allText)) {
        $tags[] = 'tuning';
        $tags[] = 'rom';
    }
    if (preg_match('/(sensor|tps|map|temp|egt|iat|ect|knock|vss|cyp|ckp|tdc|o2|oxygen|solenoid|vtec|egr|eld)/i', $allText)) {
        $tags[] = 'sensors';
        if (!in_array('reference', $tags, true)) $tags[] = 'reference';
    }
    if (preg_match('/(wire|harness|conversion|plug|connector|pinout|pinouts|diagram)/i', $allText)) {
        if (!in_array('wiring', $tags, true)) $tags[] = 'wiring';
        if (!in_array('conversion', $tags, true)) $tags[] = 'conversion';
    }
    if (preg_match('/(diagnostic|diagnostics|cel|mil|code|error|fault|trouble|blink)/i', $allText)) {
        $tags[] = 'diagnostics';
        if (!in_array('ecu', $tags, true)) $tags[] = 'ecu';
    }
    if (empty($tags)) {
        $tags = ['reference'];
    }
    $tags = array_values(array_unique($tags));

    // Applies To
    $applies = [];
    
    // OBD levels
    $obd = [];
    if (preg_match('/(obd0|obd-0|obd\s*0)/i', $allText)) $obd[] = 0;
    if (preg_match('/(obd1|obd-1|obd\s*1)/i', $allText)) $obd[] = 1;
    if (preg_match('/(obd2|obd-2|obd\s*2)/i', $allText)) $obd[] = 2;
    if ($obd) {
        $applies['obd'] = $obd;
    } else {
        $applies['obd'] = [0, 1, 2];
    }

    // ECUs
    $ecus = [];
    $ecuPatterns = ['P06', 'P28', 'P30', 'P72', 'P75', 'PM6', 'PM7', 'PR3', 'PR4', 'PW0', 'P13', 'P14', 'P91', 'P73'];
    foreach ($ecuPatterns as $e) {
        if (preg_match('/\b' . preg_quote($e, '/') . '\b/i', $slug . ' ' . $title)) {
            $ecus[] = $e;
        }
    }
    if ($ecus) {
        $applies['ecus'] = array_values(array_unique($ecus));
    }

    // Brands
    $brands = [];
    if (preg_match('/(acura|integra|nsx)/i', $allText)) $brands[] = 'Acura';
    if (preg_match('/(honda|civic|crx|prelude|accord|del\s*sol|domani|rover)/i', $allText)) $brands[] = 'Honda';
    if ($brands) {
        $applies['brand'] = implode('/', array_unique($brands));
    }

    $summary = generate_summary($md);

    return [
        'summary' => $summary,
        'applies_to' => $applies,
        'complexity' => $complexity,
        'tags' => $tags
    ];
}

$skippedCount = 0;
$portedCount  = 0;

foreach ($topics as $t) {
    $slug = $t['slug'];
    
    // Skip already ported
    if (in_array($slug, $ported, true)) {
        continue;
    }

    // Skip empty and system/personal pages
    $textOnly = trim(strip_tags($t['body_html']));
    $charCount = strlen($textOnly);
    
    $systemSlugs = [
        'page-history', 'title-search', 'all-pages', 'random-page', 'recent-changes', 
        'recent-visitors', 'full-recent-changes', 'sign-in', 'good-style', 'edit-text', 
        'find-page', 'fuzzy-pages', 'orphaned-pages', 'user-preferences', 'like-pages',
        'full-text-search', 'debug-info', 'text-formatting-rules', 'info',
        'xd-eep', 'ben-ogle', 'david-blundell', 'phiz-hates-teh-wiki', 'c92',
        'pgmfi-admin', 'bre', 'web-geek', 'steve-wainstead', 'honda', 'honda-motor-company',
        'prog-studio', 'tech-tom', 'dual-runner-manifold-2', 'serial-communication-2'
    ];
    
    if ($charCount === 0 || in_array($slug, $systemSlugs, true)) {
        $skippedCount++;
        continue;
    }

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

    // Convert raw body using HTML-to-Markdown converter
    // Keep a list of all slugs (existing + pending) so we can repoint them
    $allSlugs = array_values(array_unique(array_merge($ported, array_column($topics, 'slug'))));
    $md = convert_wiki_html($t['body_html'], $allSlugs);

    $copied = [];
    foreach ($atts as $a) {
        $fn = basename($a['filename']);
        $md = str_replace(['/pgmfi/wiki/media/' . $a['local_path'], $a['local_path']], $fn, $md);
        $disk = $MEDIA_BASE . '/' . $a['local_path'];
        if (is_file($disk)) {
            $copied[$fn] = $disk;
        }
    }

    // Clean markdown text from artifacts
    $md = clean_text_artifacts($md);

    // Generate frontmatter
    $fmData = generate_frontmatter($slug, $title, $md);

    // Build the final content
    $fmString = "---\n" . Yaml::dump($fmData, 2, 2) . "---\n\n";
    $body = $fmString . "# {$title}\n\n" . $md;

    // Write to folder
    $dir = "$OUT_BASE/$slug";
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents("$dir/$slug.md", $body);
    
    foreach ($copied as $fn => $disk) {
        @copy($disk, "$dir/$fn");
    }

    $portedCount++;
}

echo "Successfully ported {$portedCount} articles!\n";
echo "Skipped {$skippedCount} stubs.\n";
