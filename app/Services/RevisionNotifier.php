<?php

namespace App\Services;

use App\Models\ArticleRevision;
use App\Models\User;
use App\Notifications\PendingEditSubmitted;
use App\Notifications\RevisionStatusUpdated;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class RevisionNotifier
{
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

    private function sendDiscordMessage(string $channelId, string $content): void
    {
        $botToken = config('services.discord.bot_token');
        if (! $botToken) {
            return;
        }

        try {
            Http::withHeaders([
                'Authorization' => 'Bot '.$botToken,
                'Content-Type' => 'application/json',
            ])->post("https://discord.com/api/v10/channels/{$channelId}/messages", [
                'content' => $content,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to send Discord channel message', ['channel' => $channelId, 'error' => $e->getMessage()]);
        }
    }
}
