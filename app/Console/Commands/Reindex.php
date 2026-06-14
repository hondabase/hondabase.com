<?php

namespace App\Console\Commands;

use App\Models\ArticleFacet;
use App\Services\ArticleIndexer;
use Illuminate\Console\Command;

/**
 * Rebuild the derived article index + facets from the content repo.
 * The index is disposable: this command fully reconstructs it (forkability invariant).
 */
class Reindex extends Command
{
    protected $signature   = 'hondabase:reindex';
    protected $description = 'Rebuild the article index and facets from the content repo';

    public function handle(ArticleIndexer $indexer): int
    {
        $counts = $indexer->indexAll();

        $this->info(sprintf(
            'Indexed %d articles, %d facets (%d distinct kinds).',
            $counts['articles'],
            $counts['facets'],
            ArticleFacet::distinct('kind')->count('kind'),
        ));

        return self::SUCCESS;
    }
}
