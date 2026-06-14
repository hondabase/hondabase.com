<div class="explorer">
    <div class="ex-search">
        <input type="search" wire:model.live.debounce.300ms="q"
               placeholder="Search the knowledgebase..." aria-label="Search articles" autocomplete="off"
               class="ex-input">
        <span class="ex-count" wire:loading.remove>{{ $total }} article{{ $total === 1 ? '' : 's' }}</span>
        <span class="ex-count" wire:loading>searching...</span>
    </div>

    @if ($scoped)
        <div class="ex-scope">
            <span class="ex-active-label">Searching</span>
            <button type="button" class="ex-scopebtn {{ $scopeAll ? '' : 'is-on' }}" wire:click="$set('scopeAll', false)">{{ $scopeLabel }}</button>
            <button type="button" class="ex-scopebtn {{ $scopeAll ? 'is-on' : '' }}" wire:click="$set('scopeAll', true)">Everything</button>
        </div>
    @endif

    @if ($activeLabels)
        <div class="ex-active">
            <span class="ex-active-label">Filtering by</span>
            @foreach ($activeLabels as $kv => $label)
                <button type="button" class="chip chip-active" wire:click="toggleFilter('{{ $kv }}')" wire:key="active-{{ $kv }}">
                    {{ $label }} <span aria-hidden="true">&times;</span>
                </button>
            @endforeach
            <button type="button" class="ex-clear" wire:click="clearAll">Clear all</button>
        </div>
    @endif

    @if ($forYou->isNotEmpty())
        <section class="ex-foryou">
            <h3 class="section-head">For you</h3>
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
                                        class="chip {{ in_array($kv, $filters, true) ? 'chip-active' : '' }}{{ $kind === 'obd' ? ' chip-obd' : ($kind === 'engine' ? ' chip-series' : '') }}">
                                    {{ $facet->label }} <span class="chip-c">{{ $facet->c }}</span>
                                </button>
                                @if ($isAuthed)
                                    @php $following = in_array($kv, $followed, true); @endphp
                                    <button type="button" wire:key="fol-{{ $kv }}"
                                            wire:click="toggleFollow('{{ $kv }}')"
                                            class="chip-follow {{ $following ? 'is-following' : '' }}"
                                            title="{{ $following ? 'Unfollow' : 'Follow' }}" aria-label="Follow {{ $facet->label }}">{{ $following ? '★' : '☆' }}</button>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="ex-group-title">No filters available.</p>
            @endforelse
        </aside>

        <div class="ex-results" wire:loading.class="is-loading">
            @forelse ($articles as $a)
                <a class="ex-card" href="{{ $a->url() }}" wire:navigate wire:key="a-{{ $a->id }}">
                    <div class="ex-card-kicker">{{ ucfirst($a->type) }} &middot; {{ ucwords(str_replace('-', ' ', $a->category)) }}</div>
                    <h3 class="ex-card-title">{{ $a->title }}</h3>
                    @if ($a->summary)
                        <p class="ex-card-summary">{{ \Illuminate\Support\Str::limit($a->summary, 120) }}</p>
                    @endif
                </a>
            @empty
                <div class="ex-noresults">
                    <p>No articles match your search.</p>
                    <button type="button" class="ex-clear" wire:click="clearAll">Reset</button>
                </div>
            @endforelse
        </div>
    </div>
</div>
