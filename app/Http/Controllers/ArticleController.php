<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleLinkClick;
use App\Models\ArticleRevision;
use App\Models\Compatibility;
use App\Models\TaxonomyNode;
use App\Services\ArticleClickCounter;
use App\Services\ArticleService;
use App\Support\BreadcrumbBuilder;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ArticleController extends Controller
{
    private const COMPATIBILITY_ROOT_KINDS = ['model', 'family', 'engine_family'];

    public function __construct(
        private ArticleService $articles,
        private BreadcrumbBuilder $crumbs,
        private ArticleClickCounter $clicks,
    ) {}

    // The knowledgebase serves arbitrary-depth category paths (electronics/ecu/...), which route
    // regex can't disambiguate, so a single catch-all `/{type}/{path}` (+ /{locale}/ mirror) lands
    // here and is resolved against the content/index: co-located asset, then article, then category
    // listing, else 404. Params are read by name: the localized route carries a leading {locale}
    // segment, absent on the canonical unprefixed route (which is therefore always the default,
    // English locale, regardless of any cookie).
    public function resolve(Request $request)
    {
        $type = $request->route('type');
        $path = trim((string) $request->route('path'), '/');
        $locale = $request->route('locale') ?? Locales::default();

        // 1. Co-located asset: the final segment is a filename with an extension. Resolve the
        //    bundle (category path + slug) from the segments before it.
        $last = basename($path);
        if (preg_match('/\.[A-Za-z0-9]+$/', $last) && str_contains($path, '/')) {
            $bundle = ArticleService::splitPath(substr($path, 0, strrpos($path, '/')));
            if ($bundle['category'] !== '') {
                $abs = $this->articles->assetPath($type, $bundle['category'], $bundle['slug'], $last);
                if ($abs) {
                    return response()->file($abs, ['Cache-Control' => 'public, max-age=86400']);
                }
            }
        }

        // 2. Article at this exact path (slug = last segment, category = the rest).
        ['category' => $category, 'slug' => $slug] = ArticleService::splitPath($path);
        if ($category !== '') {
            $art = $this->articles->find($type, $category, $slug, $locale);
            if ($art) {
                // Fetch the DB identity (always English) to pull its linked taxonomy nodes.
                $dbArt = Article::where([
                    'type' => $type,
                    'category' => $category,
                    'slug' => $slug,
                    'locale' => Locales::default(),
                ])->first();
                $viewArt = Article::where([
                    'type' => $type,
                    'category' => $category,
                    'slug' => $slug,
                    'locale' => ! empty($art['is_fallback']) ? Locales::default() : $art['locale'],
                ])->first();
                $this->clicks->countArticleView($viewArt, $request);
                $art['view_count'] = (int) ($viewArt?->view_count ?? 0);
                $decorated = $this->clicks->decorate($art);
                $art['html'] = $decorated['html'];

                return view('article', [
                    'art' => $art,
                    'compatibilityGroups' => $this->compatibilityGroups($dbArt),
                    'crumbs' => $this->crumbs->forCategory($type, $category, $locale),
                ]);
            }
        }

        // 3. Taxonomy node landing page (e.g. /cars/honda/civic/eg): a product/generation page
        //    listing everything that fits it (inherited + explicit) plus its child nodes.
        $node = TaxonomyNode::firstWhere('path', "{$type}/{$path}");
        if ($node) {
            return $this->nodePage($node, $locale);
        }

        // 4. Category listing: the whole path is a (subject) category (any depth).
        if ($this->articles->categoryExists($type, $path)) {
            return view('category', [
                'type' => $type,
                'category' => $path,
                'locale' => $locale,
                'type_label' => ucwords(str_replace('-', ' ', $type)),
                'category_label' => ucwords(str_replace('-', ' ', basename($path))),
                'crumbs' => $this->crumbs->forCategory($type, $path, $locale),
            ]);
        }

        abort(404);
    }

    /** Group linked taxonomy nodes for the article "Applies to" block. */
    private function compatibilityGroups(?Article $article): Collection
    {
        if (! $article) {
            return collect();
        }

        $nodeIds = $article->compatibilities()->pluck('taxonomy_node_id');
        if ($nodeIds->isEmpty()) {
            return collect();
        }

        $linkedNodes = TaxonomyNode::whereIn('id', $nodeIds)->get();
        if ($linkedNodes->isEmpty()) {
            return collect();
        }

        $ancestorPaths = $linkedNodes
            ->flatMap(fn (TaxonomyNode $node) => $this->taxonomyAncestorPaths($node->path))
            ->unique()
            ->values();
        $nodesByPath = TaxonomyNode::whereIn('path', $ancestorPaths)->get()->keyBy('path');

        return $linkedNodes
            ->sortBy(fn (TaxonomyNode $node) => $this->taxonomySortKey($node))
            ->groupBy('type')
            ->map(function (Collection $typeNodes, string $type) use ($nodesByPath) {
                $branches = $typeNodes
                    ->groupBy(fn (TaxonomyNode $node) => $this->taxonomyBranchKey($node))
                    ->map(fn (Collection $branchNodes, string $branchKey) => $this->compatibilityBranch($branchNodes, $branchKey, $nodesByPath))
                    ->sortBy('sort')
                    ->values();

                return [
                    'key' => $type,
                    'label' => $this->taxonomyLabel($type),
                    'count' => $typeNodes->count(),
                    'branches' => $branches,
                    'sort' => $this->typeSortKey($type),
                ];
            })
            ->sortBy('sort')
            ->values();
    }

    private function compatibilityBranch(Collection $branchNodes, string $branchKey, Collection $nodesByPath): array
    {
        $first = $branchNodes->first();
        $branchNode = $first ? $nodesByPath->get($first->type.'/'.$branchKey) : null;

        $cards = $branchNodes
            ->groupBy(fn (TaxonomyNode $node) => $this->compatibilityRoot($node, $nodesByPath)->path)
            ->map(function (Collection $cardNodes, string $rootPath) use ($nodesByPath) {
                $root = $nodesByPath->get($rootPath) ?? $cardNodes->first();
                $children = $cardNodes
                    ->reject(fn (TaxonomyNode $node) => $node->path === $root->path)
                    ->sortBy(fn (TaxonomyNode $node) => $this->taxonomySortKey($node))
                    ->map(fn (TaxonomyNode $node) => $this->compatibilityNode($node))
                    ->values();

                return [
                    'root' => $this->compatibilityNode($root),
                    'direct' => $cardNodes->contains(fn (TaxonomyNode $node) => $node->path === $root->path),
                    'children' => $children,
                ];
            })
            ->sortBy(fn (array $card) => $card['root']['sort'])
            ->values();

        return [
            'key' => $branchKey,
            'label' => $branchNode?->name ?? $this->taxonomyLabel($branchKey),
            'count' => $branchNodes->count(),
            'cards' => $cards,
            'sort' => $branchNode ? $this->taxonomySortKey($branchNode) : $this->taxonomySortKey($first),
        ];
    }

    private function compatibilityRoot(TaxonomyNode $node, Collection $nodesByPath): TaxonomyNode
    {
        foreach (array_reverse($this->taxonomyAncestorPaths($node->path)) as $path) {
            $candidate = $nodesByPath->get($path);
            if ($candidate && in_array($candidate->kind, self::COMPATIBILITY_ROOT_KINDS, true)) {
                return $candidate;
            }
        }

        return $node;
    }

    private function compatibilityNode(TaxonomyNode $node): array
    {
        $meta = [];
        if ($node->yearRange()) {
            $meta[] = $node->yearRange();
        }

        $chassis = collect($node->chassisCodes())->map(fn (string $code) => strtoupper($code))->unique()->join(', ');
        if ($chassis !== '') {
            $meta[] = $chassis;
        }

        return [
            'kind' => $this->taxonomyLabel($node->kind),
            'name' => $node->name,
            'url' => $node->url(),
            'meta' => $meta,
            'sort' => $this->taxonomySortKey($node),
        ];
    }

    private function taxonomyBranchKey(TaxonomyNode $node): string
    {
        $parts = explode('/', trim($node->path, '/'));

        return $parts[1] ?? 'general';
    }

    /** @return list<string> */
    private function taxonomyAncestorPaths(string $path): array
    {
        $parts = explode('/', trim($path, '/'));
        $paths = [];

        for ($i = 2; $i <= count($parts); $i++) {
            $paths[] = implode('/', array_slice($parts, 0, $i));
        }

        return $paths;
    }

    private function taxonomySortKey(?TaxonomyNode $node): string
    {
        if (! $node) {
            return '99:';
        }

        return $this->typeSortKey($node->type).':'.$node->path;
    }

    private function typeSortKey(string $type): string
    {
        $idx = array_search($type, config('hondabase.types', []), true);

        return str_pad((string) ($idx === false ? 99 : $idx), 2, '0', STR_PAD_LEFT);
    }

    private function taxonomyLabel(string $value): string
    {
        return Str::headline(str_replace(['-', '_'], ' ', $value));
    }

    /** Render a taxonomy node landing page: its metadata, child nodes, and the articles that fit
     *  it or any descendant (via the compatibilities pivot). */
    private function nodePage(TaxonomyNode $node, string $locale)
    {
        $articleIds = Compatibility::whereIn('taxonomy_node_id', $node->selfAndDescendantIds())
            ->distinct()->pluck('article_id');

        // Compatibility links live on the default-locale (English) identity row; render that set and
        // mark each row with the requested locale so url() emits the localized link.
        $articles = Article::whereIn('id', $articleIds)
            ->where('locale', Locales::default())
            ->orderByDesc('updated_at')->get()
            ->each(fn (Article $a) => $a->locale = $locale);

        $category = Str::after($node->path, $node->type.'/');

        return view('node', [
            'node' => $node,
            'locale' => $locale,
            'articles' => $articles,
            'children' => $node->children()->orderBy('name')->get(),
            'crumbs' => $this->crumbs->forCategory($node->type, $category, $locale),
        ]);
    }

    public function stagedAsset(ArticleRevision $revision, string $file): BinaryFileResponse
    {
        $path = $revision->stagedAssetPath($file);
        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'private, no-store']);
    }

    public function clickLink(ArticleLinkClick $counter, Request $request)
    {
        $this->clicks->countLinkClick($counter, $request);

        return response()->noContent();
    }
}
