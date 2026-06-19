<?php

namespace App\Jobs;

use App\Support\Locales;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Removes an article bundle from the local content clone and pushes the deletion to origin.
 *
 * When $locale is null, deletes every locale's bundle directory for this slug (full removal).
 * When $locale is a non-default locale, deletes only that translation directory.
 * The DB cleanup is done synchronously in the controller before this job is dispatched.
 */
class DeleteArticle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $type,
        public string $category,
        public string $slug,
        public string $deletedByName,
        public ?string $locale = null,
    ) {}

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(): void
    {
        $root = rtrim((string) config('hondabase.content_path'), '/');

        $paths = $this->locale
            ? $this->removeLocaleBundle($root, $this->locale)
            : $this->removeAllBundles($root);

        if (empty($paths)) {
            Log::info('DeleteArticle: nothing on disk to remove', [
                'type' => $this->type,
                'category' => $this->category,
                'slug' => $this->slug,
                'locale' => $this->locale,
            ]);

            return;
        }

        $this->commit($root, $paths);
        $this->push($root);
    }

    /** Remove a single locale's translation directory. Returns the removed repo-relative paths. */
    private function removeLocaleBundle(string $root, string $locale): array
    {
        $localeRoot = Locales::isDefault($locale) ? $root : "{$root}/{$locale}";
        $dir = "{$localeRoot}/{$this->type}/{$this->category}/{$this->slug}";

        if (! is_dir($dir)) {
            return [];
        }

        $rel = Locales::isDefault($locale)
            ? "{$this->type}/{$this->category}/{$this->slug}"
            : "{$locale}/{$this->type}/{$this->category}/{$this->slug}";

        $rm = Process::path($root)->run(['git', 'rm', '-r', '--', $rel]);

        if (! $rm->successful()) {
            throw new \RuntimeException("git rm failed for {$rel}: ".$rm->errorOutput().$rm->output());
        }

        return [$rel];
    }

    /** Remove every locale's bundle directory. Returns the removed repo-relative paths. */
    private function removeAllBundles(string $root): array
    {
        $paths = [];

        foreach (Locales::codes() as $locale) {
            $removed = $this->removeLocaleBundle($root, $locale);
            $paths = array_merge($paths, $removed);
        }

        return $paths;
    }

    private function commit(string $root, array $paths): void
    {
        $bot = config('hondabase.git');

        $subject = $this->locale
            ? "Delete {$this->locale} translation of {$this->type}/{$this->category}/{$this->slug} (by {$this->deletedByName})"
            : "Delete {$this->type}/{$this->category}/{$this->slug} (by {$this->deletedByName})";

        $msgFile = tempnam(sys_get_temp_dir(), 'hb-del-');
        file_put_contents($msgFile, $subject."\n");

        try {
            $commit = Process::path($root)->run([
                'git',
                '-c', 'user.name='.$bot['bot_name'],
                '-c', 'user.email='.$bot['bot_email'],
                'commit', '-F', $msgFile, '--',
                ...$paths,
            ]);

            if (! $commit->successful()) {
                throw new \RuntimeException('git commit failed: '.$commit->errorOutput().$commit->output());
            }
        } finally {
            @unlink($msgFile);
        }

        Log::info('DeleteArticle committed', [
            'type' => $this->type,
            'category' => $this->category,
            'slug' => $this->slug,
            'locale' => $this->locale,
        ]);
    }

    private function push(string $root): void
    {
        if (! config('hondabase.git.push')) {
            return;
        }

        $branch = config('hondabase.git.branch');
        $result = Process::path($root)->run(['git', 'push', 'origin', $branch]);

        if (! $result->successful()) {
            throw new \RuntimeException('git push failed: '.$result->errorOutput());
        }
    }
}
