<?php
$ROOT = '/var/www/hondabase/www/content/cars/electronics';
$files = glob("$ROOT/*/*.md");

$processed = 0;

foreach ($files as $f) {
    $content = file_get_contents($f);
    $original = $content;

    // 1. Clean up legacy attachment tables
    // We match any block that starts with | ... attachment ... | followed by divider and rows
    $pattern = '/(?:^|\n)(\\|[^\n]*?attachment[^\n]*?\\|)\s*\n\\|[ :\-|]+\\|\s*\n((?:\\|[^\n]*?\\|(?:\s*\\|)?\s*(?:\n|$))+)/i';
    
    $content = preg_replace_callback($pattern, function($m) {
        $tableBody = $m[2];
        
        // Match all attachment rows inside the table body using the robust regex
        $rowPattern = '/icn\/[a-z0-9]+\.gif\)\s*(?:\\\\?\[)?([^\]\s\\\\#]+)(?:\\\\?\])?\(([^)\s\\\\#]+)\)\s*\|\s*[^|]*\|\s*[^|]*\|\s*[^|]*\|\s*[^|]*\|\s*([^|]*)/i';
        
        if (preg_match_all($rowPattern, $tableBody, $matches, PREG_SET_ORDER)) {
            $replacements = [];
            foreach ($matches as $match) {
                $displayName = str_replace('\\', '', trim($match[1]));
                $url = str_replace('\\', '', trim($match[2]));
                $comment = str_replace('\\', '', trim($match[3]));
                
                $filename = basename($url);
                
                // Clean up comment
                $description = trim($comment);
                if ($description === '' || strtolower($description) === 'mod' || $description === '[]') {
                    $description = $displayName;
                }
                
                // Detect if image
                if (preg_match('/\.(jpe?g|png|gif)$/i', $filename)) {
                    $replacements[] = "![{$description}]({$filename})";
                } else {
                    $replacements[] = "* [Download: {$description}]({$filename})";
                }
            }
            return "\n\n" . implode("\n\n", $replacements) . "\n\n";
        }
        
        return $m[0]; // Return unchanged if no attachments matched
    }, $content);

    // 2. Prepend Wayback Machine to pgmfi.org / forum.pgmfi.org external links
    // Match http://forum.pgmfi.org/..., http://pgmfi.org/..., etc.
    // Exclude links that already have web.archive.org/web/
    $content = preg_replace_callback(
        '/(?<!web\.archive\.org\/web\/)(https?:\/\/(?:[a-z0-9-]+\.)?pgmfi\.org[^\s)"]*)/i',
        function ($m) {
            $url = $m[1];
            // Remove trailing dot or punctuation if matched
            $url = rtrim($url, '.,;');
            return "https://web.archive.org/web/" . $url;
        },
        $content
    );

    // 3. General cleanup: fix any double newlines or stray escape characters
    $content = preg_replace("/\n{3,}/", "\n\n", $content);
    
    // Clean up escaped underscores in markdown image/file names: e.g. ![caption](foo\_bar.jpg) -> ![caption](foo_bar.jpg)
    $content = preg_replace_callback('/(!\[.*?\]\()([^)]+)(\))/', function($m) {
        $url = str_replace('\\', '', $m[2]);
        return $m[1] . $url . $m[3];
    }, $content);
    $content = preg_replace_callback('/(\[[^\]]+\]\()([^)]+)(\))/', function($m) {
        $url = str_replace('\\', '', $m[2]);
        return $m[1] . $url . $m[3];
    }, $content);

    // 4. Save file if changed
    if ($content !== $original) {
        file_put_contents($f, $content);
        $processed++;
    }
}

echo "Cleaned up and refactored {$processed} articles.\n";
