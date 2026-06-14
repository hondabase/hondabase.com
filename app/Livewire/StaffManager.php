<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Owner-only staff management (the UI form of the hondabase:staff artisan command). The owner
 * searches users who have signed in and grants/revokes the article-management staff role. The
 * owner is staff implicitly and cannot revoke themselves into a lockout.
 */
class StaffManager extends Component
{
    use WithPagination;

    public string $q = '';

    public ?string $message = null;

    public function mount(): void
    {
        abort_unless(Gate::allows('manage-staff'), 403);
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function toggle(int $id): void
    {
        abort_unless(Gate::allows('manage-staff'), 403);

        $user = User::find($id);
        if ($user === null) {
            $this->message = "User #{$id} no longer exists.";

            return;
        }

        if ($user->isOwner()) {
            $this->message = 'The instance owner is always staff and cannot be changed here.';

            return;
        }

        $grant = ! $user->is_staff;
        $user->forceFill(['is_staff' => $grant])->save();
        $this->message = ($grant ? 'Granted' : 'Revoked')." staff for {$user->displayName()}.";
    }

    public function render(): View
    {
        abort_unless(Gate::allows('manage-staff'), 403);

        $term = trim($this->q);
        $users = User::query()
            ->when($term !== '', function ($w) use ($term) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
                $w->where(function ($s) use ($like) {
                    $s->where('discord_username', 'like', $like)
                        ->orWhere('discord_global_name', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('github_login', 'like', $like);
                });
            })
            ->orderByDesc('is_staff')
            ->orderBy('discord_username')
            ->paginate(20);

        return view('livewire.staff-manager', [
            'users' => $users,
            'staffCount' => User::where('is_staff', true)->count(),
        ]);
    }
}
