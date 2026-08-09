<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Services\RevisionNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Announce a newly published article in Discord #new-articles.
 *
 * Split out of CommitArticle so a Discord failure (bad frontmatter, API outage, rate limit)
 * retries on its own schedule and lands in failed_jobs when exhausted, instead of being
 * swallowed inside the commit job. Exceptions intentionally propagate from handle().
 */
class NotifyNewArticlePublished implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $revisionId, public int $articleId) {}

    /** Discord outages / rate limits recover on these timescales. */
    public function backoff(): array
    {
        return [30, 60, 300, 900];
    }

    public function handle(RevisionNotifier $notifier): void
    {
        $rev = ArticleRevision::find($this->revisionId);
        $article = Article::find($this->articleId);
        if ($rev === null || $article === null) {
            return; // deleted between dispatch and run; nothing to announce.
        }

        $notifier->notifyNewArticlePublished($rev, $article);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('NotifyNewArticlePublished exhausted retries; announcement was NOT sent', [
            'revision' => $this->revisionId,
            'article' => $this->articleId,
            'error' => $e->getMessage(),
        ]);
    }
}
