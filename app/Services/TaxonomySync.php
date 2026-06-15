<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\TaxonomyNode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the DERIVED taxonomy_nodes + subjects tables from the forkable source of truth
 * (content/_data/taxonomy.json + subjects.json). Run as part of the full reindex so a fresh
 * clone restores the whole catalog (forkability invariant). Idempotent: it rebuilds both tables.
 */
class TaxonomySync
{
    /** @return array{nodes:int, subjects:int} */
    public function sync(): array
    {
        $root = rtrim((string) config('hondabase.content_path'), '/');

        return DB::transaction(function () use ($root) {
            TaxonomyNode::query()->delete();
            foreach ($this->json("{$root}/_data/taxonomy.json") as $type => $tree) {
                if ($type === '_comment' || ! is_array($tree)) {
                    continue;
                }
                $this->insertTree($type, null, $type, $tree);
            }

            Subject::query()->delete();
            $subjects = $this->json("{$root}/_data/subjects.json")['subjects'] ?? [];
            foreach ($subjects as $slug => $name) {
                Subject::create(['slug' => $slug, 'name' => $name]);
            }

            return ['nodes' => TaxonomyNode::count(), 'subjects' => Subject::count()];
        });
    }

    /** Register a subject slug discovered in content if it isn't already seeded. */
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
