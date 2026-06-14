<?php
$ROOT = '/var/www/hondabase/www/content/cars/electronics';
$files = glob("$ROOT/*/*.md");

$processed = 0;

foreach ($files as $f) {
    $content = file_get_contents($f);
    $original = $content;

    // Tokenize markdown into segments:
    // Even indices: plain text segments
    // Odd indices: frontmatter, code blocks, inline code, links, or HTML tags (leave untouched)
    $pattern = '/(---.*?---|^```.*?^```|`[^`]+`|!\[[^\]]*\]\([^)]+\)|\[[^\]]+\]\([^)]+\)|<[^>]+>)/ms';
    $parts = preg_split($pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

    for ($i = 0; $i < count($parts); $i += 2) {
        $text = $parts[$i];

        // 1. Chip models: e.g., 74HC373, 27C256, 28C256, 29C256, 27SF256, 83C154, 80C154, 66207, 66507, 66P507, 66Q589, DS1220, DS1230Y, M5128, MAX233, MAX232, FTDI, FT232
        $chips = ['74HC373', '27C256', '28C256', '29C256', '27SF256', '83C154', '80C154', '66207', '66507', '66P507', '66Q589', 'DS1220', 'DS1230Y', 'M5128', 'MAX233', 'MAX232', 'FTDI', 'FT232'];
        foreach ($chips as $chip) {
            $text = preg_replace('/\b' . preg_quote($chip, '/') . '\b/i', "`{$chip}`", $text);
        }

        // 2. Board component identifiers: R, C, IC, J, CN, Q followed by 1 to 3 digits (e.g. R54, R67, IC4, J1, CN2)
        // Also ensure it's not part of another word
        $text = preg_replace('/\b(R|C|IC|J|CN|Q)([0-9]{1,3})\b/', '`$1$2`', $text);

        // 3. Hexadecimal addresses starting with 0x or ending with h/H (e.g. 0xFE, FEh, 3ef0h)
        $text = preg_replace('/\b(0x[0-9a-fA-F]+)\b/i', '`$1`', $text);
        $text = preg_replace('/\b([0-9a-fA-F]+h)\b/i', '`$1`', $text);

        // 4. Specific known ROM offsets (e.g. 026E, 645D, 645F, 6464, 6466, 3ef0, 3eff, 645D)
        $offsets = ['026E', '645D', '645F', '6464', '6466', '3ef0', '3eff'];
        foreach ($offsets as $offset) {
            $text = preg_replace('/\b' . preg_quote($offset, '/') . '\b/i', "`{$offset}`", $text);
        }

        $parts[$i] = $text;
    }

    $content = implode('', $parts);

    if ($content !== $original) {
        file_put_contents($f, $content);
        $processed++;
    }
}

echo "Successfully formatted monospaced identifiers in {$processed} articles.\n";
