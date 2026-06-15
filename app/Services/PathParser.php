<?php

namespace App\Services;

use App\Models\TaxonomyNode;

/**
 * Splits an article's category path into its taxonomy-node prefix and its subject remainder, the
 * heart of the hybrid storage model. Within a `type`, a category path is
 * `[taxonomy nodes...][subject...]`: leading segments that form a real taxonomy node path are the
 * vehicle/product context (cars/honda/civic/eg), and whatever remains is the subject (engine, or a
 * generic article's electronics/ecu when no node matches at all).
 *
 * Node paths are loaded once and matched as array lookups (the sync may parse hundreds of articles).
 */
class PathParser
{
    /** @var array<string, TaxonomyNode>|null path => node */
    private ?array $byPath = null;

    /**
     * @return array{node: ?TaxonomyNode, node_path: string, node_slugs: array<int,string>, subject: string, subject_slug: string}
     */
    public function parse(string $type, string $category): array
    {
        $byPath = $this->index();
        $segments = array_values(array_filter(explode('/', trim($category, '/')), fn ($s) => $s !== ''));

        $matched = null;
        $nodeSlugs = [];
        $acc = $type;
        foreach ($segments as $seg) {
            $candidate = "{$acc}/{$seg}";
            if (! isset($byPath[$candidate])) {
                break; // first non-node segment begins the subject
            }
            $matched = $byPath[$candidate];
            $nodeSlugs[] = $seg;
            $acc = $candidate;
        }

        $subjectSegments = array_slice($segments, count($nodeSlugs));

        return [
            'node' => $matched,
            'node_path' => implode('/', $nodeSlugs),
            'node_slugs' => $nodeSlugs,
            'subject' => implode('/', $subjectSegments),
            'subject_slug' => $subjectSegments[0] ?? '',
        ];
    }

    /** Drop the cached node map (call after the sync reseeds taxonomy within one process). */
    public function forget(): void
    {
        $this->byPath = null;
    }

    /** @return array<string, TaxonomyNode> */
    private function index(): array
    {
        return $this->byPath ??= TaxonomyNode::all()->keyBy('path')->all();
    }
}
