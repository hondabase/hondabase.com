<?php
$file = '/var/www/hondabase/www/content/cars/electronics/ecu-definition-codes/ecu-definition-codes.md';
$content = file_get_contents($file);

// Find the frontmatter and the main body
if (!preg_match('/^---\s*?\r?\n(.*?)\r?\n---\s*?\r?\n(.*)$/s', $content, $m)) {
    echo "No frontmatter found\n";
    exit(1);
}
$fm = $m[1];
$body = $m[2];

// Find the H1 title and the intro text
$lines = explode("\n", $body);
$intro = "";
$tableLine = "";
$attachments = [];

foreach ($lines as $line) {
    if (str_contains($line, '***OBD***')) {
        $tableLine = $line;
    } elseif (str_contains($line, '**Attachment:**') || !empty($attachments) || str_contains($line, '![](/pgmfi/wiki/assets/icn/')) {
        $attachments[] = $line;
    } else {
        $intro .= $line . "\n";
    }
}

// Clean up intro
$intro = trim($intro) . "\n\n";

// Let's parse the table line
// It starts with some text: "Exactly court-vertical-bars... note ... etc."
$tableStartPos = strpos($tableLine, '***OBD***');
$tableTextBefore = substr($tableLine, 0, $tableStartPos);
$tableData = substr($tableLine, $tableStartPos);

echo "Table data starts with: " . substr($tableData, 0, 200) . "\n";

// Let's split by double spaces followed by a known OBD generation or ?
// The columns are: OBD, ID, Market, ECU, Car/Engine, Notes, Bin
// We can use a regex to capture each row.
// A row seems to start with one of: OBD0, OBD1, OBD2 A, OBD2 B, OBD2 K, OBD2?, OBD2, ?
// Let's use preg_split with a regex that captures the row start.
$rowsRaw = preg_split('/\s{2,}(?=(?:OBD0|OBD1|OBD2 A|OBD2 B|OBD2 K|OBD2\?|OBD2|\?)\s{2,})/i', $tableData);

echo "Found " . count($rowsRaw) . " raw rows.\n";

$headers = [];
$rows = [];

// The first row will be the header row:
// "***OBD***  ***ID***  ***Market***  ***ECU***  ***Car/Engine***  ***notes***  ***bin***"
$headerRow = array_shift($rowsRaw);
// Parse headers
$headerRow = str_replace('***', '', $headerRow);
$headers = preg_split('/\s{2,}/', trim($headerRow));

print_r($headers);

foreach ($rowsRaw as $r) {
    // Split columns by double spaces
    $cols = preg_split('/\s{2,}/', trim($r));
    
    // We expect 7 columns: OBD, ID, Market, ECU, Car/Engine, Notes, Bin
    // Sometimes there are fewer columns if some are empty.
    // Let's pad it to 7 elements.
    $obd = $cols[0] ?? '';
    $id = $cols[1] ?? '';
    $market = $cols[2] ?? '';
    $ecu = $cols[3] ?? '';
    $car = $cols[4] ?? '';
    $notes = $cols[5] ?? '';
    $bin = $cols[6] ?? '';
    
    // Sometimes the columns shift if one of the columns in the middle is empty.
    // Let's check:
    // If the last column doesn't look like a bin link, but the notes is empty, maybe they shifted.
    // But since they are separated by double spaces, empty columns in TWiki tables are represented by spaces or empty cells.
    // Let's look at one row: "OBD2 A  ??  USDM (Auto)  P0J-L61  1997 Accord"
    // Here we have: OBD=OBD2 A, ID=??, Market=USDM (Auto), ECU=P0J-L61, Car=1997 Accord, Notes='', Bin=''
    // This is correct.
    
    // Let's clean up entities in each column
    $clean = function($s) {
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML401, 'UTF-8');
        $s = str_replace('***', '', $s);
        return trim($s);
    };
    
    $rows[] = [
        'obd' => $clean($obd),
        'id' => $clean($id),
        'market' => $clean($market),
        'ecu' => $clean($ecu),
        'car' => $clean($car),
        'notes' => $clean($notes),
        'bin' => $clean($bin)
    ];
}

// Format as markdown table
$mdTable = "| OBD | ID | Market | ECU | Car/Engine | Notes | Bin File |\n";
$mdTable .= "| :--- | :---: | :--- | :--- | :--- | :--- | :--- |\n";

foreach ($rows as $row) {
    // Clean bin link: e.g. [bin](P13OEMJ50AT.bin)
    // If it's a bin link, let's keep it. If it's empty, leave it empty.
    $binLink = $row['bin'];
    if ($binLink !== '' && !str_starts_with($binLink, '[')) {
        // If it looks like a filename, make it a link or code block
        $binLink = "`{$binLink}`";
    }
    
    $mdTable .= sprintf(
        "| %s | %s | %s | %s | %s | %s | %s |\n",
        $row['obd'],
        $row['id'],
        $row['market'],
        $row['ecu'] !== '' ? "`{$row['ecu']}`" : '',
        $row['car'],
        $row['notes'],
        $binLink
    );
}

// Assemble new file content
$newContent = "---\n{$fm}\n---\n\n";
$newContent .= $intro;
$newContent .= "## ECU Definition Table\n\n";
$newContent .= $mdTable . "\n\n";

if (!empty($attachments)) {
    $newContent .= "## Attachments & Reference Files\n\n";
    foreach ($attachments as $a) {
        // clean up attachments block
        if (str_contains($a, '**Attachment:**')) continue;
        $a = preg_replace('/!\[\]\(.*?\)\s*/', '', $a);
        $newContent .= $a . "\n";
    }
}

file_put_contents('/var/www/hondabase/www/content/cars/electronics/ecu-definition-codes/ecu-definition-codes.md', $newContent);
echo "Format conversion complete!\n";
