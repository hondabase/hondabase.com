<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Nav bell: unread count + a dropdown of recent notifications. Reads Laravel's `notifications`
 * table via the User's Notifiable trait. Opening an item marks it read and navigates to the
 * article; "Mark all read" clears the badge.
 */
class NotificationBell extends Component
{
    public bool $open = false;

    #[Computed]
    public function unread(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    #[Computed]
    public function items()
    {
        return auth()->user()->notifications()->latest()->limit(12)->get();
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        unset($this->unread, $this->items);
    }

    /** Mark one read and go to its article. */
    public function go(string $id)
    {
        $n = auth()->user()->notifications()->whereKey($id)->first();
        if (!$n) {
            return null;
        }
        $n->markAsRead();
        return redirect(data_get($n->data, 'url', '/'));
    }

    public function render(): View
    {
        return view('livewire.notification-bell');
    }
}
