<?php

namespace App\Livewire;

use App\Jobs\CommitArticle;
use App\Livewire\Concerns\EditsFrontmatter;
use App\Livewire\Concerns\ManagesArticleImages;
use App\Markdown\CarouselParser;
use App\Markdown\WirelistParser;
use App\Models\ArticleRevision;
use App\Services\ArticleService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Create a brand-new article bundle. A signed-in (guild-gated) user picks the location
 * (type / category / slug), writes the body in the TipTap rich-text canvas, fills in the
 * structured frontmatter fields, and may upload co-located images, all with the same live preview
 * as a published page. Submitting records an App\Models\ArticleRevision exactly like an edit: a
 * member's goes to the review queue, a staff member's auto-applies. On commit the recomposed .md
 * and its uploaded images land together in one path-limited commit.
 *
 * Mobile-first: editor and preview are tabs on a phone, side-by-side on a wide screen.
 */
class ArticleCreator extends Component
{
    use EditsFrontmatter;
    use ManagesArticleImages;

    public string $type = 'cars';

    public string $category = '';

    public string $slug = '';

    /** The TipTap-edited body Markdown (frontmatter optional, first H1 = title). */
    public string $bodyMarkdown = "# New article title\n\nStart writing your article here.\n";

    /** The note shown to the reviewer (stored as the revision's changelog note). */
    public string $note = '';

    /** UI only; the authoritative auto-apply decision is re-checked server-side in submit(). */
    public bool $canManage = false;

    public function mount(): void
    {
        abort_unless(Auth::check(), 403);
        $this->canManage = Gate::allows('manage-articles');
    }

    /** Keep the slug in sync with the title until the user types their own slug. */
    public function updatedBodyMarkdown(): void
    {
        if ($this->slug === '') {
            $title = $this->previewData()['title'] ?? '';
            // only autofill from a real title, not the placeholder
            if ($title !== '' && $title !== 'New article title') {
                $this->slug = Str::slug($title);
            }
        }
    }

    /** Type/category folders that already exist, to offer as a datalist. */
    #[Computed]
    public function categoryOptions(): array
    {
        return array_map(fn ($c) => $c['slug'], app(ArticleService::class)->categories($this->type));
    }

    #[Computed]
    public function preview(): array
    {
        return $this->previewData();
    }

    private function previewData(): array
    {
        return app(ArticleService::class)->preview(
            $this->composedDocument(),
            $this->type,
            $this->cleanSlug($this->category),
            $this->cleanSlug($this->slug),
            $this->uploadedPreviewUrls(),
        );
    }

    public function submit()
    {
        $this->category = $this->cleanSlug($this->category);
        $this->slug = $this->cleanSlug($this->slug);

        $this->validate([
            'type' => ['required', 'in:'.implode(',', app(ArticleService::class)->types())],
            'category' => ['required', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'slug' => ['required', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'bodyMarkdown' => ['required', 'string', 'min:20'],
            'note' => ['nullable', 'string', 'max:500'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096'],
        ], [], ['category' => 'category', 'slug' => 'slug', 'bodyMarkdown' => 'article']);

        if ($error = app(CarouselParser::class)->errors($this->bodyMarkdown)[0] ?? null) {
            $this->addError('bodyMarkdown', $error);

            return null;
        }
        if ($error = app(WirelistParser::class)->errors($this->bodyMarkdown)[0] ?? null) {
            $this->addError('bodyMarkdown', $error);

            return null;
        }

        $svc = app(ArticleService::class);

        if ($svc->exists($this->type, $this->category, $this->slug)) {
            $this->addError('slug', __('An article already exists at that location. Edit it instead.'));

            return null;
        }

        $composed = $this->composedDocument();
        $repoPath = "{$this->type}/{$this->category}/{$this->slug}/{$this->slug}.md";
        $manage = Gate::allows('manage-articles');

        $rev = ArticleRevision::create([
            'user_id' => Auth::id(),
            'type' => $this->type,
            'category' => $this->category,
            'slug' => $this->slug,
            'title' => $svc->preview($composed, $this->type, $this->category, $this->slug)['title'],
            'repo_path' => $repoPath,
            'base_sha' => $svc->currentSha(),
            'original_body' => '', // new file: there is nothing on disk to base on yet
            'proposed_body' => $composed,
            'assets' => null,
            'summary' => $this->note !== '' ? $this->note : null,
            'status' => $manage ? 'approved' : 'pending',
            'reviewer_id' => $manage ? Auth::id() : null,
            'reviewed_at' => $manage ? now() : null,
        ]);

        $names = $this->stageReferencedUploads($rev, $composed);
        $rev->forceFill(['assets' => $names ?: null])->save();

        if ($manage) {
            CommitArticle::dispatch($rev->id);
            session()->flash('status', __('Created. Your new article (#:id) was published and committed; it can be reverted from history.', ['id' => $rev->id]));

            return $this->redirect($rev->url(), navigate: true);
        }

        session()->flash('status', __('Thanks. Your new article (#:id) was submitted for review and will go live once approved.', ['id' => $rev->id]));

        return $this->redirect('/', navigate: true);
    }

    private function cleanSlug(string $s): string
    {
        return Str::slug($s);
    }

    public function render(): View
    {
        return view('livewire.article-creator');
    }
}
