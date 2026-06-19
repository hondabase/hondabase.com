<div>
    @if ($hidden)
        <div class="article-hidden-banner" role="alert">This article is hidden from the public.</div>
    @endif
    <button type="button"
        class="btn hide-article-btn @if($hidden) is-hidden @endif"
        wire:click="toggle"
        wire:loading.attr="disabled"
        wire:confirm="{{ $hidden ? 'Unhide this article? It will become publicly visible.' : 'Hide this article from the public?' }}">
        {{ $hidden ? 'Unhide article' : 'Hide article' }}
    </button>
</div>
