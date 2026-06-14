<div class="review">
    <div class="rev-head">
        <h1>Staff management</h1>
        <a class="rev-link" href="/admin/reviews">Review queue →</a>
    </div>

    <p class="rev-section-note">Staff review members' edits and have their own edits applied
        immediately. The owner is always staff. {{ $staffCount }} user{{ $staffCount === 1 ? '' : 's' }}
        currently flagged as staff (plus the owner).</p>

    @if ($message)
        <div class="flash flash-ok" role="status">{{ $message }}</div>
    @endif

    <div class="staff-search">
        <input type="search" wire:model.live.debounce.300ms="q"
            placeholder="Search by Discord name, display name or GitHub login…" aria-label="Search users">
    </div>

    <div class="staff-list">
        @forelse ($users as $user)
            <article class="staff-row" wire:key="user-{{ $user->id }}">
                <img class="staff-avatar" src="{{ $user->avatarUrl() }}" alt="" width="40" height="40" loading="lazy">
                <div class="staff-id">
                    <b>{{ $user->displayName() }}</b>
                    <span class="staff-meta">
                        #{{ $user->id }}
                        @if ($user->github_login) · GitHub: {{ $user->github_login }} @endif
                        @if ($user->isOwner()) · <span class="staff-badge staff-badge-owner">owner</span>
                        @elseif ($user->is_staff) · <span class="staff-badge staff-badge-staff">staff</span>
                        @endif
                    </span>
                </div>
                <div class="staff-action">
                    @if ($user->isOwner())
                        <span class="staff-locked">always staff</span>
                    @else
                        <button type="button"
                            class="btn {{ $user->is_staff ? 'btn-reject' : '' }}"
                            wire:click="toggle({{ $user->id }})"
                            wire:confirm="{{ $user->is_staff ? 'Revoke' : 'Grant' }} staff for {{ $user->displayName() }}?">
                            {{ $user->is_staff ? 'Revoke staff' : 'Make staff' }}
                        </button>
                    @endif
                </div>
            </article>
        @empty
            <p class="staff-empty">No users match "{{ $q }}".</p>
        @endforelse
    </div>

    <div class="staff-pager">{{ $users->links() }}</div>
</div>
