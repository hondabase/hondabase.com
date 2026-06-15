<?php

namespace App\Models;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A suggested edit awaiting review. See the migration for the lifecycle: pending -> approved
 * (then committed to git by App\Jobs\CommitArticle) or rejected.
 */
class ArticleRevision extends Model
{
    protected $guarded = [];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'pushed' => 'bool',
        'assets' => 'array',
    ];

    /** Absolute path where this revision's uploaded image assets are staged until commit. */
    public function assetStagingDir(): string
    {
        return storage_path('app/pending-assets/'.$this->id);
    }

    /** Resolve one declared staged asset without allowing arbitrary storage reads. */
    public function stagedAssetPath(string $file): ?string
    {
        $file = basename($file);
        if (! in_array($file, $this->assets ?? [], true)) {
            return null;
        }
        $path = $this->assetStagingDir().'/'.$file;

        return is_file($path) ? $path : null;
    }

    public function cleanupStagedAssets(): void
    {
        $dir = $this->assetStagingDir();
        if (! is_dir($dir)) {
            return;
        }
        foreach ((array) glob($dir.'/*') as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** The earlier revision this one reverts (null unless this revision is a revert). */
    public function reverts(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverts_revision_id');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    /** Parked at apply time because the on-disk base moved underneath the edit; needs re-review. */
    public function scopeConflicted(Builder $q): Builder
    {
        return $q->where('status', 'conflicted');
    }

    public function isConflicted(): bool
    {
        return $this->status === 'conflicted';
    }

    /** Applied = approved and committed to git (has a commit sha); these are revertible. */
    public function scopeApplied(Builder $q): Builder
    {
        return $q->whereNotNull('commit_sha');
    }

    public function isApplied(): bool
    {
        return $this->commit_sha !== null;
    }

    /** Committed locally but not yet pushed to origin (the deploy key is down or absent). */
    public function scopeUnpushed(Builder $q): Builder
    {
        return $q->whereNotNull('commit_sha')->where('pushed', false);
    }

    public function url(): string
    {
        $prefix = ($this->locale && ! Locales::isDefault($this->locale)) ? "/{$this->locale}" : '';

        return "{$prefix}/{$this->type}/{$this->category}/{$this->slug}";
    }

    /**
     * A simple line-level diff of original -> proposed for the review screen. Returns rows
     * of ['kind' => 'same'|'add'|'del', 'text' => string]. Uses the longest-common-subsequence
     * shape PHP's array_diff gives us via a lightweight Myers-free approach (good enough for
     * article-sized files; this is a review aid, not a patch generator).
     */
    public function diff(): array
    {
        $a = preg_split("/\r\n|\n|\r/", (string) $this->original_body);
        $b = preg_split("/\r\n|\n|\r/", (string) $this->proposed_body);

        // Classic LCS table.
        $n = count($a);
        $m = count($b);
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $rows = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $rows[] = ['kind' => 'same', 'text' => $a[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $rows[] = ['kind' => 'del', 'text' => $a[$i]];
                $i++;
            } else {
                $rows[] = ['kind' => 'add', 'text' => $b[$j]];
                $j++;
            }
        }
        while ($i < $n) {
            $rows[] = ['kind' => 'del', 'text' => $a[$i++]];
        }
        while ($j < $m) {
            $rows[] = ['kind' => 'add', 'text' => $b[$j++]];
        }

        return $rows;
    }

    /**
     * diff() collapsed to N lines of context around each change, with long unchanged runs
     * replaced by a 'gap' marker row, so the reviewer sees the change without scrolling a
     * whole article of identical lines.
     */
    public function compactDiff(int $context = 3): array
    {
        $rows = $this->diff();
        $count = count($rows);
        $keep = array_fill(0, $count, false);
        foreach ($rows as $i => $r) {
            if ($r['kind'] !== 'same') {
                for ($j = max(0, $i - $context); $j <= min($count - 1, $i + $context); $j++) {
                    $keep[$j] = true;
                }
            }
        }

        $out = [];
        $skipped = 0;
        $flush = function () use (&$out, &$skipped) {
            if ($skipped > 0) {
                $out[] = ['kind' => 'gap', 'text' => "{$skipped} unchanged line".($skipped === 1 ? '' : 's')];
                $skipped = 0;
            }
        };
        foreach ($rows as $i => $r) {
            if ($keep[$i]) {
                $flush();
                $out[] = $r;
            } else {
                $skipped++;
            }
        }
        $flush();

        return $out;
    }
}
