<?php
$ROOT = '/var/www/hondabase/www/content/cars/electronics';
$files = glob("$ROOT/*/*.md");

$issues = [];
$totalChecked = 0;

foreach ($files as $f) {
    $totalChecked++;
    $content = file_get_contents($f);
    $relativeName = str_replace('/var/www/hondabase/www/content/', '', $f);
    
    // 1. Check for disallowed alert blocks
    if (preg_match('/^>\s*\[!/mi', $content)) {
        $issues[$relativeName][] = "Disallowed alert callout format (> [!)";
    }
    
    // 2. Check for leftover HTML elements
    if (preg_match('/<(a|p|br|table|tr|td|th|div|span|ul|ol|li)\b[^>]*>/i', $content, $m)) {
        // Exclude specific safe inline elements or anchors if any (but we want clean markdown)
        $issues[$relativeName][] = "Leftover HTML tag: <{$m[1]}>";
    }
    
    // 3. Check for unresolved legacy pgmfi / forum links
    if (preg_match('/pgmfi\.org|pgmfi\/wiki|pgmfi\/forum/i', $content, $m)) {
        $issues[$relativeName][] = "Contains legacy pgmfi/forum reference";
    }
    
    // 4. Check for encoding artifacts
    if (preg_match('/Â|â€™|Ã¢|â\x80\x99|Ã¢â\x82¬â\x84¢|Ã¢â‚¬â„¢|Ã¢â‚¬Å“|Ã¢â‚¬?/i', $content, $m)) {
        $issues[$relativeName][] = "Contains encoding artifacts/broken UTF-8 characters";
    }
    
    // 5. Check for empty contents or missing title
    $lines = explode("\n", $content);
    $hasTitle = false;
    foreach ($lines as $line) {
        if (str_starts_with($line, '# ')) {
            $hasTitle = true;
            break;
        }
    }
    if (!$hasTitle) {
        $issues[$relativeName][] = "Missing H1 title (# Title)";
    }
}

echo "Audited {$totalChecked} articles.\n";
if (empty($issues)) {
    echo "No issues found! All articles are clean.\n";
} else {
    echo "Found issues in " . count($issues) . " files:\n\n";
    foreach ($issues as $name => $fileIssues) {
        echo "File: {$name}\n";
        foreach ($fileIssues as $issue) {
            echo "  - {$issue}\n";
        }
        echo "\n";
    }
}
