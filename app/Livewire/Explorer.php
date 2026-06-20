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
        'tag' => 'Tags', 'chassis' => 'Chassis', 'model' => 'Models',
        'make' => 'Make', 'scope' => 'Scope', 'system' => 'Systems', 'year' => 'Years',
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
            'isStaff' => (bool) auth()->user()?->isStaff(),
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

        if (! auth()->user()?->isStaff()) {
            $query->where('is_hidden', false);
        }

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
            $terms = $this->searchTerms($term);
            $locale = app()->getLocale();
            $query->where(function ($all) use ($terms, $locale) {
                foreach ($terms as $word) {
                    $all->where(function ($one) use ($word, $locale) {
                        $like = $this->like($word);
                        $this->whereSearchTermMatches($one, $like);
                        if (! Locales::isDefault($locale)) {
                            $one->orWhereExists(function ($sub) use ($like, $locale) {
                                $sub->select(DB::raw(1))
                                    ->from('articles as t')
                                    ->whereColumn('t.type', 'articles.type')
                                    ->whereColumn('t.category', 'articles.category')
                                    ->whereColumn('t.slug', 'articles.slug')
                                    ->where('t.locale', $locale)
                                    ->where(function ($t) use ($like) {
                                        $this->whereArticleMatches($t, $like, 't');
                                    });
                            });
                        }
                    });
                }
            });

            [$scoreSql, $scoreBindings] = $this->searchScore($term, $terms, $locale);
            $query->addSelect('articles.*')->selectRaw($scoreSql.' as search_score', $scoreBindings);
        }

        foreach ($this->filters as $kv) {
            [$kind, $value] = array_pad(explode(':', $this->normalizeFilter($kv), 2), 2, '');
            $values = $this->filterValues($kind, $value);
            $query->whereExists(function ($sub) use ($kind, $values) {
                $sub->select(DB::raw(1))
                    ->from('article_facets')
                    ->whereColumn('article_facets.article_id', 'articles.id')
                    ->where('kind', $kind)
                    ->whereIn('value', $values);
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
            ->when(trim($this->q) !== '', fn ($q) => $q->orderByDesc('search_score'))
            ->orderByDesc('view_count')
            ->orderByRaw('updated_at IS NULL, updated_at DESC')
            ->orderBy('title')
            ->limit(60)
            ->get();
    }

    private function whereSearchTermMatches($query, string $like): void
    {
        $this->whereArticleMatches($query, $like, 'articles');
        $query->orWhereExists(function ($sub) use ($like) {
            $sub->select(DB::raw(1))
                ->from('article_facets as sf')
                ->whereColumn('sf.article_id', 'articles.id')
                ->where(function ($facet) use ($like) {
                    $facet->where('sf.kind', 'like', $like)
                        ->orWhere('sf.value', 'like', $like)
                        ->orWhere('sf.label', 'like', $like);
                });
        })->orWhereExists(function ($sub) use ($like) {
            $sub->select(DB::raw(1))
                ->from('compatibilities as sc')
                ->join('taxonomy_nodes as st', 'st.id', '=', 'sc.taxonomy_node_id')
                ->whereColumn('sc.article_id', 'articles.id')
                ->where(function ($tax) use ($like) {
                    $tax->where('st.kind', 'like', $like)
                        ->orWhere('st.slug', 'like', $like)
                        ->orWhere('st.name', 'like', $like)
                        ->orWhere('st.path', 'like', $like)
                        ->orWhere('st.meta', 'like', $like);
                });
        });
    }

    private function whereArticleMatches($query, string $like, string $table): void
    {
        $query->where("{$table}.title", 'like', $like)
            ->orWhere("{$table}.summary", 'like', $like)
            ->orWhere("{$table}.body_text", 'like', $like)
            ->orWhere("{$table}.slug", 'like', $like)
            ->orWhere("{$table}.category", 'like', $like)
            ->orWhere("{$table}.type", 'like', $like)
            ->orWhere("{$table}.complexity", 'like', $like)
            ->orWhere("{$table}.repo_path", 'like', $like);
    }

    /** @return array{0: string, 1: list<string>} */
    private function searchScore(string $phrase, array $terms, string $locale): array
    {
        $parts = [];
        $bindings = [];
        $add = function (string $sql, array $values = []) use (&$parts, &$bindings): void {
            $parts[] = $sql;
            array_push($bindings, ...$values);
        };

        $phraseLike = $this->like($phrase);
        foreach ([
            ['articles.title', 120],
            ['articles.slug', 90],
            ['articles.category', 70],
            ['articles.summary', 55],
            ['articles.body_text', 25],
        ] as [$column, $weight]) {
            $add("CASE WHEN {$column} LIKE ? THEN {$weight} ELSE 0 END", [$phraseLike]);
        }
        $add('CASE WHEN EXISTS (SELECT 1 FROM article_facets sp WHERE sp.article_id = articles.id AND (sp.kind LIKE ? OR sp.value LIKE ? OR sp.label LIKE ?)) THEN 70 ELSE 0 END', [$phraseLike, $phraseLike, $phraseLike]);
        $add('CASE WHEN EXISTS (SELECT 1 FROM compatibilities scp JOIN taxonomy_nodes stp ON stp.id = scp.taxonomy_node_id WHERE scp.article_id = articles.id AND (stp.kind LIKE ? OR stp.slug LIKE ? OR stp.name LIKE ? OR stp.path LIKE ? OR stp.meta LIKE ?)) THEN 70 ELSE 0 END', [$phraseLike, $phraseLike, $phraseLike, $phraseLike, $phraseLike]);

        foreach ($terms as $word) {
            $like = $this->like($word);
            foreach ([
                ['articles.title', 30],
                ['articles.slug', 26],
                ['articles.category', 22],
                ['articles.summary', 16],
                ['articles.type', 10],
                ['articles.complexity', 8],
                ['articles.repo_path', 8],
                ['articles.body_text', 5],
            ] as [$column, $weight]) {
                $add("CASE WHEN {$column} LIKE ? THEN {$weight} ELSE 0 END", [$like]);
            }
            $add('CASE WHEN EXISTS (SELECT 1 FROM article_facets sfp WHERE sfp.article_id = articles.id AND (sfp.kind LIKE ? OR sfp.value LIKE ? OR sfp.label LIKE ?)) THEN 20 ELSE 0 END', [$like, $like, $like]);
            $add('CASE WHEN EXISTS (SELECT 1 FROM compatibilities sct JOIN taxonomy_nodes stt ON stt.id = sct.taxonomy_node_id WHERE sct.article_id = articles.id AND (stt.kind LIKE ? OR stt.slug LIKE ? OR stt.name LIKE ? OR stt.path LIKE ? OR stt.meta LIKE ?)) THEN 18 ELSE 0 END', [$like, $like, $like, $like, $like]);

            if (! Locales::isDefault($locale)) {
                foreach ([
                    ['title', 24],
                    ['slug', 20],
                    ['category', 16],
                    ['summary', 14],
                    ['body_text', 5],
                ] as [$column, $weight]) {
                    $add("CASE WHEN EXISTS (SELECT 1 FROM articles tr WHERE tr.type = articles.type AND tr.category = articles.category AND tr.slug = articles.slug AND tr.locale = ? AND tr.{$column} LIKE ?) THEN {$weight} ELSE 0 END", [$locale, $like]);
                }
            }
        }

        return ['('.implode(' + ', $parts).')', $bindings];
    }

    /** @return list<string> */
    private function searchTerms(string $term): array
    {
        preg_match_all('/[[:alnum:]]+/u', mb_strtolower($term), $matches);

        $terms = array_values(array_unique(array_filter($matches[0] ?? [], fn (string $word) => $word !== '')));

        return $terms !== [] ? $terms : [$term];
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
            ->orderByDesc('view_count')
            ->orderByRaw('updated_at IS NULL, updated_at DESC')
            ->orderBy('title')
            ->limit(12)
            ->get(['id', 'type', 'category', 'slug', 'title', 'summary', 'updated_at', 'view_count']);
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

        // Engine family facets are only relevant for engine-adjacent categories.
        if (! in_array($this->scopeCategory, ['ecu', 'ignition', 'fueling', 'tuning'], true)) {
            unset($byKind['engine']);
        }

        $order = ['category', 'engine', 'tag', 'chassis', 'model', 'make', 'scope', 'system', 'year'];
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

    /** @return list<string> */
    private function filterValues(string $kind, string $value): array
    {
        if ($kind === 'tag' && $value === 'serial-communication') {
            return ['serial-communication', 'serial', 'serial communication'];
        }

        return [$value];
    }
}
