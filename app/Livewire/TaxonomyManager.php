<?php

namespace App\Livewire;

use App\Models\Article;
use App\Models\Subject;
use App\Models\TaxonomyNode;
use App\Services\ArticleIndexer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Staff/owner control panel for the product taxonomy (the DB is the live source of truth). Add,
 * edit, rename and remove taxonomy nodes + subjects directly. Because a node's slug is also a
 * content folder name, a slug rename or removal is blocked when articles are physically filed under
 * the node (it would orphan their folders); adding nodes and editing metadata are always free.
 * Article compatibility links update on the next "Rebuild article links" (a reindex).
 *
 * Admin surface: English only (see the i18n-admin-views convention).
 */
class TaxonomyManager extends Component
{
    public array $types = [];

    // Node form
    public ?int $nodeId = null;

    public string $nodeType = 'cars';

    public ?int $nodeParentId = null;

    public string $nodeKind = 'model';

    public string $nodeSlug = '';

    public string $nodeName = '';

    public string $nodeChassis = '';

    public ?int $nodeStartYear = null;

    public ?int $nodeEndYear = null;

    public bool $showNodeForm = false;

    // Subject form
    public ?int $subjectId = null;

    public string $subjectSlug = '';

    public string $subjectName = '';

    public ?string $message = null;

    public function mount(): void
    {
        abort_unless(Gate::allows('manage-articles'), 403);
        $this->types = (array) config('hondabase.types', []);
    }

    public function newNode(string $type, ?int $parentId = null): void
    {
        $this->resetNodeForm();
        $this->nodeType = $parentId ? TaxonomyNode::find($parentId)?->type ?? $type : $type;
        $this->nodeParentId = $parentId;
        $this->showNodeForm = true;
    }

    public function editNode(int $id): void
    {
        $node = TaxonomyNode::findOrFail($id);
        $this->nodeId = $node->id;
        $this->nodeType = $node->type;
        $this->nodeParentId = $node->parent_id;
        $this->nodeKind = $node->kind;
        $this->nodeSlug = $node->slug;
        $this->nodeName = $node->name;
        $this->nodeChassis = implode(', ', $node->meta['chassis_codes'] ?? []);
        $this->nodeStartYear = $node->meta['start_year'] ?? null;
        $this->nodeEndYear = $node->meta['end_year'] ?? null;
        $this->showNodeForm = true;
    }

    public function saveNode(): void
    {
        abort_unless(Gate::allows('manage-articles'), 403);

        $this->validate([
            'nodeKind' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9-]*$/'],
            'nodeSlug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'nodeName' => ['required', 'string', 'max:120'],
            'nodeStartYear' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'nodeEndYear' => ['nullable', 'integer', 'min:1900', 'max:2100'],
        ]);

        $parent = $this->nodeParentId ? TaxonomyNode::find($this->nodeParentId) : null;
        $type = $parent?->type ?? $this->nodeType;
        $newPath = ($parent ? $parent->path : $type).'/'.$this->nodeSlug;

        // Path uniqueness (excluding self on edit).
        $clash = TaxonomyNode::where('path', $newPath)->when($this->nodeId, fn ($q) => $q->whereKeyNot($this->nodeId))->exists();
        if ($clash) {
            $this->addError('nodeSlug', 'A node already exists at '.$newPath.'.');

            return;
        }

        $meta = array_filter([
            'chassis_codes' => $this->csv($this->nodeChassis) ?: null,
            'start_year' => $this->nodeStartYear,
            'end_year' => $this->nodeEndYear,
        ], fn ($v) => $v !== null && $v !== []);

        if ($this->nodeId) {
            $node = TaxonomyNode::findOrFail($this->nodeId);
            // A slug change moves the node's folder identity; block it if content lives under it.
            if ($node->path !== $newPath && $this->hasInheritedContent($node)) {
                $this->addError('nodeSlug', 'Cannot rename: articles are filed under this node. Move them first.');

                return;
            }
            $oldPath = $node->path;
            $node->update(['kind' => $this->nodeKind, 'name' => $this->nodeName, 'slug' => $this->nodeSlug, 'path' => $newPath, 'meta' => $meta ?: null]);
            if ($oldPath !== $newPath) {
                $this->repathDescendants($oldPath, $newPath);
            }
            $this->message = "Updated {$newPath}.";
        } else {
            TaxonomyNode::create([
                'parent_id' => $parent?->id,
                'type' => $type,
                'kind' => $this->nodeKind,
                'slug' => $this->nodeSlug,
                'name' => $this->nodeName,
                'path' => $newPath,
                'meta' => $meta ?: null,
            ]);
            $this->message = "Added {$newPath}.";
        }

        $this->resetNodeForm();
    }

    public function deleteNode(int $id): void
    {
        abort_unless(Gate::allows('manage-articles'), 403);

        $node = TaxonomyNode::find($id);
        if ($node === null) {
            return;
        }
        if ($this->hasInheritedContent($node)) {
            $this->message = "Cannot delete {$node->path}: articles are filed under it. Move them first.";

            return;
        }
        // Delete the whole subtree (node + descendants) - none of it has inherited content.
        $path = $node->path;
        TaxonomyNode::where('path', $path)->orWhere('path', 'like', $path.'/%')->delete();
        $this->message = "Removed {$path} (and any sub-nodes).";
    }

    public function saveSubject(): void
    {
        abort_unless(Gate::allows('manage-articles'), 403);

        $this->validate([
            'subjectSlug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'subjectName' => ['required', 'string', 'max:120'],
        ]);

        $clash = Subject::where('slug', $this->subjectSlug)->when($this->subjectId, fn ($q) => $q->whereKeyNot($this->subjectId))->exists();
        if ($clash) {
            $this->addError('subjectSlug', 'That subject slug already exists.');

            return;
        }

        Subject::updateOrCreate(['id' => $this->subjectId], ['slug' => $this->subjectSlug, 'name' => $this->subjectName]);
        $this->message = 'Subject saved.';
        $this->reset(['subjectId', 'subjectSlug', 'subjectName']);
    }

    public function editSubject(int $id): void
    {
        $s = Subject::findOrFail($id);
        $this->subjectId = $s->id;
        $this->subjectSlug = $s->slug;
        $this->subjectName = $s->name;
    }

    public function deleteSubject(int $id): void
    {
        abort_unless(Gate::allows('manage-articles'), 403);
        Subject::whereKey($id)->delete();
        $this->message = 'Subject removed.';
    }

    /** Rebuild the derived article index so compatibility links reflect taxonomy changes. */
    public function rebuildIndex(ArticleIndexer $indexer): void
    {
        abort_unless(Gate::allows('manage-articles'), 403);
        $c = $indexer->indexAll();
        $this->message = "Rebuilt: {$c['articles']} articles, {$c['compatibilities']} compatibility links.";
    }

    public function cancelNode(): void
    {
        $this->resetNodeForm();
    }

    private function resetNodeForm(): void
    {
        $this->reset(['nodeId', 'nodeParentId', 'nodeSlug', 'nodeName', 'nodeChassis', 'nodeStartYear', 'nodeEndYear', 'showNodeForm']);
        $this->nodeKind = 'model';
    }

    /** True if any article is physically filed under this node's path (inherited content). */
    private function hasInheritedContent(TaxonomyNode $node): bool
    {
        $category = Str::after($node->path, $node->type.'/');

        return Article::where('type', $node->type)
            ->where(fn ($q) => $q->where('category', $category)->orWhere('category', 'like', $category.'/%'))
            ->exists();
    }

    private function repathDescendants(string $oldPath, string $newPath): void
    {
        foreach (TaxonomyNode::where('path', 'like', $oldPath.'/%')->get() as $desc) {
            $desc->update(['path' => $newPath.substr($desc->path, strlen($oldPath))]);
        }
    }

    private function csv(string $s): array
    {
        return array_values(array_filter(array_map(fn ($p) => strtolower(trim($p)), explode(',', $s)), fn ($p) => $p !== ''));
    }

    public function render(): View
    {
        abort_unless(Gate::allows('manage-articles'), 403);

        return view('livewire.taxonomy-manager', [
            'nodesByType' => TaxonomyNode::orderBy('path')->get()->groupBy('type'),
            'subjects' => Subject::orderBy('slug')->get(),
        ]);
    }
}
