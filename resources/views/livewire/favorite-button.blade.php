<div>
    @if ($articleId)
        <button type="button" class="fav-btn @if($saved) is-saved @endif" wire:click="toggle"
            aria-pressed="{{ $saved ? 'true' : 'false' }}"
            title="{{ $saved ? __('Saved to your favorites') : __('Save this article') }}">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="{{ $saved ? 'currentColor' : 'none' }}"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
            </svg>
            <span>{{ $saved ? __('Saved') : __('Save') }}</span>
        </button>
    @endif
</div>
