<?php

namespace App\Livewire;

use App\Jobs\CommitArticle;
use App\Models\ArticleRevision;
use App\Services\ArticleService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Staff change log + reversibility. Scoped to one article it lists every revision (pending,
 * applied, rejected, reverts) newest-first with diffs; unscoped it shows recent applied edits
 * across all articles. Reverting an applied edit does NOT rewrite history: it records a new
 * tracked revision whose committed content restores the pre-edit snapshot, so the revert is
 * itself a normal, revertible commit.
 */
class ArticleHistory extends Component
{
    public ?string $type = null;

    public ?string $category = null;

    public ?string $slug = null;

    public string $articleTitle = '';

    public ?string $message = null;

    public function mount(?string $type = null, ?string $category = null, ?string $slug = null): void
    {
        abort_unless(Gate::allows('manage-articles'), 403);

        $this->type = $type;
        $this->category = $category;
        $this->slug = $slug;

        if ($this->scoped()) {
            $raw = app(ArticleService::class)->rawMarkdown($type, $category, $slug);
            $this->articleTitle = $raw['title'] ?? $slug;
        }
    }

    public function scoped(): bool
    {
        return $this->type !== null && $this->category !== null && $this->slug !== null;
    }

    /** Restore the article to the state *before* the given applied edit, as a new commit. */
    public function revert(int $id): void
    {
        abort_unless(Gate::allows('manage-articles'), 403);

        $target = ArticleRevision::applied()->find($id);
        if ($target === null) {
            $this->message = "Edit #{$id} is not an applied change, so there is nothing to revert.";

            return;
        }

        $svc = app(ArticleService::class);
        $raw = $svc->rawMarkdown($target->type, $target->category, $target->slug);
        if ($raw === null) {
            $this->message = "The article for edit #{$id} no longer exists on disk.";

            return;
        }

        $restore = $target->original_body; // the snapshot from before that edit
        if ($this->normalize($restore) === $this->normalize($raw['content'])) {
            $this->message = "The article already matches the state before edit #{$id}; nothing to do.";

            return;
        }

        $rev = ArticleRevision::create([
            'user_id' => Auth::id(),
            'type' => $target->type,
            'category' => $target->category,
            'slug' => $target->slug,
            'title' => $svc->preview($restore, $target->type, $target->category, $target->slug)['title'],
            'repo_path' => $raw['repo_path'],
            'base_sha' => $raw['sha'],
            'original_body' => $raw['content'], // current on-disk, for an accurate diff
            'proposed_body' => $restore,
            'summary' => 'Revert of edit #'.$id.($target->summary ? ' ('.$target->summary.')' : ''),
            'status' => 'approved',
            'reviewer_id' => Auth::id(),
            'reviewed_at' => now(),
            'reverts_revision_id' => $target->id,
        ]);

        CommitArticle::dispatch($rev->id);
        $this->message = "Reverting edit #{$id}: restored the prior version as #{$rev->id}, committing now.";
    }

    public function render(): View
    {
        abort_unless(Gate::allows('manage-articles'), 403);

        $query = ArticleRevision::with(['author', 'reviewer', 'reverts'])->latest();
        $revisions = $this->scoped()
            ? $query->where('type', $this->type)->where('category', $this->category)->where('slug', $this->slug)->get()
            : $query->applied()->limit(50)->get();

        return view('livewire.article-history', [
            'revisions' => $revisions,
            'unpushed' => ArticleRevision::unpushed()->count(),
        ]);
    }

    private function normalize(string $s): string
    {
        return rtrim(str_replace("\r\n", "\n", $s))."\n";
    }
}
