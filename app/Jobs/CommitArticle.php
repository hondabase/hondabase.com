<?php

namespace App\Jobs;

use App\Models\ArticleRevision;
use App\Models\Article;
use App\Services\ArticleAuthorService;
use App\Services\ArticleIndexer;
use App\Services\FollowerNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Commits an approved article edit into the local content/ clone and pushes it to origin.
 *
 * Attribution is the point: the commit is *authored by the bot* (a single machine identity),
 * while the human who wrote the edit is credited with a `Co-Authored-By:` trailer and the
 * approver with `Reviewed-By:`. Linked-GitHub editors get their real no-reply address so the
 * commit lands on their GitHub profile; unlinked editors get a stable synthetic address. Git
 * history remains a portable record, while the database drives the public author list.
 *
 * The job is idempotent + retryable: once it records `commit_sha` it never re-commits, so a
 * retry only re-attempts the push. Push is best-effort (deploy key); if it is disabled or the
 * key is down the commit still lands locally and the revision is counted as "unpushed".
 */
class CommitArticle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $revisionId)
    {
    }

    /** Re-push backoff (seconds) for transient remote failures. */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(ArticleIndexer $indexer, ArticleAuthorService $authors, FollowerNotifier $notifier): void
    {
        $rev = ArticleRevision::find($this->revisionId);
        if ($rev === null || $rev->status !== 'approved') {
            return; // rejected or vanished between dispatch and run; nothing to do.
        }

        $root = rtrim((string) config('hondabase.content_path'), '/');

        // First run: write + commit. On a retry (commit_sha set) we skip straight to push.
        if ($rev->commit_sha === null) {
            // A conflict (the on-disk base moved underneath this edit) parks the revision for
            // re-review and commits nothing; stop before crediting/pushing.
            if (!$this->writeAndCommit($rev, $root)) {
                return;
            }
            // Make the change visible on the live site immediately, push or not.
            $indexer->indexOne($rev->type, $rev->category, $rev->slug);

            // Notify followers of a matching facet. A revert isn't an authored "publish/update",
            // so it doesn't notify. Failures here must never break the commit/push, so guard it.
            if ($rev->reverts_revision_id === null) {
                $article = Article::where('type', $rev->type)->where('category', $rev->category)
                    ->where('slug', $rev->slug)->first();
                if ($article) {
                    try {
                        $notifier->notify($article, $rev->original_body === '', optional($rev->author)->id);
                    } catch (\Throwable $e) {
                        Log::warning('CommitArticle notify failed', ['revision' => $rev->id, 'error' => $e->getMessage()]);
                    }
                }
            }
        }

        if ($rev->reverts_revision_id === null && $rev->author !== null) {
            $authors->creditContributor($rev->repo_path, $rev->author);
        }
        $this->push($rev, $root);
    }

    /**
     * Write the proposed body and commit it. Returns true if applied (or already applied/no-op),
     * false if the edit conflicts with newer on-disk content and was parked as 'conflicted'.
     */
    private function writeAndCommit(ArticleRevision $rev, string $root): bool
    {
        // Defense in depth: the path is already server-derived from safe() segments, but never
        // write outside the content root or anywhere but a .md file under it.
        $abs  = $root . '/' . $rev->repo_path;
        $real = realpath($root);
        $target = realpath(dirname($abs));
        if ($real === false || str_contains($rev->repo_path, '..') || !str_ends_with($abs, '.md')
            || ($target !== false && !str_starts_with($target, $real))) {
            throw new \RuntimeException("CommitArticle: refusing unsafe repo_path '{$rev->repo_path}'");
        }

        // Conflict detection: the edit was authored against `original_body`. If the file on disk
        // no longer matches that base, main moved underneath the edit (another commit, a parallel
        // approval, a manual change). Applying proposed_body would silently clobber that newer
        // content, so park the revision as 'conflicted' for a human to re-review instead.
        $current = is_file($abs) ? (string) file_get_contents($abs) : '';
        if ($this->normalize($current) !== $this->normalize($rev->original_body)) {
            $rev->forceFill(['status' => 'conflicted'])->save();
            Log::warning('CommitArticle conflict: on-disk base changed, edit parked', [
                'revision'  => $rev->id,
                'repo_path' => $rev->repo_path,
            ]);
            return false;
        }

        $dir = dirname($abs);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($abs, $this->normalize($rev->proposed_body));

        // Co-located image uploads staged for this revision are written into the same bundle and
        // committed in the same path-limited commit, so a new article lands with its images.
        $paths = array_merge([$rev->repo_path], $this->writeAssets($rev, $dir));

        // Nothing actually changed on disk (already applied): record HEAD and move on rather
        // than failing on git's "nothing to commit".
        $status = Process::path($root)->run(array_merge(['git', 'status', '--porcelain', '--'], $paths));
        if (trim($status->output()) === '') {
            $rev->forceFill(['commit_sha' => $this->head($root)])->save();
            $this->cleanupAssets($rev);
            return true;
        }

        $author   = optional($rev->author)->gitIdentity();
        $reviewer = optional($rev->reviewer)->gitIdentity();
        $by       = optional($rev->author)->displayName() ?? 'a contributor';

        $bot = config('hondabase.git');

        // Build the message: subject, the editor's rationale, then attribution trailers.
        $subject = $rev->reverts_revision_id
            ? "Revert to before edit #{$rev->reverts_revision_id}: {$rev->repo_path} (by {$by})"
            : "Update {$rev->repo_path} (by {$by})";

        $lines = [$subject, ''];
        if ($rev->summary) {
            $lines[] = $rev->summary;
            $lines[] = '';
        }
        if ($author) {
            $lines[] = "Co-Authored-By: {$author['name']} <{$author['email']}>";
        }
        if ($reviewer) {
            $lines[] = "Reviewed-By: {$reviewer['name']} <{$reviewer['email']}>";
        }
        if ($rev->reverts_revision_id) {
            $orig = ArticleRevision::find($rev->reverts_revision_id);
            $lines[] = "Reverts: revision #{$rev->reverts_revision_id}"
                . ($orig && $orig->commit_sha ? ' (commit ' . substr($orig->commit_sha, 0, 12) . ')' : '');
        }
        $message = implode("\n", $lines) . "\n";

        $msgFile = tempnam(sys_get_temp_dir(), 'hb-commit-');
        file_put_contents($msgFile, $message);

        try {
            // Stage exactly these paths so newly created files (a new article + its uploaded
            // images) are known to git; `git commit <pathspec>` alone fails on untracked files.
            // Limiting `add` to $paths keeps the path-limited guarantee: nothing else is staged.
            $add = Process::path($root)->run(array_merge(['git', 'add', '--'], $paths));
            if (!$add->successful()) {
                throw new \RuntimeException('git add failed: ' . $add->errorOutput() . $add->output());
            }

            // Path-limited commit: commits ONLY this article, ignoring any other dirty files
            // in the content clone. Bot is author + committer via the inline -c overrides.
            $commit = Process::path($root)->run(array_merge([
                'git',
                '-c', 'user.name=' . $bot['bot_name'],
                '-c', 'user.email=' . $bot['bot_email'],
                'commit', '-F', $msgFile, '--',
            ], $paths));

            if (!$commit->successful()) {
                throw new \RuntimeException('git commit failed: ' . $commit->errorOutput() . $commit->output());
            }
        } finally {
            @unlink($msgFile);
        }

        $rev->forceFill(['commit_sha' => $this->head($root)])->save();
        $this->cleanupAssets($rev);
        Log::info('CommitArticle committed', ['revision' => $rev->id, 'sha' => $rev->commit_sha]);
        return true;
    }

    /**
     * Copy this revision's staged image uploads into the article bundle, returning their
     * repo-relative paths for the path-limited commit. Each asset is a bare filename inside the
     * bundle; anything unsafe is skipped. A path is still returned if its staged source is gone
     * (a prior run already copied + committed it) so the commit/status pathspec stays complete.
     */
    private function writeAssets(ArticleRevision $rev, string $bundleDir): array
    {
        $names = $rev->assets ?? [];
        if (empty($names)) {
            return [];
        }
        $staging = $rev->assetStagingDir();
        $relBase = trim(dirname($rev->repo_path), '/'); // type/category/slug
        $paths   = [];
        foreach ($names as $name) {
            $name = basename((string) $name);
            if ($name === '' || str_contains($name, '..')
                || !preg_match('/^[A-Za-z0-9._-]+\.[A-Za-z0-9]+$/', $name)) {
                continue;
            }
            $src = $staging . '/' . $name;
            if (is_file($src)) {
                copy($src, $bundleDir . '/' . $name);
            }
            $paths[] = $relBase . '/' . $name;
        }
        return $paths;
    }

    /** Remove the revision's staged uploads once they are safely committed. */
    private function cleanupAssets(ArticleRevision $rev): void
    {
        $dir = $rev->assetStagingDir();
        if (!is_dir($dir)) {
            return;
        }
        foreach ((array) glob($dir . '/*') as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    private function push(ArticleRevision $rev, string $root): void
    {
        if ($rev->pushed) {
            return;
        }

        // Push is opt-in: it stays off until a deploy key is configured on the box. Until then
        // the commit is local-only and surfaces in the admin "unpushed" count by design.
        if (!config('hondabase.git.push')) {
            return;
        }

        $branch = config('hondabase.git.branch');
        $result = Process::path($root)->run(['git', 'push', 'origin', $branch]);

        if (!$result->successful()) {
            // Throw so the queue retries (with backoff). The commit already persisted, so the
            // retry path skips straight back here and only re-attempts the push.
            throw new \RuntimeException('git push failed: ' . $result->errorOutput());
        }

        $rev->forceFill(['pushed' => true])->save();
        Log::info('CommitArticle pushed', ['revision' => $rev->id]);
    }

    private function head(string $root): ?string
    {
        $r = Process::path($root)->run(['git', 'rev-parse', 'HEAD']);
        return $r->successful() ? trim($r->output()) : null;
    }

    /** Match the editor's normalization so round-tripped files end with a single newline. */
    private function normalize(string $s): string
    {
        return rtrim(str_replace("\r\n", "\n", $s)) . "\n";
    }
}
