<?php

namespace App\Console\Commands;

use App\Http\Controllers\FeedController;
use App\Http\Controllers\SitemapController;
use App\Models\Article;
use App\Models\ArticleFacet;
use App\Services\ArticleIndexer;
use App\Services\OgImageGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Rebuild the derived article index + facets from the content repo.
 * The index is disposable: this command fully reconstructs it (forkability invariant).
 */
class Reindex extends Command
{
    protected $signature = 'hondabase:reindex {--force-og : Regenerate every OG card even if fresh}';

    protected $description = 'Rebuild the article index and facets from the content repo';

    public function handle(ArticleIndexer $indexer, OgImageGenerator $og): int
    {
        $counts = $indexer->indexAll();

        Cache::forget(SitemapController::CACHE_KEY);
        Cache::forget(FeedController::CACHE_KEY);

        $this->regenerateOgImages($og);

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

    /**
     * (Re)render the per-article OG cards and prune ones whose article is gone
     * or hidden. Cards are derived artifacts under public/assets/og (gitignored).
     */
    private function regenerateOgImages(OgImageGenerator $og): void
    {
        $expected = [];
        $generated = 0;
        foreach (Article::query()->orderBy('repo_path')->lazy() as $article) {
            if ($article->is_hidden) {
                $og->remove($article);

                continue;
            }
            $expected[OgImageGenerator::relativePath($article)] = true;
            $og->generate($article, (bool) $this->option('force-og'));
            $generated++;
        }

        $root = public_path('assets/og');
        if (is_dir($root)) {
            foreach (File::allFiles($root) as $file) {
                $rel = 'assets/og/'.str_replace('\\', '/', $file->getRelativePathname());
                if (! isset($expected[$rel])) {
                    File::delete($file->getPathname());
                }
            }
        }

        $this->info("OG cards: {$generated} articles covered.");
    }
}
