<?php
require 'vendor/autoload.php';
use App\Support\ArticleDocument;

// Commit before mangling (da7e365) and mangling commit (0616ba8)
$prevCommit = 'da7e365';
$mangledCommit = '0616ba8';

$files = explode("\n", trim(shell_exec("git -C content show --name-only $mangledCommit | grep '.md$'")));

$repairedCount = 0;

foreach ($files as $file) {
    if (empty($file)) continue;
    
    // Get raw contents from before and after
    $rawOld = shell_exec("git -C content show $prevCommit:$file 2>/dev/null");
    $rawNew = shell_exec("git -C content show $mangledCommit:$file 2>/dev/null");
    
    if (empty($rawOld) || empty($rawNew)) continue;
    
    $docOld = ArticleDocument::parse($rawOld);
    $docNew = ArticleDocument::parse($rawNew);
    
    $oldSum = $docOld['fm']['summary'] ?? '';
    $newSum = $docNew['fm']['summary'] ?? '';
    
    // If summary is mangled (detect by character loss or weird chars)
    // Actually, comparing them directly is safer.
    if ($oldSum !== $newSum && !empty($oldSum)) {
        echo "Repairing $file...\n";
        
        // Update the file with the old summary
        $docNew['fm']['summary'] = $oldSum;
        $newRaw = ArticleDocument::compose($docNew['fm'], $docNew['body']);
        
        file_put_contents('content/' . $file, $newRaw);
        $repairedCount++;
    }
}

echo "Repaired $repairedCount files.\n";
