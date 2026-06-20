<div class="explorer">
    <div class="ex-search">
        <input type="search" wire:model.live.debounce.300ms="q"
               placeholder="{{ __('Search the knowledgebase...') }}" aria-label="{{ __('Search articles') }}" autocomplete="off"
               class="ex-input">
        <span class="ex-count" wire:loading.remove>{{ trans_choice('{0}:count articles|{1}:count article|[2,*]:count articles', $total, ['count' => $total]) }}</span>
        <span class="ex-count" wire:loading>{{ __('searching...') }}</span>
    </div>

    @if ($scoped)
        <div class="ex-scope">
            <span class="ex-active-label">{{ __('Searching') }}</span>
            <button type="button" class="ex-scopebtn {{ $scopeAll ? '' : 'is-on' }}" wire:click="$set('scopeAll', false)">{{ $scopeLabel }}</button>
            <button type="button" class="ex-scopebtn {{ $scopeAll ? 'is-on' : '' }}" wire:click="$set('scopeAll', true)">{{ __('Everything') }}</button>
        </div>
    @endif

    @if ($activeLabels)
        <div class="ex-active">
            <span class="ex-active-label">{{ __('Filtering by') }}</span>
            @foreach ($activeLabels as $kv => $label)
                <button type="button" class="chip chip-active" wire:click="toggleFilter('{{ $kv }}')" wire:key="active-{{ $kv }}">
                    {{ $label }} <span aria-hidden="true">&times;</span>
                </button>
            @endforeach
            <button type="button" class="ex-clear" wire:click="clearAll">{{ __('Clear all') }}</button>
        </div>
    @endif

    @if ($forYou->isNotEmpty())
        <section class="ex-foryou">
            <h3 class="section-head">{{ __('For you') }}</h3>
            <div class="ex-results">
                @foreach ($forYou as $a)
                    <a class="ex-card" href="{{ $a->url() }}" wire:navigate wire:key="fy-{{ $a->id }}">
                        <div class="ex-card-kicker">{{ ucfirst($a->type) }} &middot; {{ ucwords(str_replace('-', ' ', $a->category)) }}</div>
                        <h3 class="ex-card-title">{{ $a->title }}</h3>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="ex-body">
        <aside class="ex-facets" wire:loading.class="is-loading">
            @forelse ($groups as $kind => $group)
                <div class="ex-group">
                    <h4 class="ex-group-title">{{ $group['label'] }}</h4>
                    <div class="ex-chips">
                        @foreach ($group['items'] as $facet)
                            @php $kv = $facet->kind . ':' . $facet->value; @endphp
                            <span class="chipwrap">
                                <button type="button" wire:key="f-{{ $kv }}"
                                        wire:click="toggleFilter('{{ $kv }}')"
                                        class="chip {{ in_array($kv, $filters, true) ? 'chip-active' : '' }}">
                                    {{ $facet->label }} <span class="chip-c">{{ $facet->c }}</span>
                                </button>
                                @if ($isAuthed)
                                    @php $following = in_array($kv, $followed, true); @endphp
                                    <button type="button" wire:key="fol-{{ $kv }}"
                                            wire:click="toggleFollow('{{ $kv }}')"
                                            class="chip-follow {{ $following ? 'is-following' : '' }}"
                                            title="{{ $following ? __('Unfollow') : __('Follow') }}" aria-label="{{ __('Follow :label', ['label' => $facet->label]) }}">
                                        <svg viewBox="0 0 24 24" width="12" height="12" fill="{{ $following ? 'currentColor' : 'none' }}"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                        </svg>
                                    </button>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="ex-group-title">{{ __('No filters available.') }}</p>
            @endforelse
        </aside>

        <div class="ex-results" wire:loading.class="is-loading">
            @forelse ($articles as $a)
                <a class="ex-card @if(($isStaff ?? false) && $a->is_hidden) is-hidden @endif" href="{{ $a->url() }}" wire:navigate wire:key="a-{{ $a->id }}">
                    <div class="ex-card-kicker">{{ ucfirst($a->type) }} &middot; {{ ucwords(str_replace('-', ' ', $a->category)) }}</div>
                    <h3 class="ex-card-title">{{ $a->title }}</h3>
                    @if (($isStaff ?? false) && $a->is_hidden)
                        <span class="ex-card-hidden-badge">Hidden</span>
                    @endif
                    @if ($a->summary)
                        <p class="ex-card-summary">{{ \Illuminate\Support\Str::limit($a->summary, 120) }}</p>
                    @endif
                </a>
            @empty
                <div class="ex-noresults">
                    <p>{{ __('No articles match your search.') }}</p>
                    <button type="button" class="ex-clear" wire:click="clearAll">{{ __('Reset') }}</button>
                </div>
            @endforelse
        </div>
    </div>
</div>
