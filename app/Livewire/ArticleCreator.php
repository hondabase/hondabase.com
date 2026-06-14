<?php

namespace App\Livewire;

use App\Jobs\CommitArticle;
use App\Models\ArticleRevision;
use App\Services\ArticleService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Create a brand-new article bundle. A signed-in (guild-gated) user picks the location
 * (type / category / slug), writes the markdown with the same live preview as a published
 * page, and may upload co-located images. Submitting records an App\Models\ArticleRevision
 * exactly like an edit: a member's goes to the review queue, a staff member's auto-applies.
 * On commit the new .md and its uploaded images land together in one path-limited commit.
 *
 * Mobile-first: the editor and preview are tabs on a phone, side-by-side on a wide screen.
 */
class ArticleCreator extends Component
{
    use WithFileUploads;

    public string $type = 'cars';
    public string $category = '';
    public string $slug = '';

    /** The whole markdown file (frontmatter optional + body, first H1 = title). */
    public string $body = "# New article title\n\nWrite the article here. Use **Markdown**.\n";
    public string $summary = '';

    /** Newly uploaded images (Livewire temp files) destined for the article bundle. */
    public array $images = [];

    /** UI only; the authoritative auto-apply decision is re-checked server-side in submit(). */
    public bool $canManage = false;

    public function mount(): void
    {
        abort_unless(Auth::check(), 403);
        $this->canManage = Gate::allows('manage-articles');
    }

    /** Keep the slug in sync with the title until the user types their own slug. */
    public function updatedBody(): void
    {
        if ($this->slug === '') {
            $title = $this->previewData()['title'] ?? '';
            // only autofill from a real title, not the placeholder
            if ($title !== '' && $title !== 'New article title') {
                $this->slug = Str::slug($title);
            }
        }
    }

    public function updatedImages(): void
    {
        $this->validate(['images.*' => ['image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096']]);
    }

    public function removeImage(int $i): void
    {
        unset($this->images[$i]);
        $this->images = array_values($this->images);
    }

    /** Type/category folders that already exist, to offer as a datalist. */
    #[Computed]
    public function categoryOptions(): array
    {
        return array_map(fn ($c) => $c['slug'], app(ArticleService::class)->categories($this->type));
    }

    /** Deduped, sanitized bundle filenames for the current uploads (and copy snippets). */
    #[Computed]
    public function assetNames(): array
    {
        $names = [];
        $seen  = [];
        foreach ($this->images as $img) {
            $name = $this->assetName($img->getClientOriginalName());
            $base = pathinfo($name, PATHINFO_FILENAME);
            $ext  = pathinfo($name, PATHINFO_EXTENSION);
            $try  = $name;
            $n    = 1;
            while (isset($seen[$try])) {
                $try = $base . '-' . $n++ . '.' . $ext;
            }
            $seen[$try] = true;
            $names[] = $try;
        }
        return $names;
    }

    #[Computed]
    public function preview(): array
    {
        return $this->previewData();
    }

    private function previewData(): array
    {
        return app(ArticleService::class)->preview($this->body, $this->type, $this->cleanSlug($this->category), $this->cleanSlug($this->slug));
    }

    public function submit()
    {
        $this->category = $this->cleanSlug($this->category);
        $this->slug     = $this->cleanSlug($this->slug);

        $this->validate([
            'type'     => ['required', 'in:' . implode(',', app(ArticleService::class)->types())],
            'category' => ['required', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'slug'     => ['required', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'body'     => ['required', 'string', 'min:20'],
            'summary'  => ['nullable', 'string', 'max:500'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096'],
        ], [], ['category' => 'category', 'slug' => 'slug', 'body' => 'article']);

        $svc = app(ArticleService::class);

        if ($svc->exists($this->type, $this->category, $this->slug)) {
            $this->addError('slug', 'An article already exists at that location. Edit it instead.');
            return null;
        }

        $repoPath = "{$this->type}/{$this->category}/{$this->slug}/{$this->slug}.md";
        $names    = $this->assetNames();
        $manage   = Gate::allows('manage-articles');

        $rev = ArticleRevision::create([
            'user_id'       => Auth::id(),
            'type'          => $this->type,
            'category'      => $this->category,
            'slug'          => $this->slug,
            'title'         => $svc->preview($this->body, $this->type, $this->category, $this->slug)['title'],
            'repo_path'     => $repoPath,
            'base_sha'      => $svc->currentSha(),
            'original_body' => '', // new file: there is nothing on disk to base on yet
            'proposed_body' => $this->body,
            'assets'        => $names ?: null,
            'summary'       => $this->summary !== '' ? $this->summary : null,
            'status'        => $manage ? 'approved' : 'pending',
            'reviewer_id'   => $manage ? Auth::id() : null,
            'reviewed_at'   => $manage ? now() : null,
        ]);

        $this->stageUploads($rev, $names);

        if ($manage) {
            CommitArticle::dispatch($rev->id);
            session()->flash('status', 'Created. Your new article (#' . $rev->id . ') was published and committed; it can be reverted from history.');
            return $this->redirect($rev->url(), navigate: true);
        }

        session()->flash('status', 'Thanks. Your new article (#' . $rev->id . ') was submitted for review and will go live once approved.');
        return $this->redirect('/', navigate: true);
    }

    /** Persist uploaded images into the revision's staging dir under their committed names. */
    private function stageUploads(ArticleRevision $rev, array $names): void
    {
        if (empty($this->images)) {
            return;
        }
        $dir = $rev->assetStagingDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        foreach ($this->images as $i => $img) {
            $name = $names[$i] ?? null;
            if ($name === null) {
                continue;
            }
            copy($img->getRealPath(), $dir . '/' . $name);
        }
    }

    /** Sanitize a client filename to a safe co-located asset name (slug + lowercased ext). */
    private function assetName(string $original): string
    {
        $ext  = strtolower(pathinfo($original, PATHINFO_EXTENSION)) ?: 'img';
        $base = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'image';
        return $base . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
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
