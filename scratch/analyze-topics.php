<?php
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

// Get already ported slugs from content directory
$outBase = $ROOT . '/content/cars/electronics';
$ported = array_map('basename', glob("$outBase/*", GLOB_ONLYDIR) ?: []);

// Fetch all topics
$topics = $pdo->query("select topic_id, slug, title, is_webhome, body_html from topics where web='library' and is_webhome=0 order by title")->fetchAll();

$categories = [
    'Electronics/ECU hardware & chipping' => ['ecu', 'chip', 'solder', 'eeprom', 'latch', 'hardware', 'board', 'circuit', 'resistor', 'jumper', 'prom', 'burner', 'socket', 'ic16', '74hc373', '27c256', '27sf256', '28c256'],
    'Electronics/Sensors & solenoids' => ['sensor', 'tps', 'map', 'temp', 'egt', 'iat', 'ect', 'knock', 'vss', 'cyp', 'ckp', 'tdc', 'oxygen', 'o2', 'solenoid', 'vtec', 'egr', 'eld', 'laf', 'maf', 'pressure'],
    'Electronics/Wiring & conversion' => ['wire', 'wiring', 'harness', 'loom', 'pinout', 'schematic', 'plug', 'connector', 'conversion', 'obd0-obd1', 'obd1-obd2', 'obd2-obd1', 'auto-manual', 'swap'],
    'Diagnostics & troubleshooting' => ['trouble', 'diagnostic', 'cel', 'mil', 'code', 'error', 'limp', 'fail', 'fix', 'leak', 'stumble', 'idle', 'surge', 'troubleshooting'],
    'Tuning & ROM editing' => ['tune', 'tuning', 'rom', 'bin', 'edit', 'editor', 'crome', 'uberdata', 'hondata', 'ectune', 'neptune', 'datalog', 'datalogging', 'table', 'map', 'fueling', 'ignition', 'timing', 'boost', 'rev-limit', 'launch', '2-step'],
    'Engine & Drivetrain mechanical' => ['engine', 'motor', 'cam', 'camshaft', 'head', 'block', 'piston', 'rod', 'crank', 'displacement', 'intake', 'exhaust', 'manifold', 'turbo', 'supercharger', 'transmission', 'gear', 'differential', 'clutch', 'flywheel'],
    'Fueling & Injectors' => ['fuel', 'injector', 'saturation', 'impedance', 'sizing', 'pump', 'pressure', 'rail', 'duty-cycle'],
    'General Info & History' => ['info', 'faq', 'history', 'intro', 'introduction', 'glossary', 'acronym', 'wiki', 'contributor']
];

$items = [];
foreach ($topics as $t) {
    $slug = $t['slug'];
    $title = $t['title'] !== '' ? $t['title'] : ucwords(str_replace('-', ' ', $slug));
    $body = $t['body_html'];
    $charCount = strlen(strip_tags($body));

    // Determine status
    $status = 'pending';
    if (in_array($slug, $ported, true)) {
        $status = 'completed';
    } elseif ($charCount < 100) {
        $status = 'stub';
    }

    // Categorize
    $assignedCat = 'Unclassified/Other';
    foreach ($categories as $cat => $keywords) {
        foreach ($keywords as $kw) {
            if (str_contains(strtolower($slug), $kw) || str_contains(strtolower($title), $kw)) {
                $assignedCat = $cat;
                break 2;
            }
        }
    }

    // Try to auto-subcategorize some unclassified ones using more specific terms
    if ($assignedCat === 'Unclassified/Other') {
        if (preg_match('/(p06|p28|p30|p72|p75|pm6|pm7|pr3|pr4|pw0|p13|p14|p11|p29|p84|p91|pcx|p73)/i', $slug)) {
            $assignedCat = 'Electronics/ECU hardware & chipping';
        } elseif (preg_match('/(rt4wd|lsd|d16|b16|b18|k20|h22|f22|vti|sir|crx|civic|integra|accord|prelude)/i', $slug)) {
            $assignedCat = 'Engine & Drivetrain mechanical';
        } elseif (preg_match('/(datalog|datalogging|datalogger|baud|serial|com|tx|rx|max233|max232|ftdi)/i', $slug)) {
            $assignedCat = 'Tuning & ROM editing';
        }
    }

    $items[] = [
        'slug' => $slug,
        'title' => $title,
        'category' => $assignedCat,
        'length' => $charCount,
        'status' => $status
    ];
}

// Generate the markdown document
$out = "# Hondatabase - Wiki Porting Assessment & Progress Plan\n\n";
$out .= "This plan assesses all **513 topics** in the `library` web of the `pgmfi` wiki archive database. It categorizes them, identifies valuable articles vs stubs, and establishes a clear path to complete the porting process.\n\n";

// Stats
$total = count($items);
$completed = count(array_filter($items, fn($i) => $i['status'] === 'completed'));
$stub = count(array_filter($items, fn($i) => $i['status'] === 'stub'));
$pending = count(array_filter($items, fn($i) => $i['status'] === 'pending'));

$out .= "## 1. Overall Progress Summary\n\n";
$out .= "| Status | Count | Percentage | Description |\n";
$out .= "| :--- | :---: | :---: | :--- |\n";
$out .= "| **Completed** | {$completed} | " . round(($completed / $total) * 100, 1) . "% | Topics with local article bundles |\n";
$out .= "| **Pending Porting** | {$pending} | " . round(($pending / $total) * 100, 1) . "% | Relevant content articles remaining to be ported |\n";
$out .= "| **Stubs / Ignored** | {$stub} | " . round(($stub / $total) * 100, 1) . "% | Wiki pages with < 100 characters of text (empty templates, personal placeholders) |\n";
$out .= "| **Total** | **{$total}** | **100%** | All topics in `library` web |\n\n";

// Breakdown by category
$out .= "## 2. Category Breakdown\n\n";
$catStats = [];
foreach ($items as $item) {
    $cat = $item['category'];
    if (!isset($catStats[$cat])) {
        $catStats[$cat] = ['total' => 0, 'completed' => 0, 'pending' => 0, 'stub' => 0];
    }
    $catStats[$cat]['total']++;
    $catStats[$cat][$item['status']]++;
}

// Sort categories by pending count desc
uasort($catStats, fn($a, $b) => $b['pending'] <=> $a['pending']);

$out .= "| Category | Total | Completed | Pending | Stubs |\n";
$out .= "| :--- | :---: | :---: | :---: | :---: |\n";
foreach ($catStats as $cat => $stats) {
    $out .= "| {$cat} | {$stats['total']} | {$stats['completed']} | **{$stats['pending']}** | {$stats['stub']} |\n";
}
$out .= "\n";

// List valuable pending articles per category
$out .= "## 3. Prioritized Pending Articles by Category\n\n";
if ($pending === 0) {
    $out .= "No substantive archived topics remain in the automated pending queue. Remaining work is post-port source-fidelity, attachment, and presentation review.\n\n";
} else {
    $out .= "Below are the top pending articles in each category, sorted by length (approximate value/content depth). This serves as our prioritized backlog.\n\n";
}

foreach (array_keys($catStats) as $cat) {
    $catItems = array_filter($items, fn($i) => $i['category'] === $cat && $i['status'] === 'pending');
    if (empty($catItems)) continue;

    // Sort by length desc
    usort($catItems, fn($a, $b) => $b['length'] <=> $a['length']);

    $out .= "### {$cat} (Top " . min(15, count($catItems)) . " prioritized)\n\n";
    $out .= "| Title | Slug | Length (chars) | Priority / Note |\n";
    $out .= "| :--- | :--- | :---: | :--- |\n";
    
    $limit = 15;
    $count = 0;
    foreach ($catItems as $item) {
        $priority = 'Medium';
        if ($item['length'] > 2500) {
            $priority = '**High** (Rich content)';
        } elseif ($item['length'] < 300) {
            $priority = 'Low (Short reference)';
        }
        $out .= "| {$item['title']} | `{$item['slug']}` | {$item['length']} | {$priority} |\n";
        if (++$count >= $limit) break;
    }
    $out .= "\n";
}

$out .= "## 4. Batch Porting Quality Gate\n\n";
$out .= "A local article bundle counts as completed in the tables above, but a port is not editorially complete until it passes this workflow:\n\n";
$out .= "1. **Capture the source:** retain the raw archived page, attachment inventory, and generated draft long enough to compare them during review.\n";
$out .= "2. **Adapt the presentation:** remove wiki boilerplate and forum chatter; add a useful lead, clear sections, tables, captions, and supported Markdown.\n";
$out .= "3. **Preserve the evidence:** do not invent component functions, compatibility claims, missing table labels, procedures, or recommendations. Keep uncertainty and source contradictions visible.\n";
$out .= "4. **Label legacy guidance:** describe old software settings, tuning defaults, supplier numbers, and community procedures as archived examples rather than current recommendations.\n";
$out .= "5. **Bring the attachments:** compare archive attachment records with the article bundle, copy every useful recovered image and download, remove broken legacy links, and explicitly note important referenced files that were not recovered.\n";
$out .= "6. **Compare source to article:** verify all values, formulas, addresses, pinouts, units, filenames, and retained assets against the raw page after the rewrite.\n";
$out .= "7. **Check rendering and URLs:** remove unsupported syntax and verify every retained image and download through its rendered article asset URL.\n";
$out .= "8. **Run automated validation:** run `php artisan app:lint-articles`, but treat lint as a structural check only. A passing linter does not establish technical accuracy or source fidelity.\n\n";
$out .= "Source-faithful porting does not mean copying pages verbatim. Articles should be rewritten for clarity and mobile readability while keeping the archived technical claims, limitations, and uncertainty intact.\n\n";

$out .= "## 5. Next Steps\n\n";
if ($pending === 0) {
    $out .= "1. **Source-fidelity review:** compare generated and lightly edited articles against their archived pages, prioritizing long technical references and procedures.\n";
    $out .= "2. **Attachment audit:** reconcile archive attachment records with article bundles and verify every retained image and download URL.\n";
    $out .= "3. **Stub decisions:** review the {$stub} short archived topics and either intentionally ignore, merge, or expand them.\n";
    $out .= "4. **Presentation cleanup:** replace malformed legacy tables, link soup, raw HTML, and unsupported Markdown without changing technical meaning.\n";
} else {
    $out .= "1. Port the highest-value pending topics first, using the quality gate above.\n";
    $out .= "2. Review short stubs separately so placeholders do not become low-value articles automatically.\n";
}

file_put_contents($ROOT . '/content/docs/WIKI_PORTING_PLAN.md', $out);
echo "WIKI_PORTING_PLAN.md written successfully!\n";
