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
    protected $signature = 'hondabase:reindex';

    protected $description = 'Rebuild the article index and facets from the content repo';

    public function handle(ArticleIndexer $indexer): int
    {
        $counts = $indexer->indexAll();

        $this->info(sprintf(
            'Indexed %d articles, %d facets (%d kinds), %d compatibilities. Taxonomy: %d nodes, %d subjects (seed via hondabase:taxonomy:seed).',
            $counts['articles'],
            $counts['facets'],
            ArticleFacet::distinct('kind')->count('kind'),
            $counts['compatibilities'] ?? 0,
            $counts['nodes'] ?? 0,
            $counts['subjects'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
