<?php

namespace App\Services;

use App\Models\TaxonomyNode;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Resolves which taxonomy nodes an article fits, and the node-derived facets that make it
 * discoverable up the tree. Three sources:
 *   - inherited: the article's folder path resolves to a node (PathParser)
 *   - explicit:  a `fits:` list of node paths (with optional trim/notes/tags)
 *   - bridge:    legacy `applies_to` chassis/models/trims values mapped onto nodes, so the existing
 *                496-article corpus gains node links without rewriting front matter
 *
 * Output feeds the compatibilities pivot + article_facets (so the explorer/follows keep working).
 * Node lookups are cached for the whole sync run; call forget() after the taxonomy is reseeded.
 */
class CompatibilityResolver
{
    private ?Collection $nodes = null;

    public function __construct(private PathParser $parser) {}

    /**
     * @return array{links: array<int, array{source: string, meta: ?array}>, facets: array<int, array{0:string,1:string,2:string}>}
     */
    public function resolve(string $type, string $category, array $fm): array
    {
        $links = [];

        // 1. Inherited from the folder location (the deepest matched node).
        $inherited = $this->parser->parse($type, $category)['node'];
        if ($inherited) {
            $links[$inherited->id] = ['source' => 'inherited', 'meta' => null];
        }

        // 2. Explicit `fits:` entries -> node paths.
        foreach ($this->fitsList($fm) as $fit) {
            $node = $this->byPath((string) ($fit['path'] ?? ''));
            if ($node && ! isset($links[$node->id])) {
                $links[$node->id] = ['source' => 'explicit', 'meta' => Arr::only($fit, ['trim', 'notes', 'tags']) ?: null];
            }
        }

        // 3. Bridge: legacy applies_to values -> nodes (chassis code, model slug, trim slug).
        $at = is_array($fm['applies_to'] ?? null) ? $fm['applies_to'] : [];
        $bridge = function (TaxonomyNode $node, string $via) use (&$links) {
            if (! isset($links[$node->id])) {
                $links[$node->id] = ['source' => 'explicit', 'meta' => ['via' => $via]];
            }
        };
        foreach ((array) ($at['chassis'] ?? []) as $code) {
            foreach ($this->byChassis($type, (string) $code) as $node) {
                $bridge($node, 'applies_to.chassis');
            }
        }
        foreach ((array) ($at['models'] ?? []) as $m) {
            foreach ($this->bySlug($type, 'model', Str::slug((string) $m)) as $node) {
                $bridge($node, 'applies_to.models');
            }
        }
        foreach ((array) ($at['trims'] ?? []) as $tr) {
            foreach ($this->bySlug($type, 'trim', Str::slug((string) $tr)) as $node) {
                $bridge($node, 'applies_to.trims');
            }
        }

        // Facets: every linked node plus its ancestors becomes a (kind, slug, name) facet so the
        // article surfaces under make/model/generation drill-down.
        $facets = [];
        foreach (array_keys($links) as $nodeId) {
            foreach ($this->ancestorsAndSelf($nodeId) as $node) {
                $facets[] = [$node->kind, $node->slug, $node->name];
            }
        }

        return ['links' => $links, 'facets' => $facets];
    }

    public function forget(): void
    {
        $this->nodes = null;
        $this->parser->forget();
    }

    /** @return list<array<string,mixed>> */
    private function fitsList(array $fm): array
    {
        $fits = $fm['fits'] ?? [];
        if (! is_array($fits)) {
            return [];
        }

        // Accept either a list of {path: ...} maps or a bare list of path strings.
        return array_map(fn ($f) => is_array($f) ? $f : ['path' => (string) $f], array_values($fits));
    }

    private function nodes(): Collection
    {
        return $this->nodes ??= TaxonomyNode::all();
    }

    private function byPath(string $path): ?TaxonomyNode
    {
        $path = trim($path, '/');

        return $path === '' ? null : $this->nodes()->firstWhere('path', $path);
    }

    /** @return Collection<int, TaxonomyNode> */
    private function byChassis(string $type, string $code): Collection
    {
        $code = strtolower(trim($code));

        return $this->nodes()->filter(fn (TaxonomyNode $n) => $n->type === $type && in_array($code, $n->chassisCodes(), true))->values();
    }

    /** @return Collection<int, TaxonomyNode> */
    private function bySlug(string $type, string $kind, string $slug): Collection
    {
        return $this->nodes()->filter(fn (TaxonomyNode $n) => $n->type === $type && $n->kind === $kind && $n->slug === $slug)->values();
    }

    /** @return list<TaxonomyNode> the node and each ancestor up to the root */
    private function ancestorsAndSelf(int $nodeId): array
    {
        $byId = $this->nodes()->keyBy('id');
        $chain = [];
        $cursor = $byId->get($nodeId);
        while ($cursor) {
            $chain[] = $cursor;
            $cursor = $cursor->parent_id ? $byId->get($cursor->parent_id) : null;
        }

        return $chain;
    }
}
