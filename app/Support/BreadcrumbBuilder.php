<?php

namespace App\Support;

use App\Models\Subject;
use App\Models\TaxonomyNode;
use Illuminate\Support\Str;

/**
 * Builds breadcrumb segments for a category path, labelling each segment richly: a segment that
 * forms a taxonomy node path gets the node's display name ("5th Gen (EG)"), a subject segment gets
 * its Subject name ("Engine & Drivetrain"), and anything unknown falls back to a humanized slug.
 * Returns ['name' => ..., 'url' => '/{locale?}/{type}/{cumulative}'] per segment (no type crumb,
 * matching the existing article header which shows the type in its kicker).
 */
class BreadcrumbBuilder
{
    /** @return list<array{name: string, url: string}> */
    public function forCategory(string $type, string $category, string $locale = 'en'): array
    {
        $prefix = Locales::isDefault($locale) ? '' : "/{$locale}";
        $segments = array_values(array_filter(explode('/', trim($category, '/')), fn ($s) => $s !== ''));

        $crumbs = [];
        $catAcc = '';
        $nodeAcc = $type;
        $inNodeChain = true;
        foreach ($segments as $seg) {
            $catAcc = $catAcc === '' ? $seg : "{$catAcc}/{$seg}";
            $nodeAcc = "{$nodeAcc}/{$seg}";

            $name = null;
            if ($inNodeChain && ($node = TaxonomyNode::where('path', $nodeAcc)->first())) {
                $name = $node->name; // taxonomy node (make/model/generation/...)
            } else {
                $inNodeChain = false; // once a segment isn't a node, the rest is subject/slug
                $name = Subject::where('slug', $seg)->value('name') ?? Str::headline($seg);
            }

            $crumbs[] = ['name' => $name, 'url' => "{$prefix}/{$type}/{$catAcc}"];
        }

        return $crumbs;
    }
}
