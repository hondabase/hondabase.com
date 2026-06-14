<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleFacet;
use Illuminate\Support\Facades\DB;

/**
 * Writes the derived article index (articles + facets) from the content repo. The index is
 * disposable and fully rebuildable (forkability invariant): indexAll() reconstructs everything;
 * indexOne() refreshes a single article in place (used after a commit) so a one-file edit does
 * not churn the whole table.
 */
class ArticleIndexer
{
    public function __construct(private ArticleService $articles)
    {
    }

    /** Rebuild the entire index. Returns ['articles' => n, 'facets' => n]. */
    public function indexAll(): array
    {
        $rows = $this->articles->scan();

        DB::transaction(function () use ($rows) {
            ArticleFacet::query()->delete();
            Article::query()->delete();
            foreach ($rows as $r) {
                $this->persist($r);
            }
        });

        return ['articles' => Article::count(), 'facets' => ArticleFacet::count()];
    }

    /**
     * Refresh one article in the index: re-read it from disk and replace its row + facets. If
     * the article no longer exists on disk it is removed from the index (so this also handles
     * deletions and reverts that delete a file).
     */
    public function indexOne(string $type, string $category, string $slug): void
    {
        DB::transaction(function () use ($type, $category, $slug) {
            $existing = Article::where(compact('type', 'category', 'slug'))->first();
            if ($existing) {
                ArticleFacet::where('article_id', $existing->id)->delete();
                $existing->delete();
            }

            $row = $this->articles->scanOne($type, $category, $slug);
            if ($row !== null) {
                $this->persist($row);
            }
        });
    }

    private function persist(array $r): void
    {
        $article = Article::create([
            'type'       => $r['type'],
            'category'   => $r['category'],
            'slug'       => $r['slug'],
            'title'      => $r['title'],
            'summary'    => $r['summary'],
            'complexity' => $r['complexity'],
            'body_text'  => $r['body_text'],
            'repo_path'  => $r['repo_path'],
            'updated_at' => $r['updated'],
        ]);

        $facets = [];
        foreach ($r['facets'] as [$kind, $value, $label]) {
            $facets[] = [
                'article_id' => $article->id,
                'kind'       => $kind,
                'value'      => $value,
                'label'      => $label,
            ];
        }
        if ($facets) {
            ArticleFacet::insert($facets);
        }
    }
}
