<?php

namespace App\Livewire;

use App\Jobs\CommitArticle;
use App\Models\ArticleRevision;
use App\Services\ArticleService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * In-browser article editor. A signed-in (guild-gated) user edits the article's markdown and
 * sees a live, content-shifting preview rendered by the very same pipeline as a published page.
 *
 * A member's submission records a *pending* App\Models\ArticleRevision for staff review (no git
 * yet). A staff member (manage-articles) self-approves: the revision is committed immediately.
 * Either way the change is tracked as a revision + git commit, and is revertible from history.
 *
 * Editing is mobile-first: the editor and preview are tabs on a phone so neither crowds the
 * other, and the whole flow works thumb-first.
 */
class ArticleEditor extends Component
{
    public string $type;
    public string $category;
    public string $slug;

    public string $repoPath = '';
    public ?string $baseSha = null;
    public string $articleTitle = '';

    /** The on-disk markdown at load time (frontmatter + body); used to detect real changes. */
    public string $original = '';

    /** The editable markdown (whole file) and the editor's reason for the change. */
    public string $body = '';
    public string $summary = '';

    /** UI only (button/copy). The authoritative auto-apply decision is re-checked in submit(). */
    public bool $canManage = false;

    public function mount(string $type, string $category, string $slug, ArticleService $articles): void
    {
        abort_unless(Auth::check(), 403);

        $raw = $articles->rawMarkdown($type, $category, $slug);
        abort_if($raw === null, 404);

        $this->type     = $type;
        $this->category = $category;
        $this->slug     = $slug;
        $this->repoPath = $raw['repo_path'];
        $this->baseSha  = $raw['sha'];
        $this->articleTitle = $raw['title'];
        $this->original = $raw['content'];
        $this->body     = $raw['content'];
        $this->canManage = Gate::allows('manage-articles');
    }

    /** Live preview of the current editor contents, rendered exactly like a published page. */
    #[Computed]
    public function preview(): array
    {
        return app(ArticleService::class)->preview($this->body, $this->type, $this->category, $this->slug);
    }

    public function submit()
    {
        $this->validate([
            'body'    => ['required', 'string', 'min:20'],
            'summary' => ['nullable', 'string', 'max:500'],
        ], [], ['body' => 'article', 'summary' => 'summary']);

        // Never trust the client-held repo_path / base_sha / original: re-derive them on the
        // server from the (path-validated) type/category/slug, so a tampered Livewire payload
        // cannot redirect the write to an arbitrary file. rawMarkdown() runs the safe() guard
        // and confirms the article still exists.
        $svc = app(ArticleService::class);
        $raw = $svc->rawMarkdown($this->type, $this->category, $this->slug);
        abort_if($raw === null, 404);

        if ($this->normalize($this->body) === $this->normalize($raw['content'])) {
            $this->addError('body', 'No changes to submit. Edit the article first.');
            return null;
        }

        // Authoritative check (never trust the client-held $canManage): staff self-approve.
        $manage = Gate::allows('manage-articles');

        $rev = ArticleRevision::create([
            'user_id'       => Auth::id(),
            'type'          => $this->type,
            'category'      => $this->category,
            'slug'          => $this->slug,
            'title'         => $svc->preview($this->body, $this->type, $this->category, $this->slug)['title'],
            'repo_path'     => $raw['repo_path'],
            'base_sha'      => $raw['sha'],
            'original_body' => $raw['content'],
            'proposed_body' => $this->body,
            'summary'       => $this->summary !== '' ? $this->summary : null,
            'status'        => $manage ? 'approved' : 'pending',
            'reviewer_id'   => $manage ? Auth::id() : null,
            'reviewed_at'   => $manage ? now() : null,
        ]);

        if ($manage) {
            CommitArticle::dispatch($rev->id);
            session()->flash('status', 'Published. Your change (#' . $rev->id . ') was applied and committed; it can be reverted from history.');
        } else {
            session()->flash('status', 'Thanks. Your edit (#' . $rev->id . ') was submitted for review and will go live once approved.');
        }

        return $this->redirect($rev->url(), navigate: true);
    }

    /** Normalize trailing whitespace + final newline so a no-op save is detected reliably. */
    private function normalize(string $s): string
    {
        return rtrim(str_replace("\r\n", "\n", $s)) . "\n";
    }

    public function render(): View
    {
        return view('livewire.article-editor');
    }
}
