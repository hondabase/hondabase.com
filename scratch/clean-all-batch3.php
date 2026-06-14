<?php
$files = [
    '/var/www/hondabase/www/content/cars/electronics/ecu-definition-codes/ecu-definition-codes.md',
    '/var/www/hondabase/www/content/cars/electronics/ecu-chipping-wirelist/ecu-chipping-wirelist.md',
    '/var/www/hondabase/www/content/cars/electronics/kurts-obd0-obd1/kurts-obd0-obd1.md',
    '/var/www/hondabase/www/content/cars/electronics/d16z6-4g-swapnotes/d16z6-4g-swapnotes.md',
    '/var/www/hondabase/www/content/cars/electronics/wide-band-o2/wide-band-o2.md'
];

foreach ($files as $f) {
    if (!is_file($f)) {
        echo "File not found: $f\n";
        continue;
    }
    
    $content = file_get_contents($f);
    
    // 1. Clean up HTML entities and encoding artifacts
    $content = str_replace(
        ['&#146;', 'Â’', 'â\x80\x99', 'Ã¢â\x82¬â\x84¢', 'â'],
        "'",
        $content
    );
    $content = str_replace(
        ['&#147;', '&#148;', 'Â“', 'Â”', 'â', 'â'],
        '"',
        $content
    );
    $content = str_replace('Â', '', $content);
    $content = str_replace('&amp;', '&', $content);
    $content = str_replace('&gt;', '>', $content);
    $content = str_replace('&lt;', '<', $content);
    
    // 2. Clean up escaped Markdown characters in table generator or converter
    $content = str_replace(['\*\*\*', '\*\*'], '**', $content);
    $content = str_replace(['\\[', '\\]'], ['[', ']'], $content);
    $content = str_replace('\\_', '_', $content);
    
    // 3. Strip raw <a> tags without href
    $content = preg_replace('/<a>(.*?)<\/a>/is', '$1', $content);
    
    // 4. Strip anchor tags inside headings
    $content = preg_replace('/^(#+)\s*<a\b[^>]*>(.*?)<\/a>\s*(.*)/mi', '$1 $2$3', $content);
    $content = preg_replace('/^(#+)\s*<a\b[^>]*>\s*<\/a>\s*(.*)/mi', '$1 $2', $content);
    
    // 5. Clean up duplicate anchors or names
    $content = preg_replace('/<a name=".*?">\s*<\/a>/i', '', $content);
    
    // 6. Terminology casing
    $CASE = ['OBD0', 'OBD1', 'OBD2', 'OBD', 'VTEC', 'SOHC', 'DOHC', 'ECU', 'ECM', 'TPS', 'IAT',
             'ECT', 'EGR', 'ELD', 'LAF', 'VSS', 'IAC', 'EACV', 'TDC', 'CYP', 'CKP', 'CKF', 'TCU',
             'CEL', 'MIL', 'SCS', 'EGT'];
    foreach ($CASE as $t) {
        $content = preg_replace('/(?<![\w\/-])' . preg_quote($t, '/') . '(?![\w\/-])/i', $t, $content);
    }
    
    // Restore lowercase keys for frontmatter
    $content = preg_replace('/^  OBD:/m', '  obd:', $content);
    
    file_put_contents($f, $content);
    echo "Cleaned $f\n";
}
