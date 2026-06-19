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
        'category' => 'Categories', 'engine' => 'Engine family',
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
        $kv = $this->normalizeFilter($kv);
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
        $this->filters = $this->normalizeFilters($this->filters);

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
            $like = $this->like($term);
            $terms = $this->searchTerms($term);
            $locale = app()->getLocale();
            $query->where(function ($w) use ($like, $terms, $locale) {
                $w->where(function ($phrase) use ($like) {
                    $this->whereArticleMatches($phrase, $like, 'articles');
                });
                if ($terms !== []) {
                    $w->orWhere(function ($tokens) use ($terms) {
                        foreach ($terms as $word) {
                            $tokens->where(function ($x) use ($word) {
                                $this->whereArticleMatches($x, $this->like($word), 'articles');
                            });
                        }
                    });
                }
                // Under a non-default locale, also match the article's translation row so a
                // term that only appears in the translated text still finds it.
                if (! Locales::isDefault($locale)) {
                    $w->orWhereExists(function ($sub) use ($like, $terms, $locale) {
                        $sub->select(DB::raw(1))
                            ->from('articles as t')
                            ->whereColumn('t.type', 'articles.type')
                            ->whereColumn('t.category', 'articles.category')
                            ->whereColumn('t.slug', 'articles.slug')
                            ->where('t.locale', $locale)
                            ->where(function ($x) use ($like, $terms) {
                                $x->where(function ($phrase) use ($like) {
                                    $this->whereArticleMatches($phrase, $like, 't');
                                });
                                if ($terms !== []) {
                                    $x->orWhere(function ($tokens) use ($terms) {
                                        foreach ($terms as $word) {
                                            $tokens->where(function ($token) use ($word) {
                                                $this->whereArticleMatches($token, $this->like($word), 't');
                                            });
                                        }
                                    });
                                }
                            });
                    });
                }
            });
        }

        foreach ($this->filters as $kv) {
            [$kind, $value] = array_pad(explode(':', $this->normalizeFilter($kv), 2), 2, '');
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

    private function whereArticleMatches($query, string $like, string $table): void
    {
        $query->where("{$table}.title", 'like', $like)
            ->orWhere("{$table}.summary", 'like', $like)
            ->orWhere("{$table}.body_text", 'like', $like)
            ->orWhere("{$table}.slug", 'like', $like)
            ->orWhere("{$table}.category", 'like', $like);
    }

    /** @return list<string> */
    private function searchTerms(string $term): array
    {
        preg_match_all('/[[:alnum:]]+/u', mb_strtolower($term), $matches);

        return array_values(array_unique(array_filter($matches[0] ?? [], fn (string $word) => $word !== '')));
    }

    private function like(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
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

        $order = ['category', 'engine', 'tag', 'chassis', 'ecu', 'model', 'brand', 'scope', 'system', 'year'];
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
            $kv = $this->normalizeFilter($kv);
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

    private function normalizeFilters(array $filters): array
    {
        return array_values(array_unique(array_map(fn ($kv) => $this->normalizeFilter((string) $kv), $filters)));
    }

    private function normalizeFilter(string $kv): string
    {
        [$kind, $value] = array_pad(explode(':', $kv, 2), 2, '');
        if ($kind !== 'obd') {
            return $kv;
        }

        $value = strtolower(trim($value));
        if ($value === '') {
            return $kv;
        }

        return 'tag:'.(str_starts_with($value, 'obd') ? $value : 'obd'.$value);
    }
}
