<?php

namespace App\Notifications;

use App\Models\ArticleRevision;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PendingEditSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public ArticleRevision $revision,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (method_exists($notifiable, 'pushSubscriptions') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        $authorName = $this->revision->author ? $this->revision->author->displayName() : 'A contributor';

        return [
            'revision_id' => $this->revision->id,
            'title' => $this->revision->title,
            'author_name' => $authorName,
            'url' => route('admin.reviews'),
            'type' => 'pending_edit',
        ];
    }

    public function toWebPush(object $notifiable, $notification = null)
    {
        $authorName = $this->revision->author ? $this->revision->author->displayName() : 'A contributor';

        return (new WebPushMessage)
            ->title("Pending Edit: {$this->revision->title}")
            ->body("{$authorName} submitted an edit for review.")
            ->icon('/favicon.ico')
            ->action('Review edit', 'open')
            ->data(['url' => route('admin.reviews')]);
    }
}
