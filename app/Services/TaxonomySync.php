<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\TaxonomyNode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports the product taxonomy + subjects from a JSON seed into the taxonomy_nodes/subjects tables.
 *
 * The tables are the LIVE source of truth (edited via the taxonomy control panel and distributed in
 * the public DB dump) - this importer only *bootstraps* them from the shipped seed files
 * (database/data/*.json) via `php artisan hondabase:taxonomy:seed`, or loads a fixture in tests. It
 * is intentionally NOT part of `hondabase:reindex`, so a reindex never clobbers control-panel edits.
 */
class TaxonomySync
{
    /**
     * Replace the taxonomy + subjects with the contents of the given seed files (a missing file is
     * treated as empty). Destructive: callers guard against clobbering live edits.
     *
     * @return array{nodes:int, subjects:int}
     */
    public function import(string $taxonomyFile, string $subjectsFile): array
    {
        return DB::transaction(function () use ($taxonomyFile, $subjectsFile) {
            TaxonomyNode::query()->delete();
            foreach ($this->json($taxonomyFile) as $type => $tree) {
                if ($type === '_comment' || ! is_array($tree)) {
                    continue;
                }
                $this->insertTree($type, null, $type, $tree);
            }

            Subject::query()->delete();
            foreach ($this->json($subjectsFile)['subjects'] ?? [] as $slug => $name) {
                Subject::create(['slug' => $slug, 'name' => $name]);
            }

            return ['nodes' => TaxonomyNode::count(), 'subjects' => Subject::count()];
        });
    }

    /** Register a subject slug discovered in content if it isn't already present. */
    public function ensureSubject(string $slug): void
    {
        if ($slug !== '' && ! Subject::where('slug', $slug)->exists()) {
            Subject::create(['slug' => $slug, 'name' => Str::headline($slug)]);
        }
    }

    private function insertTree(string $type, ?int $parentId, string $parentPath, array $nodes): void
    {
        foreach ($nodes as $slug => $def) {
            if ($slug === '_comment' || ! is_array($def)) {
                continue;
            }
            $path = "{$parentPath}/{$slug}";
            $node = TaxonomyNode::create([
                'parent_id' => $parentId,
                'type' => $type,
                'kind' => $def['kind'] ?? 'node',
                'slug' => $slug,
                'name' => $def['name'] ?? Str::headline($slug),
                'path' => $path,
                'meta' => $def['meta'] ?? null,
            ]);
            if (! empty($def['children']) && is_array($def['children'])) {
                $this->insertTree($type, $node->id, $path, $def['children']);
            }
        }
    }

    private function json(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }
}
