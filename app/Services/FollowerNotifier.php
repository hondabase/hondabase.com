<?php

namespace App\Services;

use App\Models\Article;
use App\Models\User;
use App\Notifications\ArticleChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Notifies the users who follow a facet that an indexed article matches. Driven from the
 * commit job once the article is live + indexed, so it reflects what's actually published.
 * Self-notification (the editor) is skipped, and each recipient gets a reason naming one of
 * their matching interests ("matches your B-Series").
 */
class FollowerNotifier
{
    public function notify(Article $article, bool $isNew, ?int $excludeUserId = null): int
    {
        // (user_id -> a matching follow label) for everyone following any of this article's
        // facets. One row per user is enough for the reason line; MIN keeps it deterministic.
        $rows = DB::table('follows as f')
            ->join('article_facets as af', function ($j) {
                $j->on('af.kind', '=', 'f.kind')->on('af.value', '=', 'f.value');
            })
            ->where('af.article_id', $article->id)
            ->when($excludeUserId, fn ($q) => $q->where('f.user_id', '!=', $excludeUserId))
            ->groupBy('f.user_id')
            ->select('f.user_id', DB::raw('MIN(COALESCE(f.label, f.value)) as reason_label'))
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $reasons = $rows->pluck('reason_label', 'user_id');
        $users = User::whereIn('id', $rows->pluck('user_id'))->get();

        foreach ($users as $user) {
            $label = $reasons[$user->id] ?? '';
            Notification::send($user, new ArticleChanged($article, $isNew, $label ? "Matches your {$label}" : ''));
        }

        return $users->count();
    }
}
