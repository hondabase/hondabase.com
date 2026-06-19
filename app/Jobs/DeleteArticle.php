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
 * Deletes the canonical English bundle plus all per-locale translation directories for this slug.
 * The DB cleanup (Article rows, revisions, etc.) is done synchronously in the controller before
 * this job is dispatched, so the article is already gone from the live site by the time git runs.
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
    ) {}

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(): void
    {
        $root = rtrim((string) config('hondabase.content_path'), '/');

        $paths = $this->removeBundles($root);

        if (empty($paths)) {
            Log::info('DeleteArticle: nothing on disk to remove', [
                'type' => $this->type,
                'category' => $this->category,
                'slug' => $this->slug,
            ]);

            return;
        }

        $this->commit($root, $paths);
        $this->push($root);
    }

    /** Remove every locale's bundle directory and return the repo-relative paths that were deleted. */
    private function removeBundles(string $root): array
    {
        $paths = [];

        foreach (Locales::codes() as $locale) {
            $localeRoot = Locales::isDefault($locale)
                ? $root
                : "{$root}/{$locale}";

            $dir = "{$localeRoot}/{$this->type}/{$this->category}/{$this->slug}";

            if (! is_dir($dir)) {
                continue;
            }

            $rel = Locales::isDefault($locale)
                ? "{$this->type}/{$this->category}/{$this->slug}"
                : "{$locale}/{$this->type}/{$this->category}/{$this->slug}";

            $rm = Process::path($root)->run(['git', 'rm', '-r', '--', $rel]);

            if (! $rm->successful()) {
                throw new \RuntimeException("git rm failed for {$rel}: ".$rm->errorOutput().$rm->output());
            }

            $paths[] = $rel;
        }

        return $paths;
    }

    private function commit(string $root, array $paths): void
    {
        $bot = config('hondabase.git');
        $subject = "Delete {$this->type}/{$this->category}/{$this->slug} (by {$this->deletedByName})";
        $message = $subject."\n";

        $msgFile = tempnam(sys_get_temp_dir(), 'hb-del-');
        file_put_contents($msgFile, $message);

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
