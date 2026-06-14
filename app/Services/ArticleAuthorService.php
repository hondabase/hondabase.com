<?php

namespace App\Services;

use App\Models\ArticleAuthor;
use App\Models\AuthorAlias;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArticleAuthorService
{
    /** Ordered public author records for an article. */
    public function forArticle(string $repoPath): Collection
    {
        if (! Schema::hasTable('article_authors')) {
            return collect();
        }

        return ArticleAuthor::query()
            ->with('user')
            ->where('repo_path', $repoPath)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ArticleAuthor $credit) => $credit->user !== null);
    }

    /** Add a published HondaBase editor to an article's durable author list. */
    public function creditContributor(string $repoPath, User $user): ArticleAuthor
    {
        return DB::transaction(function () use ($repoPath, $user) {
            $credit = ArticleAuthor::firstOrNew([
                'repo_path' => $repoPath,
                'user_id' => $user->id,
            ]);

            if (! $credit->exists) {
                $credit->sort_order = ((int) ArticleAuthor::where('repo_path', $repoPath)->max('sort_order')) + 1;
            }
            $credit->is_contributor = true;
            $credit->save();

            return $credit;
        });
    }

    /** Move every credit from a verified ghost identity to a real Discord user. */
    public function mergeLegacyAuthor(User $legacy, User $target): void
    {
        if (! $legacy->is_legacy_author || $target->is_legacy_author || $legacy->is($target)) {
            throw new \InvalidArgumentException('A legacy author must be merged into a different non-legacy user.');
        }

        DB::transaction(function () use ($legacy, $target) {
            foreach (ArticleAuthor::where('user_id', $legacy->id)->get() as $credit) {
                $targetCredit = ArticleAuthor::firstOrNew([
                    'repo_path' => $credit->repo_path,
                    'user_id' => $target->id,
                ]);

                $targetCredit->is_original = $targetCredit->is_original || $credit->is_original;
                $targetCredit->is_contributor = $targetCredit->is_contributor || $credit->is_contributor;
                $targetCredit->sort_order = $targetCredit->exists
                    ? min($targetCredit->sort_order, $credit->sort_order)
                    : $credit->sort_order;
                $targetCredit->save();
                $credit->delete();
            }

            AuthorAlias::where('user_id', $legacy->id)->update(['user_id' => $target->id]);
            $legacy->delete();
        });
    }
}
