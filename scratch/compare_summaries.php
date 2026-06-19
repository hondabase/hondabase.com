<?php
require 'vendor/autoload.php';
use App\Support\ArticleDocument;

$files = explode("\n", trim(shell_exec('git -C content show --name-only 0616ba8686df84609b48208097ea767dbaa1983e | grep ".md$"')));

foreach ($files as $file) {
    if (empty($file)) continue;
    
    $rawOld = shell_exec("git -C content show da7e365:{$file}");
    $rawNew = shell_exec("git -C content show 0616ba8686df84609b48208097ea767dbaa1983e:{$file}");
    
    $docOld = ArticleDocument::parse($rawOld);
    $docNew = ArticleDocument::parse($rawNew);
    
    $oldSum = $docOld['fm']['summary'] ?? '';
    $newSum = $docNew['fm']['summary'] ?? '';
    
    if ($oldSum !== $newSum) {
        echo "File: $file\n";
        echo "Old: $oldSum\n";
        echo "New: $newSum\n";
        echo "-------------------\n";
    }
}
