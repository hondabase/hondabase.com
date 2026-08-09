<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use App\Notifications\PendingEditSubmitted;
use App\Notifications\RevisionStatusUpdated;
use App\Support\ArticleDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class RevisionNotifier
{
    /** Discord channel for brand-new-article publish announcements (#new-articles). */
    private const NEW_ARTICLES_CHANNEL = '1535671816645779626';

    /**
     * Send push / database notification to staff users when a non-staff contributor
     * submits an edit or new article for review.
     */
    public function notifyStaffOfPendingEdit(ArticleRevision $revision): void
    {
        if ($revision->status !== 'pending') {
            return;
        }

        $staffUsers = User::where('is_staff', true)->get();

        // Include owner if specified by config and not already matched by is_staff
        $ownerId = config('services.discord.owner_id');
        if ($ownerId) {
            $owner = User::where('discord_id', $ownerId)->first();
            if ($owner && ! $staffUsers->contains('id', $owner->id)) {
                $staffUsers->push($owner);
            }
        }

        if ($staffUsers->isNotEmpty()) {
            Notification::send($staffUsers, new PendingEditSubmitted($revision));
        }

        $this->sendDiscordMessage('1261780902598410261', "📝 **New Edit Submitted**: \"{$revision->title}\" (#{$revision->id}) by ".($revision->author ? $revision->author->displayName() : 'A contributor').".\nReview at: <".route('admin.reviews').'>');
    }

    /**
     * Send push / database notification to the edit's author when their submitted edit
     * is approved or rejected by a reviewer.
     */
    public function notifyAuthorOfStatusChange(ArticleRevision $revision): void
    {
        if ($revision->status === 'approved') {
            $this->sendDiscordMessage('1254973874177708078', "✅ **Edit Approved**: \"{$revision->title}\" (#{$revision->id}) was approved and queued for commit.");
        }

        if (! $revision->author) {
            return;
        }

        // Don't notify if the user reviewed their own edit
        if ($revision->reviewer_id && (int) $revision->reviewer_id === (int) $revision->user_id) {
            return;
        }

        $revision->author->notify(new RevisionStatusUpdated($revision));
    }

    /**
     * Announce a brand-new article's commit in #new-articles. Only for genuinely new content
     * (matches the `original_body === ''` check CommitArticle already uses for follower
     * notifications) — edits to existing articles stay silent here.
     */
    public function notifyNewArticlePublished(ArticleRevision $revision, Article $article): void
    {
        $fm = ArticleDocument::parse($revision->proposed_body)['fm'];
        $url = url($article->url());

        $fields = [
            ['name' => 'Category', 'value' => "`{$article->type}/{$article->category}`", 'inline' => true],
        ];
        if ($article->complexity) {
            $fields[] = ['name' => 'Complexity', 'value' => "`{$article->complexity}`", 'inline' => true];
        }
        if ($appliesTo = $this->formatAppliesTo($fm['applies_to'] ?? [])) {
            $fields[] = ['name' => 'Applies To', 'value' => $appliesTo, 'inline' => false];
        }
        if (! empty($fm['tags'])) {
            $fields[] = ['name' => 'Tags', 'value' => implode(' ', array_map(fn ($t) => "`#{$t}`", $fm['tags'])), 'inline' => false];
        }
        if ($source = ($fm['sources'][0] ?? null)) {
            $fields[] = ['name' => 'Source', 'value' => "[{$source['name']}]({$source['url']})", 'inline' => true];
        }

        $this->sendDiscordMessage(
            self::NEW_ARTICLES_CHANNEL,
            "✅ **Article Approved:** [**{$article->title}**](<{$url}>)",
            [[
                'type' => 'rich',
                'url' => $url,
                'title' => $article->title,
                'description' => $article->summary,
                'color' => 3900150,
                'fields' => $fields,
                'footer' => ['text' => "Hondabase • Article Revision #{$revision->id}"],
            ]]
        );
    }

    /** "**Models:** a, b • **Chassis:** c" from an `applies_to` frontmatter block. */
    private function formatAppliesTo(array $appliesTo): string
    {
        $labels = ['models' => 'Models', 'chassis' => 'Chassis', 'engines' => 'Engines'];
        $parts = [];
        foreach ($labels as $key => $label) {
            if (! empty($appliesTo[$key])) {
                $parts[] = "**{$label}:** ".implode(', ', $appliesTo[$key]);
            }
        }

        return implode(' • ', $parts);
    }

    private function sendDiscordMessage(string $channelId, string $content, array $embeds = []): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $botToken = config('services.discord.bot_token');
        if (! $botToken) {
            return;
        }

        try {
            Http::withHeaders([
                'Authorization' => 'Bot '.$botToken,
                'Content-Type' => 'application/json',
            ])->post("https://discord.com/api/v10/channels/{$channelId}/messages", array_filter([
                'content' => $content,
                'embeds' => $embeds,
            ]));
        } catch (\Throwable $e) {
            Log::warning('Failed to send Discord channel message', ['channel' => $channelId, 'error' => $e->getMessage()]);
        }
    }
}
