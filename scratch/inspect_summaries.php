<?php

use App\Models\Article;

foreach (Article::limit(20)->get() as $article) {
    echo "Slug: {$article->slug}\n";
    echo "Summary: " . ($article->summary ?? 'NULL') . "\n";
    echo "-------------------\n";
}
