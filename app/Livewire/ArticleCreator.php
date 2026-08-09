<?php

namespace App\Livewire;

use App\Jobs\CommitArticle;
use App\Livewire\Concerns\EditsFrontmatter;
use App\Livewire\Concerns\ManagesArticleImages;
use App\Markdown\CarouselParser;
use App\Markdown\WirelistParser;
use App\Models\ArticleDraft;
use App\Models\ArticleRevision;
use App\Services\ArticleService;
use App\Services\RevisionNotifier;
use App\Support\ArticleDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
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

    /** The private working copy currently being edited, or null for a new unsaved article. */
    public ?int $draftId = null;

    /** UI only; the authoritative auto-apply decision is re-checked server-side in submit(). */
    public bool $canManage = false;

    public function mount(?int $draftId = null): void
    {
        abort_unless(Auth::check(), 403);
        $this->canManage = Gate::allows('manage-articles');

        if ($draftId === null) {
            return;
        }

        $draft = ArticleDraft::whereKey($draftId)->where('user_id', Auth::id())->first();
        abort_if($draft === null, 404);
        $document = ArticleDocument::parse($draft->document);

        $this->draftId = $draft->id;
        $this->type = $draft->type;
        $this->category = $draft->category ?? '';
        $this->slug = $draft->slug ?? '';
        $this->bodyMarkdown = $document['body'];
        $this->note = $draft->note ?? '';
        $this->hydrateFrontmatter($document['fm']);
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

    /** The signed-in user's resumable drafts, newest first. */
    #[Computed]
    public function drafts(): Collection
    {
        return ArticleDraft::where('user_id', Auth::id())->latest('updated_at')->get();
    }

    /** Images already persisted with the current draft and available to the editor picker. */
    #[Computed]
    public function savedDraftAssets(): array
    {
        return $this->additionalImageAssets();
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

    /** Save an incomplete private working copy without creating a reviewable revision. */
    public function saveDraft()
    {
        $this->validate([
            'type' => ['required', 'in:'.implode(',', app(ArticleService::class)->types())],
            'category' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'bodyMarkdown' => ['nullable', 'string', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:500'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096'],
        ], [], ['bodyMarkdown' => 'article']);

        $document = $this->composedDocument();
        $title = app(ArticleService::class)->preview(
            $document,
            $this->type,
            $this->category,
            $this->slug,
        )['title'];

        $draft = $this->ownedDraft();
        if ($this->draftId !== null && $draft === null) {
            abort(404);
        }

        $draft ??= new ArticleDraft(['user_id' => Auth::id()]);
        $draft->forceFill([
            'title' => Str::limit((string) $title, 255, ''),
            'type' => $this->type,
            'category' => $this->category !== '' ? $this->category : null,
            'slug' => $this->slug !== '' ? $this->slug : null,
            'document' => $document,
            'note' => $this->note !== '' ? $this->note : null,
        ])->save();

        $this->persistDraftUploads($draft);
        $this->draftId = $draft->id;

        session()->flash('status', __('Draft saved. Only you can see it.'));

        return $this->redirectRoute('article.new.draft', ['draft' => $draft->id], navigate: true);
    }

    public function deleteDraft(int $draftId)
    {
        $draft = ArticleDraft::whereKey($draftId)->where('user_id', Auth::id())->first();
        abort_if($draft === null, 404);
        $isCurrent = $draft->id === $this->draftId;
        $draft->delete();

        session()->flash('status', __('Draft deleted.'));

        if ($isCurrent) {
            return $this->redirectRoute('article.new', navigate: true);
        }

        return null;
    }

    public function removeDraftAsset(int $index): void
    {
        $draft = $this->ownedDraft();
        abort_if($draft === null, 404);

        $assets = array_values($draft->assets ?? []);
        $name = $assets[$index] ?? null;
        abort_if($name === null, 404);

        if ($path = $draft->assetPath($name)) {
            @unlink($path);
        }

        unset($assets[$index]);
        $draft->forceFill(['assets' => array_values($assets) ?: null])->save();
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

        $draft = $this->ownedDraft();
        $names = [
            ...$this->stageReferencedUploads($rev, $composed),
            ...$this->stageReferencedDraftAssets($rev, $composed, $draft),
        ];
        $names = array_values(array_unique($names));
        $rev->forceFill(['assets' => $names ?: null])->save();
        $draft?->delete();

        if ($manage) {
            CommitArticle::dispatch($rev->id);
            session()->flash('status', __('Created. Your new article (#:id) was published and committed; it can be reverted from history.', ['id' => $rev->id]));

            return $this->redirect($rev->url(), navigate: true);
        }

        app(RevisionNotifier::class)->notifyStaffOfPendingEdit($rev);
        session()->flash('status', __('Thanks. Your new article (#:id) was submitted for review and will go live once approved.', ['id' => $rev->id]));

        return $this->redirect('/', navigate: true);
    }

    private function cleanSlug(string $s): string
    {
        return Str::slug($s);
    }

    protected function additionalImageAssets(): array
    {
        $draft = $this->ownedDraft();
        if ($draft === null) {
            return [];
        }

        $assets = [];
        foreach ($draft->assets ?? [] as $name) {
            if ($draft->assetPath($name) !== null) {
                $assets[] = [
                    'name' => $name,
                    'url' => route('article.draft.asset', ['draft' => $draft->id, 'file' => $name]),
                    'pending' => false,
                    'draft' => true,
                ];
            }
        }

        return $assets;
    }

    private function ownedDraft(): ?ArticleDraft
    {
        if ($this->draftId === null) {
            return null;
        }

        return ArticleDraft::whereKey($this->draftId)->where('user_id', Auth::id())->first();
    }

    private function persistDraftUploads(ArticleDraft $draft): void
    {
        if ($this->images === []) {
            return;
        }

        $dir = $draft->assetDirectory();
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $newNames = $this->assetNames();
        foreach ($this->images as $index => $image) {
            if ($name = $newNames[$index] ?? null) {
                copy($image->getRealPath(), $dir.'/'.$name);
            }
        }

        $assets = array_values(array_unique([...($draft->assets ?? []), ...$newNames]));
        $draft->forceFill(['assets' => $assets ?: null])->save();
    }

    private function stageReferencedDraftAssets(ArticleRevision $revision, string $markdown, ?ArticleDraft $draft): array
    {
        if ($draft === null || ($draft->assets ?? []) === []) {
            return [];
        }

        preg_match_all('/!\[[^\]\r\n]*\]\(([^)\r\n]+)\)/', $markdown, $matches);
        $referenced = array_values(array_unique(array_map(
            fn ($source) => basename((string) preg_replace('/[?#].*$/', '', trim($source))),
            $matches[1] ?? [],
        )));

        $staged = [];
        foreach ($draft->assets as $name) {
            $path = $draft->assetPath($name);
            if ($path === null || ! in_array($name, $referenced, true)) {
                continue;
            }

            $dir = $revision->assetStagingDir();
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            copy($path, $dir.'/'.$name);
            $staged[] = $name;
        }

        return $staged;
    }

    public function render(): View
    {
        return view('livewire.article-creator');
    }
}
