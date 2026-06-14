<?php

namespace App\Livewire\Concerns;

use App\Models\ArticleRevision;
use App\Services\ArticleService;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

/** Shared co-located image upload and picker behavior for article creation and editing. */
trait ManagesArticleImages
{
    use WithFileUploads;

    /** Newly uploaded images waiting to be referenced and staged with a revision. */
    public array $images = [];

    public function updatedImages(): void
    {
        $this->validate(['images.*' => ['image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096']]);
    }

    public function removeImage(int $i): void
    {
        unset($this->images[$i]);
        $this->images = array_values($this->images);
    }

    /** Collision-safe final bundle filenames, reserving every existing article image name. */
    #[Computed]
    public function assetNames(): array
    {
        $names = [];
        $seen = array_fill_keys(array_column($this->existingImageAssets(), 'name'), true);
        foreach ($this->images as $img) {
            $name = $this->assetName($img->getClientOriginalName());
            $base = pathinfo($name, PATHINFO_FILENAME);
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $try = $name;
            $n = 1;
            while (isset($seen[$try])) {
                $try = $base.'-'.$n++.'.'.$ext;
            }
            $seen[$try] = true;
            $names[] = $try;
        }

        return $names;
    }

    /** Asset picker payload consumed by the visual TipTap carousel node. */
    public function editorAssets(): array
    {
        $assets = $this->existingImageAssets();
        foreach ($this->images as $index => $image) {
            $name = $this->assetNames()[$index] ?? null;
            if ($name !== null) {
                $assets[] = ['name' => $name, 'url' => $image->temporaryUrl(), 'pending' => true];
            }
        }

        return $assets;
    }

    /** Temporary signed URLs keyed by final bundle filename for the server-rendered preview. */
    protected function uploadedPreviewUrls(): array
    {
        $urls = [];
        foreach ($this->images as $index => $image) {
            $name = $this->assetNames()[$index] ?? null;
            if ($name !== null) {
                $urls[$name] = $image->temporaryUrl();
            }
        }

        return $urls;
    }

    /**
     * Stage only uploaded images referenced by the submitted Markdown. Unused picker uploads
     * stay temporary and are cleaned by Livewire's normal upload lifecycle.
     */
    protected function stageReferencedUploads(ArticleRevision $revision, string $markdown): array
    {
        $referenced = $this->referencedUploadNames($markdown);
        if ($referenced === []) {
            return [];
        }

        $dir = $revision->assetStagingDir();
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $names = $this->assetNames();
        foreach ($this->images as $index => $image) {
            $name = $names[$index] ?? null;
            if ($name !== null && in_array($name, $referenced, true)) {
                copy($image->getRealPath(), $dir.'/'.$name);
            }
        }

        return $referenced;
    }

    protected function referencedUploadNames(string $markdown): array
    {
        preg_match_all('/!\[[^\]\r\n]*\]\(([^)\r\n]+)\)/', $markdown, $matches);
        $uploaded = array_fill_keys($this->assetNames(), true);
        $out = [];
        foreach ($matches[1] ?? [] as $source) {
            $name = basename((string) preg_replace('/[?#].*$/', '', trim($source)));
            if (isset($uploaded[$name]) && ! in_array($name, $out, true)) {
                $out[] = $name;
            }
        }

        return $out;
    }

    private function existingImageAssets(): array
    {
        if (! isset($this->type, $this->category, $this->slug)) {
            return [];
        }

        return app(ArticleService::class)->imageAssets((string) $this->type, (string) $this->category, (string) $this->slug);
    }

    private function assetName(string $original): string
    {
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION)) ?: 'img';
        $base = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'image';

        return $base.'.'.preg_replace('/[^a-z0-9]/', '', $ext);
    }
}
