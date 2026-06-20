<?php

namespace App\Livewire;

use App\Models\TaxonomyNode;
use App\Support\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Progressive-disclosure product browser. Guides users from product lines (root) → brand (make)
 * → model → generation (and deeper), then hands off to the scoped Explorer via a facet filter
 * chain. State is URL-bound so every slide is shareable and browser back / forward work natively.
 *
 * $node encoding:
 *   ''              → root slide (product lines from config)
 *   'cars'          → type slide (shows makes for type 'cars')
 *   'cars/honda'    → taxonomy node slide (shows children of the node with path 'cars/honda')
 */
class Browser extends Component
{
    /** Current taxonomy path or type slug. Empty = root product lines. */
    #[Url(as: 'node')]
    public string $node = '';

    /** Typeahead search. Separate URL param from Explorer's ?q to avoid collision. */
    #[Url(as: 'bq')]
    public string $bq = '';

    /** Navigate into a child node or type slug. Clears the typeahead. */
    public function drillTo(string $path): void
    {
        $this->node = $path;
        $this->bq = '';
    }

    public function render(): View
    {
        $current = $this->currentNode();
        $children = $this->children($current);
        $counts = $this->childCounts($current, $children);
        $breadcrumb = $this->breadcrumb($current);
        $suggestions = trim($this->bq) !== '' ? $this->suggestions($this->bq) : collect();
        $handoffUrl = $this->node !== '' ? $this->handoffUrl($current) : null;

        return view('livewire.browser', compact(
            'current', 'children', 'counts', 'breadcrumb', 'suggestions', 'handoffUrl'
        ));
    }

    // ---------------------------------------------------------------------------
    // State helpers
    // ---------------------------------------------------------------------------

    /** Resolve $node to a TaxonomyNode, or null at root/type level. */
    private function currentNode(): ?TaxonomyNode
    {
        if ($this->node === '' || ! str_contains($this->node, '/')) {
            return null;
        }

        return TaxonomyNode::where('path', $this->node)->first();
    }

    /** The type segment of $node ('cars' from 'cars/honda/civic', or '' at root). */
    private function nodeType(): string
    {
        return $this->node !== '' ? explode('/', $this->node)[0] : '';
    }

    // ---------------------------------------------------------------------------
    // Slide data
    // ---------------------------------------------------------------------------

    /**
     * Children for the current slide.
     *   root       → Collection of config arrays from config('product_lines.lines')
     *   type level → TaxonomyNode collection of makes (parent_id IS NULL) for the type
     *   node level → TaxonomyNode collection of $current->children()
     */
    private function children(?TaxonomyNode $current): Collection
    {
        if ($this->node === '') {
            return collect(config('product_lines.lines', []));
        }

        $type = $this->nodeType();

        if ($current === null) {
            // Type level: makes are root nodes (parent_id = null) for this type.
            return TaxonomyNode::where('type', $type)
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get();
        }

        return $current->children()->orderBy('name')->get();
    }

    /**
     * Article counts keyed by child slug (or type key at root level).
     * Missing entries mean 0. The view greys zero-count cards but still shows them.
     */
    private function childCounts(?TaxonomyNode $current, Collection $children): array
    {
        if ($children->isEmpty()) {
            return [];
        }

        // Root: count per type.
        if ($this->node === '') {
            return DB::table('articles')
                ->where('locale', Locales::default())
                ->where('is_hidden', false)
                ->selectRaw('type, COUNT(*) as c')
                ->groupBy('type')
                ->pluck('c', 'type')
                ->all();
        }

        // Type / node level: base query filtered by ancestor facet chain.
        $baseQuery = DB::table('articles')
            ->where('locale', Locales::default())
            ->where('is_hidden', false)
            ->where('type', $this->nodeType());

        foreach ($this->ancestorFacetChain($current) as $kv) {
            [$kind, $value] = explode(':', $kv, 2);
            $baseQuery->whereExists(fn ($sub) => $sub
                ->select(DB::raw(1))
                ->from('article_facets')
                ->whereColumn('article_facets.article_id', 'articles.id')
                ->where('article_facets.kind', $kind)
                ->where('article_facets.value', $value)
            );
        }

        // $children is a TaxonomyNode collection at type/node level.
        /** @var Collection<int, TaxonomyNode> $children */
        $childKind = $children->first()->kind;
        $childSlugs = $children->pluck('slug')->all();

        return DB::table('article_facets')
            ->whereIn('article_id', $baseQuery->select('id'))
            ->where('kind', $childKind)
            ->whereIn('value', $childSlugs)
            ->selectRaw('value, COUNT(*) as c')
            ->groupBy('value')
            ->pluck('c', 'value')
            ->all();
    }

    /**
     * Breadcrumb array. Each item: ['label' => string, 'path' => string, 'current' => bool].
     * 'path' is passed to drillTo() when clicked. Empty at root.
     */
    private function breadcrumb(?TaxonomyNode $current): array
    {
        if ($this->node === '') {
            return [];
        }

        $type = $this->nodeType();
        $typeLabel = collect(config('product_lines.lines', []))->firstWhere('type', $type)['label'] ?? ucfirst($type);

        $crumbs = [['label' => $typeLabel, 'path' => $type, 'current' => $this->node === $type]];

        if ($current === null) {
            return $crumbs;
        }

        // Add each segment of the taxonomy path.
        $parts = explode('/', $current->path); // ['cars', 'honda', 'civic']
        $allNodes = TaxonomyNode::where('type', $type)->get()->keyBy('path');
        $pathSoFar = $type;

        foreach (array_slice($parts, 1) as $slug) {
            $pathSoFar .= '/' . $slug;
            $node = $allNodes->get($pathSoFar);
            $crumbs[] = [
                'label' => $node?->name ?? ucfirst($slug),
                'path' => $pathSoFar,
                'current' => $pathSoFar === $this->node,
            ];
        }

        return $crumbs;
    }

    /**
     * Facet "kind:value" pairs for the current node and all its ancestors, in bottom-up order.
     * E.g. for cars/honda/civic: ['model:civic', 'make:honda'].
     * Used to scope the article count query to articles compatible with this node.
     */
    private function ancestorFacetChain(?TaxonomyNode $current): array
    {
        if ($current === null) {
            return [];
        }

        $allNodes = TaxonomyNode::where('type', $current->type)->get()->keyBy('id');
        $chain = [];
        $cursor = $current;

        while ($cursor) {
            $chain[] = $cursor->kind . ':' . $cursor->slug;
            $cursor = $cursor->parent_id ? $allNodes->get($cursor->parent_id) : null;
        }

        return $chain;
    }

    /**
     * Handoff URL for "See all articles". Locale-aware.
     *   type level → /cars
     *   node level → /cars?filters[0]=make:honda&filters[1]=model:civic&...
     */
    private function handoffUrl(?TaxonomyNode $current): string
    {
        $type = $this->nodeType();
        $locale = app()->getLocale();
        $prefix = Locales::isDefault($locale) ? '' : '/' . $locale;

        if ($current === null) {
            return $prefix . '/' . $type;
        }

        // Reverse so the chain is root-first (make before model before generation).
        $chain = array_reverse($this->ancestorFacetChain($current));
        $params = http_build_query(['filters' => $chain]);

        return $prefix . '/' . $type . '?' . $params;
    }

    // ---------------------------------------------------------------------------
    // Typeahead
    // ---------------------------------------------------------------------------

    /**
     * Taxonomy node suggestions for the search typeahead. Matches name, slug, or
     * chassis codes stored in the meta JSON column.
     */
    private function suggestions(string $q): Collection
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';

        return TaxonomyNode::where(fn ($w) => $w
            ->where('name', 'like', $like)
            ->orWhere('slug', 'like', $like)
            ->orWhere('meta', 'like', $like)
        )
        ->orderBy('kind')
        ->orderBy('name')
        ->limit(8)
        ->get(['id', 'type', 'kind', 'slug', 'name', 'path']);
    }
}
