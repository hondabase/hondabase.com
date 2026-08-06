<?php

namespace App\Notifications;

use App\Models\ArticleRevision;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class RevisionStatusUpdated extends Notification
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
        $status = ucfirst($this->revision->status);
        $url = $this->revision->status === 'approved' ? $this->revision->url() : route('admin.reviews');

        return [
            'revision_id' => $this->revision->id,
            'title' => $this->revision->title,
            'status' => $this->revision->status,
            'review_notes' => $this->revision->review_notes,
            'url' => $url,
            'type' => 'revision_status',
        ];
    }

    public function toWebPush(object $notifiable, $notification = null)
    {
        $statusLabel = match ($this->revision->status) {
            'approved' => 'Edit Approved',
            'rejected' => 'Edit Rejected',
            default => 'Edit Status Updated',
        };

        $body = "Your edit for \"{$this->revision->title}\" was {$this->revision->status}.";
        if ($this->revision->review_notes) {
            $body .= " Note: {$this->revision->review_notes}";
        }

        $url = $this->revision->status === 'approved' ? $this->revision->url() : '/';

        return (new WebPushMessage)
            ->title("{$statusLabel}: {$this->revision->title}")
            ->body($body)
            ->icon('/favicon.ico')
            ->action('View', 'open')
            ->data(['url' => $url]);
    }
}
