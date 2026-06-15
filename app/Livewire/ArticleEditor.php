<?php

namespace App\Livewire;

use App\Jobs\CommitArticle;
use App\Livewire\Concerns\EditsFrontmatter;
use App\Livewire\Concerns\ManagesArticleImages;
use App\Markdown\CarouselParser;
use App\Markdown\WirelistParser;
use App\Models\ArticleRevision;
use App\Services\ArticleService;
use App\Support\ArticleDocument;
use App\Support\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * In-browser article editor. A signed-in (guild-gated) user edits the article's body in a TipTap
 * rich-text canvas and its metadata via structured frontmatter fields, with a live, content-shifting
 * preview rendered by the very same pipeline as a published page. The body + frontmatter are
 * recomposed into raw Markdown on save, so an approved edit still commits a plain Markdown file.
 *
 * A member's submission records a *pending* App\Models\ArticleRevision for staff review (no git
 * yet). A staff member (manage-articles) self-approves: the revision is committed immediately.
 * Either way the change is tracked as a revision + git commit, and is revertible from history.
 *
 * Mobile-first: editor and preview are tabs on a phone, side-by-side on a wide screen.
 */
class ArticleEditor extends Component
{
    use EditsFrontmatter;
    use ManagesArticleImages;

    public string $type;

    public string $category;

    public string $slug;

    /** Target locale of this edit: the default ('en') edits the canonical bundle; another locale
     *  (e.g. 'pt') authors a translation written to the /{locale}/... mirror path. */
    public string $locale = 'en';

    /** True when editing a non-default locale (a translation rather than the English source). */
    public bool $isTranslation = false;

    /** True when this is the first time a translation is being written (no file on disk yet). */
    public bool $isNewTranslation = false;

    public string $repoPath = '';

    public ?string $baseSha = null;

    public string $articleTitle = '';

    /** The on-disk file at load time (frontmatter + body); used to detect a real change (no-op guard). */
    public string $original = '';

    /** The TipTap-edited body Markdown (no frontmatter); recombined with the structured fields on save. */
    public string $bodyMarkdown = '';

    /** The editor's reason for the change, stored as the revision's changelog note. */
    public string $note = '';

    /** UI only (button/copy). The authoritative auto-apply decision is re-checked in submit(). */
    public bool $canManage = false;

    public function mount(string $type, string $category, string $slug, ArticleService $articles, string $locale = 'en'): void
    {
        abort_unless(Auth::check(), 403);
        abort_unless(Locales::isSupported($locale), 404);

        $raw = $articles->rawMarkdown($type, $category, $slug, $locale);
        abort_if($raw === null, 404);

        $this->type = $type;
        $this->category = $category;
        $this->slug = $slug;
        $this->locale = $locale;
        $this->isTranslation = ! Locales::isDefault($locale);
        $this->isNewTranslation = $this->isTranslation && ! $raw['exists'];
        $this->repoPath = $raw['repo_path'];
        $this->baseSha = $raw['sha'];
        $this->articleTitle = $raw['title'];
        // The on-disk base the edit diffs against: the translation file ('' for a brand-new one),
        // never the English seed, so the conflict check has nothing to clobber on first save.
        $this->original = $raw['content'];

        // A new translation pre-fills the canvas from the English source so the translator works
        // from a structural copy; an existing translation (or any English edit) loads its own file.
        $doc = ArticleDocument::parse($this->isNewTranslation ? (string) $raw['seed'] : $raw['content']);
        $this->bodyMarkdown = $doc['body'];
        $this->hydrateFrontmatter($doc['fm']);

        $this->canManage = Gate::allows('manage-articles');
    }

    /** Live preview of the current editor contents, rendered exactly like a published page. */
    #[Computed]
    public function preview(): array
    {
        return app(ArticleService::class)->preview(
            $this->composedDocument(),
            $this->type,
            $this->category,
            $this->slug,
            $this->uploadedPreviewUrls(),
        );
    }

    public function submit()
    {
        $this->validate([
            'bodyMarkdown' => ['required', 'string', 'min:20'],
            'note' => ['nullable', 'string', 'max:500'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096'],
        ], [], ['bodyMarkdown' => 'article', 'note' => 'note']);

        if ($error = app(CarouselParser::class)->errors($this->bodyMarkdown)[0] ?? null) {
            $this->addError('bodyMarkdown', $error);

            return null;
        }
        if ($error = app(WirelistParser::class)->errors($this->bodyMarkdown)[0] ?? null) {
            $this->addError('bodyMarkdown', $error);

            return null;
        }

        // Never trust the client-held repo_path / base_sha / original: re-derive them on the
        // server from the (path-validated) type/category/slug, so a tampered Livewire payload
        // cannot redirect the write to an arbitrary file. rawMarkdown() runs the safe() guard
        // and confirms the article still exists.
        $svc = app(ArticleService::class);
        $raw = $svc->rawMarkdown($this->type, $this->category, $this->slug, $this->locale);
        abort_if($raw === null, 404);

        $composed = $this->composedDocument();

        if ($this->normalize($composed) === $this->normalize($raw['content'])) {
            $this->addError('bodyMarkdown', __('No changes to submit. Edit the article first.'));

            return null;
        }

        // Authoritative check (never trust the client-held $canManage): staff self-approve.
        $manage = Gate::allows('manage-articles');

        $rev = ArticleRevision::create([
            'user_id' => Auth::id(),
            'type' => $this->type,
            'category' => $this->category,
            'slug' => $this->slug,
            'locale' => $this->locale,
            'title' => $svc->preview($composed, $this->type, $this->category, $this->slug)['title'],
            'repo_path' => $raw['repo_path'],
            'base_sha' => $raw['sha'],
            'original_body' => $raw['content'],
            'proposed_body' => $composed,
            'assets' => null,
            'summary' => $this->note !== '' ? $this->note : null,
            'status' => $manage ? 'approved' : 'pending',
            'reviewer_id' => $manage ? Auth::id() : null,
            'reviewed_at' => $manage ? now() : null,
        ]);

        // Translations reuse the English bundle's co-located assets (locale-agnostic asset URLs),
        // so image uploads only attach to a default-locale edit.
        if (! $this->isTranslation) {
            $names = $this->stageReferencedUploads($rev, $composed);
            $rev->forceFill(['assets' => $names ?: null])->save();
        }

        if ($manage) {
            CommitArticle::dispatch($rev->id);
            session()->flash('status', 'Published. Your change (#'.$rev->id.') was applied and committed; it can be reverted from history.');
        } else {
            session()->flash('status', 'Thanks. Your edit (#'.$rev->id.') was submitted for review and will go live once approved.');
        }

        return $this->redirect($rev->url(), navigate: true);
    }

    /** Normalize trailing whitespace + final newline so a no-op save is detected reliably. */
    private function normalize(string $s): string
    {
        return rtrim(str_replace("\r\n", "\n", $s))."\n";
    }

    public function render(): View
    {
        return view('livewire.article-editor');
    }
}
