<div class="browser">

    {{-- ------------------------------------------------------------------ --}}
    {{-- Search box + typeahead                                             --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="br-search-wrap" x-data="{ open: false }">
        <div class="ex-search">
            <input type="search"
                   wire:model.live.debounce.300ms="bq"
                   placeholder="{{ __('Search models, chassis codes...') }}"
                   autocomplete="off"
                   class="ex-input"
                   aria-label="{{ __('Search products') }}"
                   x-on:input="open = $el.value.length > 0"
                   x-on:blur="setTimeout(() => open = false, 200)">
            @if (trim($bq) !== '')
                <a href="{{ route('explore') }}?q={{ urlencode($bq) }}"
                   class="br-search-all" wire:navigate>
                    {{ __('Search all articles for') }} &ldquo;{{ $bq }}&rdquo; &rarr;
                </a>
            @endif
        </div>

        @if ($suggestions->isNotEmpty())
            <div class="br-suggestions" x-show="open" x-cloak>
                @foreach ($suggestions as $s)
                    <button type="button"
                            class="br-suggestion"
                            wire:click="drillTo('{{ $s->path }}')"
                            wire:key="sug-{{ $s->id }}">
                        <span class="br-sug-name">{{ $s->name }}</span>
                        <span class="br-sug-meta">{{ ucfirst($s->type) }} &middot; {{ ucfirst($s->kind) }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Breadcrumb (hidden at root)                                         --}}
    {{-- ------------------------------------------------------------------ --}}
    @if (!empty($breadcrumb))
        <nav class="br-crumb" aria-label="{{ __('Browse path') }}">
            <button type="button"
                    class="br-crumb-home"
                    wire:click="drillTo('')"
                    title="{{ __('Product lines') }}"
                    aria-label="{{ __('Back to product lines') }}">
                <svg viewBox="0 0 16 16" width="13" height="13" fill="currentColor" aria-hidden="true">
                    <path d="M8 1L1 7h2v7h4v-4h2v4h4V7h2z"/>
                </svg>
            </button>
            <span class="br-crumb-sep" aria-hidden="true">/</span>
            @foreach ($breadcrumb as $crumb)
                @if ($crumb['current'])
                    <span class="br-crumb-current">{{ $crumb['label'] }}</span>
                @else
                    <button type="button"
                            class="br-crumb-link"
                            wire:click="drillTo('{{ $crumb['path'] }}')">{{ $crumb['label'] }}</button>
                @endif
                @if (!$loop->last)
                    <span class="br-crumb-sep" aria-hidden="true">/</span>
                @endif
            @endforeach
        </nav>
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Card grid                                                           --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="pl-grid" wire:loading.class="is-loading">

        @if ($node === '')
            {{-- Root slide: product lines from config --}}
            @foreach ($children as $line)
                @if (!empty($line['coming_soon']))
                    <div class="pl-card pl-card--soon">
                        <span class="pl-card-name">{{ $line['label'] }}</span>
                        <span class="pl-card-desc">{{ $line['desc'] }}</span>
                        <span class="pl-card-tag">{{ __('Coming soon') }}</span>
                    </div>
                @else
                    @php $count = $counts[$line['type']] ?? 0; @endphp
                    <button type="button"
                            class="pl-card"
                            wire:click="drillTo('{{ $line['type'] }}')">
                        <span class="pl-card-name">{{ $line['label'] }}</span>
                        <span class="pl-card-desc">{{ $line['desc'] }}</span>
                        <span class="pl-card-tag">
                            {{ $count }} {{ $count === 1 ? __('article') : __('articles') }}
                        </span>
                    </button>
                @endif
            @endforeach

        @else
            {{-- Type / node slide: taxonomy node children --}}
            @foreach ($children as $child)
                @php $count = $counts[$child->slug] ?? 0; @endphp
                <button type="button"
                        class="pl-card {{ $count === 0 ? 'pl-card--dim' : '' }}"
                        wire:click="drillTo('{{ $child->path }}')"
                        wire:key="node-{{ $child->id }}">
                    <span class="pl-card-name">{{ $child->name }}</span>
                    @if ($child->yearRange())
                        <span class="pl-card-desc">{{ $child->yearRange() }}</span>
                    @endif
                    <span class="pl-card-tag {{ $count === 0 ? 'text-muted' : '' }}">
                        {{ $count }} {{ $count === 1 ? __('article') : __('articles') }}
                    </span>
                </button>
            @endforeach

            {{-- See all articles -- always visible at type/node level --}}
            @if ($handoffUrl)
                <a class="pl-card pl-card--cta" href="{{ $handoffUrl }}" wire:navigate>
                    <span class="pl-card-name">{{ __('See all articles') }} &rarr;</span>
                    @if ($current)
                        <span class="pl-card-desc">{{ $current->name }}</span>
                    @endif
                </a>
            @endif
        @endif

    </div>

</div>
