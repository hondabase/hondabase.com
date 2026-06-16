<?php

namespace App\Livewire;

use App\Models\Article;
use App\Models\ArticleFacet;
use App\Support\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The knowledgebase explorer. As the query or facet filters change, the whole surface
 * re-renders in place (content-shifting, not autocomplete). On a category page it is scoped
 * to that category with an "Everything" escape (context-aware search). For a signed-in user
 * it is opinionated: followed content floats up and a "For you" row leads the page.
 */
class Explorer extends Component
{
    #[Url(as: 'q')]
    public string $q = '';

    /** Active facet filters, each "kind:value". */
    #[Url]
    public array $filters = [];

    // Page scope (set on category pages); $scopeAll widens back to everything.
    public ?string $scopeType = null;

    public ?string $scopeCategory = null;

    public bool $scopeAll = false;

    private const KIND_LABELS = [
        'category' => 'Categories', 'engine' => 'Engine family', 'obd' => 'OBD',
        'tag' => 'Tags', 'chassis' => 'Chassis', 'ecu' => 'ECUs', 'model' => 'Models',
        'brand' => 'Brand', 'scope' => 'Scope', 'system' => 'Systems', 'year' => 'Years',
    ];

    public function mount(?string $type = null, ?string $category = null): void
    {
        $this->scopeType = $type;
        $this->scopeCategory = $category;
    }

    public function toggleFilter(string $kv): void
    {
        $this->filters = in_array($kv, $this->filters, true)
            ? array_values(array_diff($this->filters, [$kv]))
            : array_values([...$this->filters, $kv]);
    }

    public function clearAll(): void
    {
        $this->filters = [];
        $this->q = '';
    }

    /** Follow/unfollow a facet (add it to the user's interests). Login required. */
    public function toggleFollow(string $kv)
    {
        $user = auth()->user();
        if (! $user) {
            return redirect()->route('login');
        }
        [$kind, $value] = array_pad(explode(':', $kv, 2), 2, '');
        $existing = $user->follows()->where('kind', $kind)->where('value', $value)->first();
        if ($existing) {
            $existing->delete();

            return null;
        }
        $label = ArticleFacet::where('kind', $kind)->where('value', $value)->value('label') ?: $value;
        $user->follows()->create(['kind' => $kind, 'value' => $value, 'label' => $label]);

        return null;
    }

    public function render(): View
    {
        $query = $this->baseQuery();
        $followed = $this->followedSet();
        $personalize = $followed && trim($this->q) === '' && empty($this->filters) && ! $this->scopeType;

        return view('livewire.explorer', [
            'articles' => $this->localize($this->articles(clone $query, $followed, $personalize)),
            'groups' => $this->facetGroups(clone $query),
            'activeLabels' => $this->activeLabels(),
            'total' => (clone $query)->count(),
            'followed' => $followed,
            'isAuthed' => (bool) auth()->user(),
            'forYou' => $personalize ? $this->localize($this->forYou($followed)) : collect(),
            'scoped' => $this->scopeType !== null,
            'scopeAll' => $this->scopeAll,
            'scopeLabel' => $this->scopeCategory
                ? ucwords(str_replace('-', ' ', $this->scopeCategory))
                : ($this->scopeType ? ucfirst($this->scopeType) : ''),
        ]);
    }

    /** "kind:value" strings the current user follows. */
    private function followedSet(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        return $user->follows()->get(['kind', 'value'])
            ->map(fn ($f) => $f->kind.':'.$f->value)->all();
    }

    private function baseQuery()
    {
        // The canonical result set is the default-locale rows: facets, follows and counts all
        // hang off them. Translation rows are only consulted to broaden search and to overlay
        // titles/summaries for display (see localize()).
        $query = Article::query()->where('articles.locale', Locales::default());

        if ($this->scopeType && ! $this->scopeAll) {
            $query->where('type', $this->scopeType);
            if ($this->scopeCategory) {
                // Match the category itself and any nested descendant (electronics also shows
                // electronics/ecu/... articles), so a parent-category page drills down.
                $query->where(function ($w) {
                    $w->where('category', $this->scopeCategory)
                        ->orWhere('category', 'like', $this->scopeCategory.'/%');
                });
            }
        }

        $term = trim($this->q);
        if ($term !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
            $locale = app()->getLocale();
            $query->where(function ($w) use ($like, $locale) {
                $w->where('articles.title', 'like', $like)
                    ->orWhere('articles.summary', 'like', $like)
                    ->orWhere('articles.body_text', 'like', $like);
                // Under a non-default locale, also match the article's translation row so a
                // term that only appears in the translated text still finds it.
                if (! Locales::isDefault($locale)) {
                    $w->orWhereExists(function ($sub) use ($like, $locale) {
                        $sub->select(DB::raw(1))
                            ->from('articles as t')
                            ->whereColumn('t.type', 'articles.type')
                            ->whereColumn('t.category', 'articles.category')
                            ->whereColumn('t.slug', 'articles.slug')
                            ->where('t.locale', $locale)
                            ->where(function ($x) use ($like) {
                                $x->where('t.title', 'like', $like)
                                    ->orWhere('t.summary', 'like', $like)
                                    ->orWhere('t.body_text', 'like', $like);
                            });
                    });
                }
            });
        }

        foreach ($this->filters as $kv) {
            [$kind, $value] = array_pad(explode(':', $kv, 2), 2, '');
            $query->whereExists(function ($sub) use ($kind, $value) {
                $sub->select(DB::raw(1))
                    ->from('article_facets')
                    ->whereColumn('article_facets.article_id', 'articles.id')
                    ->where('kind', $kind)
                    ->where('value', $value);
            });
        }

        return $query;
    }

    private function articles($query, array $followed, bool $personalize)
    {
        if ($personalize && $followed) {
            $place = implode(',', array_fill(0, count($followed), '?'));
            $query->selectRaw(
                "articles.*, (SELECT COUNT(*) FROM article_facets af WHERE af.article_id = articles.id AND CONCAT(af.kind, ':', af.value) IN ($place)) as follow_score",
                $followed
            )->orderByDesc('follow_score');
        }

        return $query
            ->orderByRaw('updated_at IS NULL, updated_at DESC')
            ->orderBy('title')
            ->limit(60)
            ->get();
    }

    /** Recent articles matching anything the user follows. */
    private function forYou(array $followed)
    {
        if (empty($followed)) {
            return collect();
        }
        $place = implode(',', array_fill(0, count($followed), '?'));

        return Article::query()
            ->whereRaw(
                "EXISTS (SELECT 1 FROM article_facets af WHERE af.article_id = articles.id AND CONCAT(af.kind, ':', af.value) IN ($place))",
                $followed
            )
            ->orderByRaw('updated_at IS NULL, updated_at DESC')
            ->orderBy('title')
            ->limit(12)
            ->get(['id', 'type', 'category', 'slug', 'title', 'summary', 'updated_at']);
    }

    /** Facet groups + counts over the current result set (this is the content shift). */
    private function facetGroups($query): array
    {
        $rows = ArticleFacet::query()
            ->select('kind', 'value', 'label', DB::raw('COUNT(*) as c'))
            ->whereIn('article_id', $query->select('articles.id'))
            ->groupBy('kind', 'value', 'label')
            ->orderByDesc('c')
            ->get();

        $byKind = [];
        foreach ($rows as $r) {
            $byKind[$r->kind][] = $r;
        }

        // On a scoped (single category) page, the category facet is redundant.
        if ($this->scopeCategory && ! $this->scopeAll) {
            unset($byKind['category']);
        }

        $order = ['category', 'engine', 'obd', 'tag', 'chassis', 'ecu', 'model', 'brand', 'scope', 'system', 'year'];
        $limits = ['tag' => 18];
        $groups = [];
        foreach ([...$order, ...array_diff(array_keys($byKind), $order)] as $kind) {
            if ($kind === 'type' || empty($byKind[$kind])) {
                continue;
            }
            $groups[$kind] = [
                'label' => self::KIND_LABELS[$kind] ?? ucfirst(str_replace('_', ' ', $kind)),
                'items' => array_slice($byKind[$kind], 0, $limits[$kind] ?? 12),
            ];
        }

        return $groups;
    }

    private function activeLabels(): array
    {
        $out = [];
        foreach ($this->filters as $kv) {
            [$kind, $value] = array_pad(explode(':', $kv, 2), 2, '');
            $label = ArticleFacet::where('kind', $kind)->where('value', $value)->value('label');
            $out[$kv] = $label ?: $value;
        }

        return $out;
    }

    /**
     * For a non-default locale, overlay each canonical row with its translated title/summary
     * (where a translation exists) and stamp the active locale so url() yields the /{locale}
     * link. Rows without a translation keep their English text but still link to the localized
     * URL (which falls back to English on the article page).
     */
    private function localize($collection)
    {
        $locale = app()->getLocale();
        if (Locales::isDefault($locale) || $collection->isEmpty()) {
            return $collection;
        }

        $slugs = $collection->pluck('slug')->unique()->all();
        $trans = Article::query()
            ->where('locale', $locale)
            ->whereIn('slug', $slugs)
            ->get(['type', 'category', 'slug', 'title', 'summary'])
            ->keyBy(fn ($t) => "{$t->type}/{$t->category}/{$t->slug}");

        foreach ($collection as $a) {
            $a->locale = $locale;
            if ($t = $trans->get("{$a->type}/{$a->category}/{$a->slug}")) {
                $a->title = $t->title;
                if ($t->summary) {
                    $a->summary = $t->summary;
                }
            }
        }

        return $collection;
    }
}
