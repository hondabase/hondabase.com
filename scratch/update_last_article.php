<?php
require 'vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Article;
use App\Support\ArticleDocument;

$slug = '02d01720-1500';
$summaries = [
    'en' => 'Step-by-step hardware modification guide for converting non-VTEC "1720" Honda ECU boards to support VTEC, including a detailed list of required components.',
    'pt' => 'Guia passo a passo de modificação de hardware para converter placas de ECU Honda "1720" sem VTEC para suportar VTEC, incluindo uma lista detalhada dos componentes necessários.',
];

foreach ($summaries as $locale => $summary) {
    $article = Article::where('slug', $slug)->where('locale', $locale)->first();
    if (!$article) continue;
    
    $repoPath = 'content/' . $article->repo_path;
    if (!file_exists($repoPath)) continue;
    
    $raw = file_get_contents($repoPath);
    $doc = ArticleDocument::parse($raw);
    
    $doc['fm']['summary'] = $summary;
    $newRaw = ArticleDocument::compose($doc['fm'], $doc['body']);
    
    file_put_contents($repoPath, $newRaw);
    
    $article->summary = $summary;
    $article->save();
}

echo "Successfully updated $slug summaries.\n";
