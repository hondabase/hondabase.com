# Browser Explorer Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the overwhelming homepage product-line grid + full-dump Explorer with a progressive-disclosure slide wizard (`Browser`) that walks users from product lines → brand → model → generation, ending in the existing scoped Explorer.

**Architecture:** A new `Browser` Livewire component drives the wizard; its URL-bound `$node` property holds the current taxonomy path (`cars/honda/civic`) or type slug (`cars`) or empty string (root). The handoff to the existing `Explorer` is achieved by building a `?filters[0]=make:honda&...` query string from the ancestor facet chain (the `CompatibilityResolver` already materialises each node's ancestors into `article_facets`, so no new query logic is needed in `Explorer`). The current full-dump Explorer moves off the homepage to a dedicated `/explore` route.

**Tech Stack:** Laravel 13, Livewire 4, Alpine.js (bundled), Tailwind CSS 4 / custom CSS tokens, PHPUnit via `php artisan test`, `Livewire\Livewire::test()` for component tests.

## Global Constraints

- No changes to `Explorer.php`, `CompatibilityResolver.php`, `ArticleIndexer.php`, taxonomy schema, or Article model.
- No new node-level routes; handoff uses existing `/{type}` pages with `?filters[]=` query params.
- Default locale is `en` (from `Locales::default()`). Handoff URLs must be locale-prefixed when `app()->getLocale()` is non-default.
- `Article::create()` and `TaxonomyNode::create()` work directly in tests (both have `$guarded = []`). Use `RefreshDatabase`.
- Run tests with `php artisan test`. Run a specific test file with `php artisan test tests/Feature/BrowserTest.php`.
- No emoji in source files. No `npm` — use `pnpm` if JS packages are ever needed (they are not in this plan).

---

## File Map

| Action   | Path                                           | Responsibility                                           |
|----------|------------------------------------------------|----------------------------------------------------------|
| Create   | `app/Livewire/Browser.php`                     | Slide wizard: state, node resolution, counts, breadcrumb, suggestions, handoff URL |
| Create   | `resources/views/livewire/browser.blade.php`   | Card-grid slide view + breadcrumb + search typeahead     |
| Create   | `resources/views/explore.blade.php`            | Global search page (thin wrapper for `<livewire:explorer />`) |
| Create   | `config/product_lines.php`                     | Presentation map: labels, descs, display order, coming-soon entries |
| Create   | `tests/Feature/BrowserTest.php`                | Feature tests for the Browser component                  |
| Modify   | `routes/web.php`                               | Add `/explore` route (+ localized mirror)                |
| Modify   | `resources/views/home.blade.php`               | Swap hardcoded grid + `<livewire:explorer />` → `<livewire:browser />` |
| Modify   | `resources/css/app.css`                        | Browser/slide/breadcrumb styles; reuse existing `.pl-card` / `.ex-*` |

---

## Task 1 — Preflight: verify handoff hydration

This task writes no code. It confirms that Livewire's `#[Url] public array $filters` on `Explorer` actually hydrates from `?filters[0]=make:honda` on a cold page load, which is the single point of failure for the entire handoff architecture.

**Files:** none modified.

- [ ] **Step 1: Start the development server**

```bash
php artisan serve
```

- [ ] **Step 2: Open the URL with a filter query param**

Open in a browser (or run):
```bash
curl -sI "http://localhost:8000/cars?filters%5B0%5D=make%3Ahonda"
```

Navigate to: `http://localhost:8000/cars?filters%5B0%5D=make%3Ahonda`
(That's `/cars?filters[0]=make%3Ahonda`, which is `filters[0]=make:honda`.)

- [ ] **Step 3: Confirm the chip is active**

Expected: the page loads the Cars Explorer with a `make:honda` chip active (amber background) and results filtered to Honda articles. The URL bar shows `?filters%5B0%5D=make%3Ahonda`.

**If this fails:** Livewire may use a different encoding. Try `?filters[]=make:honda` (without numeric key). Adjust the `handoffUrl()` method in Task 3 to use `array_values` without numeric keys (`http_build_query(['filters' => $chain], '', '&', PHP_QUERY_RFC3986)` drops keys when array values are strings with `[]` style — use `implode`-style URL building instead). Document the working format here before proceeding.

---

## Task 2 — `/explore` route + view

Move the global unscoped Explorer off the homepage and onto a dedicated route.

**Files:**
- Create: `resources/views/explore.blade.php`
- Modify: `routes/web.php`

**Interfaces:**
- Produces: route named `explore` at `/explore`; `/{locale}/explore` for non-default locales.

- [ ] **Step 1: Create `resources/views/explore.blade.php`**

```blade
@extends('layouts.app')

@section('title', __('Search') . ' — Hondabase')

@section('content')
    <section class="hero compact-hero">
        <div class="tag">Honda &amp; Acura &middot; {{ __('Technical Knowledgebase') }}</div>
        <h2>{{ __('Search the') }} <span class="accent">{{ __('whole') }}</span> {{ __('catalog.') }}</h2>
        <p>{{ __('Filter by category, OBD tag, chassis, engine family and more.') }}</p>
    </section>

    <livewire:explorer />
@endsection
```

- [ ] **Step 2: Add the `/explore` route to `routes/web.php`**

Open `routes/web.php`. After the `/sitemap.xml` route (line 81), insert:

```php
Route::get('/explore', fn () => view('explore'))->name('explore');
```

Also add the localized mirror in the `if ($locales !== '')` block. After the existing `Route::get('/{locale}/{type}', ...)` registration (around line 114), add:

```php
    Route::get('/{locale}/explore', fn () => view('explore'))
        ->where('locale', $locales)
        ->name('explore.localized');
```

- [ ] **Step 3: Run existing tests to confirm nothing broke**

```bash
php artisan test
```

Expected: all existing tests pass. The `/explore` route is additive.

- [ ] **Step 4: Smoke-test the new page**

Open `http://localhost:8000/explore` — expect the full Explorer with search box and all facet groups, identical to the current homepage Explorer.

- [ ] **Step 5: Commit**

```bash
git add resources/views/explore.blade.php routes/web.php
git commit -m "feat: add /explore global search page, move Explorer off homepage"
```

---

## Task 3 — `config/product_lines.php`

Static presentation map for product lines. Read by the Browser component to render root-level cards and breadcrumb type labels. Separates display concerns (labels, descriptions, order) from the DB taxonomy.

**Files:**
- Create: `config/product_lines.php`

**Interfaces:**
- Produces: `config('product_lines.lines')` — array of line definitions consumed by `Browser`.

- [ ] **Step 1: Create `config/product_lines.php`**

```php
<?php

/**
 * Presentation map for the Browser slide wizard's root product-line slide.
 * Keys: type (matches taxonomy_nodes.type), label, desc, coming_soon (optional bool).
 * Order here is the display order on the homepage.
 * Types listed as coming_soon have no taxonomy data yet; they render as greyed cards.
 * The 'engines' type is intentionally excluded — it is a cross-cutting subject accessible
 * via search and engine facets, not a product line users browse from the root.
 */
return [
    'lines' => [
        ['type' => 'cars',         'label' => 'Cars',          'desc' => 'Automobiles & light trucks'],
        ['type' => 'motorcycles',  'label' => 'Motorcycles',   'desc' => 'Road, off-road & sport bikes'],
        ['type' => 'aircraft',     'label' => 'Aircraft',      'desc' => 'HondaJet & turbofan engines'],
        ['type' => 'marine',       'label' => 'Marine',        'desc' => 'Outboard motors & jet boats',       'coming_soon' => true],
        ['type' => 'power-equipment', 'label' => 'Power Equipment', 'desc' => 'Generators, tillers & mowers', 'coming_soon' => true],
        ['type' => 'atv',          'label' => 'ATVs',          'desc' => 'All-terrain vehicles',              'coming_soon' => true],
        ['type' => 'side-by-side', 'label' => 'Side-by-Sides', 'desc' => 'Pioneer SxS series',               'coming_soon' => true],
    ],
];
```

- [ ] **Step 2: Verify the config loads**

```bash
php artisan tinker --execute="print_r(config('product_lines.lines'));"
```

Expected: array of 7 entries printed.

- [ ] **Step 3: Commit**

```bash
git add config/product_lines.php
git commit -m "feat: add product_lines config for Browser root slide"
```

---

## Task 4 — Browser component + tests (TDD)

The main wizard. Write failing tests first, then implement.

**Files:**
- Create: `tests/Feature/BrowserTest.php`
- Create: `app/Livewire/Browser.php`

**Interfaces:**
- Consumes: `config('product_lines.lines')`, `TaxonomyNode` (children, path, kind, slug, name, yearRange()), `Article` (locale, is_hidden, type), `ArticleFacet` (kind, value), `Locales::default()`, `Locales::isDefault()`.
- Produces: public `drillTo(string $path): void`; view data: `current` (?TaxonomyNode), `children` (Collection), `counts` (array slug→int), `breadcrumb` (array of ['label','path','current']), `suggestions` (Collection of TaxonomyNode), `handoffUrl` (?string).

### Step 1 — Write the failing tests

- [ ] **Step 1a: Create `tests/Feature/BrowserTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Livewire\Browser;
use App\Models\Article;
use App\Models\ArticleFacet;
use App\Models\TaxonomyNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrowserTest extends TestCase
{
    use RefreshDatabase;

    // --- root slide ---

    public function test_root_slide_shows_configured_product_lines(): void
    {
        Livewire::test(Browser::class)
            ->assertSee('Cars')
            ->assertSee('Motorcycles')
            ->assertSee('Aircraft');
    }

    public function test_root_slide_shows_coming_soon_entries(): void
    {
        Livewire::test(Browser::class)
            ->assertSee('Coming soon');
    }

    public function test_product_line_cards_show_article_counts(): void
    {
        Article::create([
            'type' => 'cars', 'category' => 'test', 'slug' => 'test-article',
            'locale' => 'en', 'title' => 'Test Article', 'body_text' => '',
            'repo_path' => 'cars/test/test-article/test-article.md',
        ]);

        Livewire::test(Browser::class)
            ->assertSee('1 article'); // cars count
    }

    // --- type-level slide ---

    public function test_drilling_to_type_shows_makes(): void
    {
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);

        Livewire::test(Browser::class)
            ->call('drillTo', 'cars')
            ->assertSee('Honda');
    }

    public function test_type_level_shows_no_breadcrumb_home_button_but_shows_type_label(): void
    {
        Livewire::test(Browser::class)
            ->call('drillTo', 'cars')
            ->assertSee('Cars'); // breadcrumb type label
    }

    // --- node-level slide ---

    public function test_drilling_to_node_shows_its_children(): void
    {
        $honda = TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => $honda->id,
        ]);

        Livewire::test(Browser::class)
            ->call('drillTo', 'cars/honda')
            ->assertSee('Civic');
    }

    public function test_leaf_node_has_empty_children_and_non_null_handoff_url(): void
    {
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);
        // No children for Honda.

        $component = Livewire::test(Browser::class)
            ->call('drillTo', 'cars/honda');

        $this->assertTrue($component->viewData('children')->isEmpty());
        $this->assertNotNull($component->viewData('handoffUrl'));
    }

    // --- breadcrumb ---

    public function test_breadcrumb_builds_from_drilled_path(): void
    {
        $honda = TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);
        $civic = TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => $honda->id,
        ]);

        $component = Livewire::test(Browser::class)
            ->call('drillTo', 'cars/honda/civic');

        $breadcrumb = $component->viewData('breadcrumb');
        $labels = array_column($breadcrumb, 'label');
        $this->assertContains('Cars', $labels);
        $this->assertContains('Honda', $labels);
        $this->assertContains('Civic', $labels);

        // Last item should be marked current.
        $last = end($breadcrumb);
        $this->assertTrue($last['current']);
        $this->assertEquals('Civic', $last['label']);
    }

    public function test_breadcrumb_type_item_links_back_to_type_level(): void
    {
        $honda = TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => $honda->id,
        ]);

        $component = Livewire::test(Browser::class)
            ->call('drillTo', 'cars/honda/civic');

        $breadcrumb = $component->viewData('breadcrumb');
        $typeCrumb = $breadcrumb[0];
        $this->assertEquals('cars', $typeCrumb['path']);
        $this->assertFalse($typeCrumb['current']);
    }

    // --- handoff URL ---

    public function test_handoff_url_for_type_level_is_bare_type_path(): void
    {
        $component = Livewire::test(Browser::class)
            ->call('drillTo', 'cars');

        $url = $component->viewData('handoffUrl');
        $this->assertEquals('/cars', $url);
    }

    public function test_handoff_url_contains_ancestor_filters(): void
    {
        $honda = TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => $honda->id,
        ]);

        $component = Livewire::test(Browser::class)
            ->call('drillTo', 'cars/honda/civic');

        $url = $component->viewData('handoffUrl');
        $this->assertStringStartsWith('/cars?', $url);
        $this->assertStringContainsString('make%3Ahonda', $url);  // make:honda URL-encoded
        $this->assertStringContainsString('model%3Acivic', $url); // model:civic URL-encoded
    }

    // --- counts ---

    public function test_child_counts_reflect_article_facets(): void
    {
        $honda = TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => $honda->id,
        ]);
        $article = Article::create([
            'type' => 'cars', 'category' => 'test', 'slug' => 'civic-guide',
            'locale' => 'en', 'title' => 'Civic Guide', 'body_text' => '',
            'repo_path' => 'cars/test/civic-guide/civic-guide.md',
        ]);
        ArticleFacet::create(['article_id' => $article->id, 'kind' => 'make',  'value' => 'honda', 'label' => 'Honda']);
        ArticleFacet::create(['article_id' => $article->id, 'kind' => 'model', 'value' => 'civic', 'label' => 'Civic']);

        $component = Livewire::test(Browser::class)
            ->call('drillTo', 'cars/honda');

        $counts = $component->viewData('counts');
        $this->assertEquals(1, $counts['civic']);
    }

    public function test_zero_count_nodes_have_count_zero_in_counts(): void
    {
        $honda = TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => $honda->id,
        ]);
        // No article_facets created → civic count stays at 0.

        $component = Livewire::test(Browser::class)
            ->call('drillTo', 'cars/honda');

        $counts = $component->viewData('counts');
        $this->assertEquals(0, $counts['civic'] ?? 0);
    }

    // --- typeahead ---

    public function test_typeahead_returns_nodes_matching_query(): void
    {
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => null,
        ]);

        $component = Livewire::test(Browser::class)
            ->set('bq', 'civ');

        $suggestions = $component->viewData('suggestions');
        $this->assertTrue($suggestions->contains('name', 'Civic'));
    }

    public function test_typeahead_is_empty_when_query_is_blank(): void
    {
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => null,
        ]);

        $component = Livewire::test(Browser::class); // bq defaults to ''

        $suggestions = $component->viewData('suggestions');
        $this->assertTrue($suggestions->isEmpty());
    }

    // --- navigation ---

    public function test_drillTo_root_clears_node(): void
    {
        Livewire::test(Browser::class)
            ->call('drillTo', 'cars')
            ->call('drillTo', '')
            ->assertSet('node', '');
    }

    public function test_drillTo_clears_search_query(): void
    {
        Livewire::test(Browser::class)
            ->set('bq', 'civic')
            ->call('drillTo', 'cars')
            ->assertSet('bq', '');
    }
}
```

- [ ] **Step 1b: Run tests to confirm they all fail (component does not exist yet)**

```bash
php artisan test tests/Feature/BrowserTest.php
```

Expected: all tests fail with "Class App\Livewire\Browser not found" or similar.

### Step 2 — Implement `app/Livewire/Browser.php`

- [ ] **Step 2a: Create `app/Livewire/Browser.php`**

```php
<?php

namespace App\Livewire;

use App\Models\Article;
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
```

- [ ] **Step 2b: Run the tests**

```bash
php artisan test tests/Feature/BrowserTest.php
```

Expected: all tests pass. If any fail, check the error message and fix the corresponding method before proceeding.

- [ ] **Step 2c: Run the full test suite to confirm no regressions**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 2d: Commit**

```bash
git add app/Livewire/Browser.php tests/Feature/BrowserTest.php
git commit -m "feat: add Browser Livewire component with TDD — slide wizard, counts, breadcrumb, handoff URL"
```

---

## Task 5 — Browser Blade view + CSS

**Files:**
- Create: `resources/views/livewire/browser.blade.php`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes view data: `$current` (?TaxonomyNode), `$children` (Collection), `$counts` (array), `$breadcrumb` (array), `$suggestions` (Collection), `$handoffUrl` (?string). Public properties `$node` and `$bq` are also auto-available.
- Reuses existing CSS classes: `.pl-card`, `.pl-card--soon`, `.pl-card-name`, `.pl-card-desc`, `.pl-card-tag`, `.pl-grid`, `.ex-input`, `.ex-search`, `.is-loading`.

- [ ] **Step 1: Create `resources/views/livewire/browser.blade.php`**

```blade
<div class="browser">

    {{-- ------------------------------------------------------------------ --}}
    {{-- Search box + typeahead                                             --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="br-search-wrap" x-data="{ open: @entangle('bq').live !== '' }">
        <div class="ex-search">
            <input type="search"
                   wire:model.live.debounce.300ms="bq"
                   placeholder="{{ __('Search models, chassis codes...') }}"
                   autocomplete="off"
                   class="ex-input"
                   aria-label="{{ __('Search products') }}"
                   x-on:input="open = $el.value.length > 0"
                   x-on:blur="setTimeout(() => open = false, 200)">
            @if (trim($bq) !== '')
                <a href="{{ route('explore') }}?q={{ urlencode($bq) }}"
                   class="br-search-all" wire:navigate>
                    {{ __('Search all articles for') }} &ldquo;{{ $bq }}&rdquo; &rarr;
                </a>
            @endif
        </div>

        @if ($suggestions->isNotEmpty())
            <div class="br-suggestions" x-show="open" x-cloak>
                @foreach ($suggestions as $s)
                    <button type="button"
                            class="br-suggestion"
                            wire:click="drillTo('{{ $s->path }}')"
                            wire:key="sug-{{ $s->id }}">
                        <span class="br-sug-name">{{ $s->name }}</span>
                        <span class="br-sug-meta">{{ ucfirst($s->type) }} &middot; {{ ucfirst($s->kind) }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Breadcrumb (hidden at root)                                         --}}
    {{-- ------------------------------------------------------------------ --}}
    @if (!empty($breadcrumb))
        <nav class="br-crumb" aria-label="{{ __('Browse path') }}">
            <button type="button"
                    class="br-crumb-home"
                    wire:click="drillTo('')"
                    title="{{ __('Product lines') }}"
                    aria-label="{{ __('Back to product lines') }}">
                <svg viewBox="0 0 16 16" width="13" height="13" fill="currentColor" aria-hidden="true">
                    <path d="M8 1L1 7h2v7h4v-4h2v4h4V7h2z"/>
                </svg>
            </button>
            <span class="br-crumb-sep" aria-hidden="true">/</span>
            @foreach ($breadcrumb as $crumb)
                @if ($crumb['current'])
                    <span class="br-crumb-current">{{ $crumb['label'] }}</span>
                @else
                    <button type="button"
                            class="br-crumb-link"
                            wire:click="drillTo('{{ $crumb['path'] }}')">{{ $crumb['label'] }}</button>
                @endif
                @if (!$loop->last)
                    <span class="br-crumb-sep" aria-hidden="true">/</span>
                @endif
            @endforeach
        </nav>
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Card grid                                                           --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="pl-grid" wire:loading.class="is-loading">

        @if ($node === '')
            {{-- Root slide: product lines from config --}}
            @foreach ($children as $line)
                @if (!empty($line['coming_soon']))
                    <div class="pl-card pl-card--soon">
                        <span class="pl-card-name">{{ $line['label'] }}</span>
                        <span class="pl-card-desc">{{ $line['desc'] }}</span>
                        <span class="pl-card-tag">{{ __('Coming soon') }}</span>
                    </div>
                @else
                    @php $count = $counts[$line['type']] ?? 0; @endphp
                    <button type="button"
                            class="pl-card"
                            wire:click="drillTo('{{ $line['type'] }}')">
                        <span class="pl-card-name">{{ $line['label'] }}</span>
                        <span class="pl-card-desc">{{ $line['desc'] }}</span>
                        <span class="pl-card-tag">
                            {{ $count }} {{ $count === 1 ? __('article') : __('articles') }}
                        </span>
                    </button>
                @endif
            @endforeach

        @else
            {{-- Type / node slide: taxonomy node children --}}
            @foreach ($children as $child)
                @php $count = $counts[$child->slug] ?? 0; @endphp
                <button type="button"
                        class="pl-card {{ $count === 0 ? 'pl-card--dim' : '' }}"
                        wire:click="drillTo('{{ $child->path }}')"
                        wire:key="node-{{ $child->id }}">
                    <span class="pl-card-name">{{ $child->name }}</span>
                    @if ($child->yearRange())
                        <span class="pl-card-desc">{{ $child->yearRange() }}</span>
                    @endif
                    <span class="pl-card-tag {{ $count === 0 ? 'text-muted' : '' }}">
                        {{ $count }} {{ $count === 1 ? __('article') : __('articles') }}
                    </span>
                </button>
            @endforeach

            {{-- See all articles — always visible at type/node level --}}
            @if ($handoffUrl)
                <a class="pl-card pl-card--cta" href="{{ $handoffUrl }}" wire:navigate>
                    <span class="pl-card-name">{{ __('See all articles') }} &rarr;</span>
                    @if ($current)
                        <span class="pl-card-desc">{{ $current->name }}</span>
                    @endif
                </a>
            @endif
        @endif

    </div>

</div>
```

- [ ] **Step 2: Add browser styles to `resources/css/app.css`**

Append the following block at the end of the `@layer components` block that contains `.explorer` (after the `.node-empty` rule, around line 1744):

```css
/* Browser slide wizard */
.browser { position: relative; }

.br-search-wrap { position: relative; margin-bottom: 1.25rem; }
.br-search-all {
  font-family: var(--font-mono); font-size: .72rem; color: var(--color-amber);
  white-space: nowrap; flex-shrink: 0;
}
.br-search-all:hover { color: var(--color-amber-2); }

.br-suggestions {
  position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 20;
  background: var(--color-bg-2); border: 1px solid var(--color-border-2);
  display: flex; flex-direction: column; max-height: 300px; overflow-y: auto;
}
.br-suggestion {
  display: flex; justify-content: space-between; align-items: baseline;
  gap: .5rem; padding: .55rem .9rem;
  background: none; border: 0; border-bottom: 1px solid var(--color-border); cursor: pointer;
  text-align: left; width: 100%;
}
.br-suggestion:last-child { border-bottom: 0; }
.br-suggestion:hover { background: var(--color-panel-hover); }
.br-sug-name { font-family: var(--font-display); font-size: .88rem; color: var(--color-amber-2); }
.br-sug-meta { font-family: var(--font-mono); font-size: .64rem; color: var(--color-muted); text-transform: uppercase; letter-spacing: .06em; }

.br-crumb {
  display: flex; align-items: center; flex-wrap: wrap;
  gap: .3rem; margin-bottom: 1.1rem;
  font-family: var(--font-mono); font-size: .72rem;
}
.br-crumb-home {
  background: none; border: 0; padding: .1rem .25rem; cursor: pointer;
  color: var(--color-dim); display: flex; align-items: center;
}
.br-crumb-home:hover { color: var(--color-amber); }
.br-crumb-sep { color: var(--color-muted); user-select: none; }
.br-crumb-link {
  background: none; border: 0; padding: .1rem .15rem; cursor: pointer;
  color: var(--color-amber); font-family: var(--font-mono); font-size: .72rem;
}
.br-crumb-link:hover { color: var(--color-amber-2); }
.br-crumb-current { color: var(--color-txt); padding: .1rem .15rem; }

/* pl-card extensions for the browser */
button.pl-card {
  /* reset button defaults while keeping the pl-card visual */
  text-align: left; cursor: pointer; font-family: inherit; background: var(--color-panel);
  border: 1px solid var(--color-border); width: 100%;
}
button.pl-card:hover {
  border-color: rgba(251, 191, 36, 0.5);
  background: var(--color-panel-hover);
  transform: translateY(-2px);
}
.pl-card--dim { opacity: 0.38; }
.pl-card--dim:hover { opacity: 0.65; }
.pl-card--cta {
  border-color: rgba(251, 191, 36, 0.25);
  background: rgba(251, 191, 36, 0.04);
}
.pl-card--cta:hover {
  border-color: rgba(251, 191, 36, 0.6);
  background: rgba(251, 191, 36, 0.08);
  transform: translateY(-2px);
}
.pl-card--cta .pl-card-name { color: var(--color-amber); }
```

- [ ] **Step 3: Build assets and smoke-test the component in isolation**

```bash
pnpm run build
```

Then open any page that has the Explorer (e.g. `/cars`) to confirm CSS builds cleanly.

- [ ] **Step 4: Run the tests**

```bash
php artisan test tests/Feature/BrowserTest.php
```

Expected: all pass (the view is now rendered by the tests too).

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/browser.blade.php resources/css/app.css
git commit -m "feat: add Browser Blade view + CSS for slide wizard, breadcrumb, typeahead"
```

---

## Task 6 — Homepage swap

Replace the hardcoded product-line grid + `<livewire:explorer />` with `<livewire:browser />`.

**Files:**
- Modify: `resources/views/home.blade.php`

**Interfaces:**
- Consumes: `<livewire:browser />` (registered automatically by Livewire via the `Browser` class).

- [ ] **Step 1: Replace `resources/views/home.blade.php`**

```blade
@extends('layouts.app')

@section('title', 'Hondabase')

@section('content')
    <section class="hero compact-hero">
        <div class="tag">Honda &amp; Acura &middot; Technical Knowledgebase</div>
        <h2>Find your <span class="accent">vehicle.</span></h2>
        <p>Browse by product line, or type to jump straight to your model.</p>
    </section>

    <livewire:browser />

    <section>
        <div class="callout prose">
            <p>Found a gap or an error? Sign in with Discord to suggest an edit (reviewed before
            it goes live), or join the community on Discord and GitHub.</p>
            <a class="btn" href="https://discord.hondabase.com">Join the Discord &rarr;</a>
        </div>
    </section>
@endsection
```

- [ ] **Step 2: Run the full test suite**

```bash
php artisan test
```

Expected: all tests pass, including the canonical URL test that asserts the homepage content.

If the canonical URL test (`tests/Feature/CanonicalUrlTest.php` or similar) fails because it was asserting the old hero text ("Explore the whole catalog."), update its assertion to match the new heading ("Find your vehicle.").

- [ ] **Step 3: Manual smoke test**

With `php artisan serve` running:

1. Open `http://localhost:8000/` — expect: no product-line grid, no raw Explorer facets; instead a search box + card grid with Cars/Motorcycles/Aircraft + coming-soon cards.
2. Click **Cars** — expect: breadcrumb shows "Cars", grid shows Honda/Acura (if those make nodes exist in the DB).
3. Click a make — expect: grid shows models with article counts.
4. Click **See all articles** — expect: navigates to `/cars?filters%5B0%5D=...` with Explorer active-chip filtering.
5. Click a breadcrumb segment — expect: returns to that drill level.
6. Type "civic" in the search box — expect: typeahead dropdown shows Civic node.
7. Click "Search all articles for..." — expect: navigates to `/explore?q=civic`.
8. Open `http://localhost:8000/explore` — expect: full unscoped Explorer (identical to old homepage).
9. Resize to mobile width — expect: card grid goes vertical (1 column from the `.pl-grid` `minmax(180px, 1fr)` rule).

- [ ] **Step 4: Commit**

```bash
git add resources/views/home.blade.php
git commit -m "feat: replace homepage product-line grid + Explorer with Browser slide wizard"
```

---

## Self-Review Checklist

**Spec coverage:**

| Requirement | Task |
|---|---|
| Search + browse coexist | Task 4/5 — search box + `bq` typeahead alongside card grid |
| Persistent search box on top | Task 5 — `br-search-wrap` at top of browser template |
| Card-grid slides with breadcrumb | Task 4/5 — `pl-grid` + `br-crumb` |
| Brand is its own slide | Handled data-driven: makes are root TaxonomyNodes, rendered as slide 2 |
| Variable depth (lawnmower case) | Task 4 — `children()` renders whatever `parent->children()` returns; leaf nodes get "See all" only |
| Per-node article counts | Task 4 — `childCounts()` method; zero = greyed in Task 5 view |
| Zero-count greyed | Task 5 — `.pl-card--dim` class applied when `$count === 0` |
| Landing reuses existing Explorer | Task 2 — `/explore` view; Tasks 4/5 — `handoffUrl()` links to `/{type}?filters[]=...` |
| Handoff with active facet chips | `http_build_query(['filters' => $chain])` → Livewire `#[Url] $filters` |
| Locale-prefixed handoff URLs | `Locales::isDefault()` check in `handoffUrl()` |
| Product-line grid from DB, not hardcoded | `config/product_lines.php` drives labels; live counts from DB |
| Hardcoded homepage grid removed | Task 6 — `home.blade.php` replaced |
| Shareability (URL-bound state) | `#[Url(as:'node')]` — every slide is bookmarkable |
| Typeahead quick-jump | `suggestions()` method + `br-suggestions` dropdown |
| "Search all articles" escape | `br-search-all` link → `/explore?q=...` |
| `/explore` global search page | Task 2 |
| Mobile vertical layout | Reuses existing `.pl-grid` `minmax(180px, 1fr)` auto-fill (collapses to 1 col on narrow) |

**Placeholder scan:** No TBDs or TODOs in plan. All code blocks are complete.

**Type consistency:** `drillTo(string $path)` used in tests, view (`wire:click="drillTo('...')`), and component. `$handoffUrl` passed from `render()` and used in view. `$counts` keyed by slug string → matches `$child->slug` in view. `$breadcrumb` shape `['label','path','current']` matches test assertions and view iteration.
