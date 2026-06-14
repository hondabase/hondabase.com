<?php

namespace App\Console\Commands;

use App\Models\ArticleAuthor;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class AuditArticleAuthors extends Command
{
    protected $signature = 'hondabase:audit-attribution';
    protected $description = 'Audit article source metadata and database author links';

    public function handle(): int
    {
        $root = rtrim((string) config('hondabase.content_path'), '/');
        $paths = [];
        $errors = [];

        foreach (glob("{$root}/*/*/*/*.md") ?: [] as $file) {
            $repoPath = ltrim(substr($file, strlen($root)), '/');
            $paths[$repoPath] = true;
        }

        foreach (ArticleAuthor::with('user')->get() as $credit) {
            if (!isset($paths[$credit->repo_path])) {
                $errors[] = "orphaned author link: {$credit->repo_path}";
            }
            if ($credit->user === null) {
                $errors[] = "author link #{$credit->id} has no user";
            } elseif (!$credit->user->is_legacy_author && !$credit->user->discord_id) {
                $errors[] = "author link #{$credit->id} is not tied to a human identity";
            } elseif ($credit->user->is_legacy_author && in_array(mb_strtolower((string) $credit->user->legacy_handle), ['guest', 'twikiguest'], true)) {
                $errors[] = "author link #{$credit->id} uses a non-human wiki identity";
            }
            if (!$credit->is_original && !$credit->is_contributor) {
                $errors[] = "author link #{$credit->id} has no credit role";
            }
        }

        foreach (glob("{$root}/cars/electronics/*/*.md") ?: [] as $file) {
            $repoPath = ltrim(substr($file, strlen($root)), '/');
            $isPgmfiPort = !in_array($repoPath, (array) config('hondabase.pgmfi_non_ports', []), true);
            $raw = (string) file_get_contents($file);
            $frontmatter = [];
            if (preg_match('/^---\s*?\r?\n(.*?)\r?\n---/s', $raw, $match)) {
                $parsed = Yaml::parse($match[1]);
                $frontmatter = is_array($parsed) ? $parsed : [];
            }
            $pgmfiSources = collect($frontmatter['sources'] ?? [])
                ->filter(fn ($source) => is_array($source) && ($source['name'] ?? null) === 'pgmfi.org wiki');
            if ($isPgmfiPort && $pgmfiSources->isEmpty()) {
                $errors[] = "{$repoPath}: missing source metadata";
            }
            if ($isPgmfiPort && !ArticleAuthor::where('repo_path', $repoPath)->where('is_original', true)->exists()) {
                $errors[] = "{$repoPath}: missing original authors";
            }
            if (!$isPgmfiPort && !ArticleAuthor::where('repo_path', $repoPath)->exists()) {
                $errors[] = "{$repoPath}: missing article authors";
            }
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $this->info('Article attribution audit passed.');
        return self::SUCCESS;
    }
}
