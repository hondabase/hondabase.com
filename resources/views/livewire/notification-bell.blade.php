<div class="notif" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
    <button type="button" class="notif-btn" @click="open = !open" :aria-expanded="open.toString()" aria-label="{{ __('Notifications') }}">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        @if ($this->unread > 0)
            <span class="notif-badge">{{ $this->unread > 9 ? '9+' : $this->unread }}</span>
        @endif
    </button>

    <div class="notif-panel" x-show="open" x-transition x-cloak>
        <div class="notif-head">
            <span>{{ __('Notifications') }}</span>
            @if ($this->unread > 0)
                <button type="button" class="btn-link" wire:click="markAllRead">{{ __('Mark all read') }}</button>
            @endif
        </div>

        @forelse ($this->items as $n)
            <button type="button" class="notif-item @if(is_null($n->read_at)) is-unread @endif"
                wire:click="go('{{ $n->id }}')" wire:key="n-{{ $n->id }}">
                <span class="notif-title">{{ data_get($n->data, 'is_new') ? __('New') : __('Updated') }}: {{ data_get($n->data, 'title') }}</span>
                @if (data_get($n->data, 'reason'))
                    <span class="notif-reason">{{ data_get($n->data, 'reason') }}</span>
                @endif
                <span class="notif-time">{{ $n->created_at->diffForHumans() }}</span>
            </button>
        @empty
            <p class="notif-empty">{{ __('Nothing yet. Follow a category, engine or chassis and new articles will show up here.') }}</p>
        @endforelse
    </div>
</div>
