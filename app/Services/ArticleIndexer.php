<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleFacet;
use App\Models\Compatibility;
use App\Models\Subject;
use App\Models\TaxonomyNode;
use App\Support\Locales;
use Illuminate\Support\Facades\DB;

/**
 * Writes the derived article index (articles + facets) from the content repo. The index is
 * disposable and fully rebuildable (forkability invariant): indexAll() reconstructs everything;
 * indexOne() refreshes a single article in place (used after a commit) so a one-file edit does
 * not churn the whole table.
 */
class ArticleIndexer
{
    public function __construct(
        private ArticleService $articles,
        private CompatibilityResolver $compatibility,
    ) {}

    /** Rebuild the derived index (articles, facets, compatibilities). The taxonomy itself is NOT
     *  touched here - it is durable, control-panel-edited state. */
    public function indexAll(): array
    {
        $this->compatibility->forget(); // fresh node cache for this run

        $rows = $this->articles->scan();

        DB::transaction(function () use ($rows) {
            $viewCounts = Article::query()
                ->get(['type', 'category', 'slug', 'locale', 'view_count', 'last_viewed_at'])
                ->mapWithKeys(fn (Article $article) => [$this->articleKey($article->type, $article->category, $article->slug, $article->locale) => [
                    'view_count' => (int) $article->view_count,
                    'last_viewed_at' => $article->last_viewed_at,
                ]]);
            Compatibility::query()->delete();
            ArticleFacet::query()->delete();
            Article::query()->delete();
            foreach ($rows as $r) {
                $counts = $viewCounts[$this->articleKey($r['type'], $r['category'], $r['slug'], $r['locale'] ?? 'en')] ?? [];
                $this->persist($r, (int) ($counts['view_count'] ?? 0), $counts['last_viewed_at'] ?? null);
            }
        });

        return [
            'articles' => Article::count(),
            'facets' => ArticleFacet::count(),
            'compatibilities' => Compatibility::count(),
            'nodes' => TaxonomyNode::count(),
            'subjects' => Subject::count(),
        ];
    }

    /**
     * Refresh one article in the index: re-read it from disk and replace its row + facets. If
     * the article no longer exists on disk it is removed from the index (so this also handles
     * deletions and reverts that delete a file).
     */
    public function indexOne(string $type, string $category, string $slug, string $locale = 'en'): void
    {
        DB::transaction(function () use ($type, $category, $slug, $locale) {
            $existing = Article::where(compact('type', 'category', 'slug', 'locale'))->first();
            $viewCount = (int) ($existing?->view_count ?? 0);
            $lastViewedAt = $existing?->last_viewed_at;
            if ($existing) {
                ArticleFacet::where('article_id', $existing->id)->delete();
                $existing->delete();
            }

            $row = $this->articles->scanOne($type, $category, $slug, $locale);
            if ($row !== null) {
                $this->persist($row, $viewCount, $lastViewedAt);
            }
        });
    }

    private function persist(array $r, int $viewCount = 0, mixed $lastViewedAt = null): void
    {
        $article = Article::create([
            'type' => $r['type'],
            'category' => $r['category'],
            'slug' => $r['slug'],
            'locale' => $r['locale'] ?? 'en',
            'title' => $r['title'],
            'summary' => $r['summary'],
            'complexity' => $r['complexity'],
            'body_text' => $r['body_text'],
            'repo_path' => $r['repo_path'],
            'updated_at' => $r['updated'],
            'view_count' => $viewCount,
            'last_viewed_at' => $lastViewedAt,
        ]);

        // Resolve node compatibility (default-locale identity only) and fold the node-derived
        // facets in with the path/frontmatter facets so make/model/generation drill-down works.
        // Gated on locale, not on frontmatter: inherited compatibility comes from the folder path,
        // so an article with no front matter at all still links to the node it lives under.
        $rows = $r['facets'];
        $links = [];
        if (Locales::isDefault($r['locale'] ?? 'en')) {
            $resolved = $this->compatibility->resolve($r['type'], $r['category'], is_array($r['fm'] ?? null) ? $r['fm'] : []);
            $rows = array_merge($rows, $resolved['facets']);
            $links = $resolved['links'];
        }

        $facets = [];
        $seen = [];
        foreach ($rows as [$kind, $value, $label]) {
            $key = $kind.'|'.$value;
            if (isset($seen[$key])) {
                continue; // article_facets is unique on (article, kind, value)
            }
            $seen[$key] = true;
            $facets[] = ['article_id' => $article->id, 'kind' => $kind, 'value' => $value, 'label' => $label];
        }
        if ($facets) {
            ArticleFacet::insert($facets);
        }

        $compat = [];
        foreach ($links as $nodeId => $link) {
            $compat[] = [
                'article_id' => $article->id,
                'taxonomy_node_id' => $nodeId,
                'source' => $link['source'],
                'meta' => $link['meta'] !== null ? json_encode($link['meta']) : null,
            ];
        }
        if ($compat) {
            Compatibility::insert($compat);
        }
    }

    private function articleKey(string $type, string $category, string $slug, string $locale): string
    {
        return "{$type}|{$category}|{$slug}|{$locale}";
    }
}
