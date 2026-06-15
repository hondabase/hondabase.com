<?php

namespace App\Livewire;

use App\Jobs\CommitArticle;
use App\Models\ArticleRevision;
use App\Services\ArticleService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Owner-only review queue (the approval gate). Each pending edit shows a compact diff; the
 * owner approves it (queuing App\Jobs\CommitArticle to commit + push with attribution) or
 * rejects it with a note. A banner surfaces edits committed locally but not yet pushed.
 */
class RevisionReview extends Component
{
    /** Per-revision reject/approve note, keyed by revision id. */
    public array $notes = [];

    public ?string $message = null;

    public function mount(): void
    {
        abort_unless(Gate::allows('manage-articles'), 403);
    }

    public function approve(int $id): void
    {
        abort_unless(Gate::allows('manage-articles'), 403);

        $rev = ArticleRevision::pending()->find($id);
        if ($rev === null) {
            $this->message = "Edit #{$id} is no longer pending.";

            return;
        }

        $rev->update([
            'status' => 'approved',
            'reviewer_id' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $this->notes[$id] ?? null,
        ]);

        CommitArticle::dispatch($rev->id);

        unset($this->notes[$id]);
        $this->message = "Edit #{$id} approved and queued for commit.";
    }

    public function reject(int $id): void
    {
        abort_unless(Gate::allows('manage-articles'), 403);

        // A reviewer can discard either a pending edit or one parked as conflicted.
        $rev = ArticleRevision::whereIn('status', ['pending', 'conflicted'])->find($id);
        if ($rev === null) {
            $this->message = "Edit #{$id} is not awaiting review.";

            return;
        }

        $rev->update([
            'status' => 'rejected',
            'reviewer_id' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $this->notes[$id] ?? null,
        ]);
        $rev->cleanupStagedAssets();

        unset($this->notes[$id]);
        $this->message = "Edit #{$id} rejected.";
    }

    /**
     * Re-base a conflicted edit onto the current on-disk content and re-queue it. The editor's
     * proposed body is kept; only the base it diffs against is refreshed, so the next commit
     * attempt either applies cleanly or (if it still conflicts) parks itself again. This never
     * discards the reviewer's intent: it just acknowledges the article moved and re-approves.
     */
    public function rebase(int $id): void
    {
        abort_unless(Gate::allows('manage-articles'), 403);

        $rev = ArticleRevision::conflicted()->find($id);
        if ($rev === null) {
            $this->message = "Edit #{$id} is not awaiting conflict resolution.";

            return;
        }

        $raw = app(ArticleService::class)->rawMarkdown($rev->type, $rev->category, $rev->slug, $rev->locale ?? 'en');
        if ($raw === null) {
            $this->message = "The article for edit #{$id} no longer exists on disk; reject it instead.";

            return;
        }

        $rev->update([
            'status' => 'approved',
            'base_sha' => $raw['sha'],
            'original_body' => $raw['content'], // re-base onto current on-disk for an honest diff
            'reviewer_id' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $this->notes[$id] ?? $rev->review_notes,
        ]);

        CommitArticle::dispatch($rev->id);

        unset($this->notes[$id]);
        $this->message = "Edit #{$id} re-based onto the current article and re-queued for commit.";
    }

    public function render(): View
    {
        abort_unless(Gate::allows('manage-articles'), 403);

        return view('livewire.revision-review', [
            'pending' => ArticleRevision::pending()->with('author')->latest()->get(),
            'conflicted' => ArticleRevision::conflicted()->with('author')->latest()->get(),
            'unpushed' => ArticleRevision::unpushed()->count(),
        ]);
    }
}
