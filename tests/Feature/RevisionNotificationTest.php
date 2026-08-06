<?php

namespace Tests\Feature;

use App\Models\ArticleRevision;
use App\Models\User;
use App\Notifications\PendingEditSubmitted;
use App\Notifications\RevisionStatusUpdated;
use App\Services\RevisionNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RevisionNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_are_notified_when_pending_edit_is_submitted(): void
    {
        Notification::fake();

        $staff = User::factory()->create(['is_staff' => true]);
        $member = User::factory()->create(['is_staff' => false]);

        $revision = ArticleRevision::create([
            'user_id' => $member->id,
            'type' => 'cars',
            'category' => 'electronics',
            'slug' => 'test-article',
            'title' => 'Test Article Title',
            'repo_path' => 'cars/electronics/test-article/test-article.md',
            'base_sha' => '1234567890',
            'original_body' => 'Original',
            'proposed_body' => 'Proposed changes',
            'status' => 'pending',
        ]);

        app(RevisionNotifier::class)->notifyStaffOfPendingEdit($revision);

        Notification::assertSentTo($staff, PendingEditSubmitted::class, function ($notification) use ($revision) {
            return $notification->revision->id === $revision->id;
        });

        Notification::assertNotSentTo($member, PendingEditSubmitted::class);
    }

    public function test_author_is_notified_when_edit_status_is_updated(): void
    {
        Notification::fake();

        $staff = User::factory()->create(['is_staff' => true]);
        $member = User::factory()->create(['is_staff' => false]);

        $revision = ArticleRevision::create([
            'user_id' => $member->id,
            'type' => 'cars',
            'category' => 'electronics',
            'slug' => 'test-article',
            'title' => 'Test Article Title',
            'repo_path' => 'cars/electronics/test-article/test-article.md',
            'base_sha' => '1234567890',
            'original_body' => 'Original',
            'proposed_body' => 'Proposed changes',
            'status' => 'approved',
            'reviewer_id' => $staff->id,
            'review_notes' => 'Great addition!',
        ]);

        app(RevisionNotifier::class)->notifyAuthorOfStatusChange($revision);

        Notification::assertSentTo($member, RevisionStatusUpdated::class, function ($notification) use ($revision) {
            return $notification->revision->id === $revision->id && $notification->revision->status === 'approved';
        });
    }
}
