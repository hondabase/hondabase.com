<?php

namespace App\Notifications;

use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to a user when an article matching something they follow is published or updated.
 * Carries only the article reference + a one-line reason ("matches your B-Series"); the
 * database payload is read by the in-app bell, and web-push (when the user subscribed) shows
 * the same headline. `WebPushChannel::class` resolves to a plain string, so referencing it is
 * safe even before the webpush package is installed; the via() guard never selects it until a
 * user actually has a push subscription.
 */
class ArticleChanged extends Notification
{
    use Queueable;

    public function __construct(
        public Article $article,
        public bool $isNew,
        public string $reason = '',
    ) {}

    /** Channels per user; only push to those who actually subscribed. */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (method_exists($notifiable, 'pushSubscriptions') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = \NotificationChannels\WebPush\WebPushChannel::class;
        }
        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'article_id' => $this->article->id,
            'title'      => $this->article->title,
            'url'        => $this->article->url(),
            'is_new'     => $this->isNew,
            'reason'     => $this->reason,
        ];
    }

    /** Web-push payload (used only once the webpush channel is active). */
    public function toWebPush(object $notifiable, $notification = null)
    {
        $verb = $this->isNew ? 'New article' : 'Updated';
        return (new \NotificationChannels\WebPush\WebPushMessage)
            ->title("{$verb}: {$this->article->title}")
            ->body($this->reason ?: 'On something you follow at Hondabase')
            ->icon('/assets/icon-192.png')
            ->action('Read it', 'open')
            ->data(['url' => $this->article->url()]);
    }
}
