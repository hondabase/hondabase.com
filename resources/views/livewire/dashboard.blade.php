<div class="dashboard">
    @if ($isEmpty)
        {{-- ---------- Onboarding ---------- --}}
        <section class="onboarding">
            <h2>Welcome to your Hondabase.</h2>
            <p>Add your vehicle and we'll tailor your homepage and this dashboard to it:
            new articles for your engine and chassis show up here automatically.</p>
            <a class="btn" href="/me/garage" wire:navigate>Add your first vehicle &rarr;</a>
            <p class="hint">Or just <a href="/" wire:navigate>explore the catalog</a> and tap the star on
            anything to follow it, or <b>Save</b> on an article to bookmark it.</p>
        </section>
    @else
        {{-- ---------- Garage summary ---------- --}}
        <section class="dash-section">
            <div class="garage-head">
                <h2 class="section-head">Garage</h2>
                <a class="btn btn-sm" href="/me/garage" wire:navigate>Manage garage</a>
            </div>
            @if ($vehicles->isEmpty())
                <p class="empty">No vehicles yet. <a href="/me/garage" wire:navigate>Add one</a> to personalize your feed.</p>
            @else
                <div class="dash-vehicles">
                    @foreach ($vehicles as $v)
                        <div class="garage-card" wire:key="dv-{{ $v->id }}">
                            <div class="garage-card-main">
                                <h3>{{ $v->label() }}</h3>
                                <p class="garage-meta">
                                    @if ($v->engine)<span class="chip">{{ $v->engine }}</span>@endif
                                    @if ($v->chassis)<span class="chip">{{ strtoupper($v->chassis) }}</span>@endif
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ---------- Following feed ---------- --}}
        <section class="dash-section">
            <h2 class="section-head">From things you follow</h2>
            @if ($follows->isNotEmpty())
                <div class="follow-chips">
                    @foreach ($follows as $f)
                        <span class="chip chip-followed" wire:key="fol-{{ $f->id }}">
                            {{ $f->label ?: $f->value }}
                            <button type="button" wire:click="unfollow('{{ $f->kind }}:{{ $f->value }}')"
                                title="Unfollow" aria-label="Unfollow {{ $f->label ?: $f->value }}">&times;</button>
                        </span>
                    @endforeach
                </div>
            @endif

            @if ($feed->isEmpty())
                <p class="empty">Nothing here yet. Follow a category, engine or chassis on the
                <a href="/" wire:navigate>explorer</a> (or add a vehicle) to fill this feed.</p>
            @else
                <div class="grid">
                    @foreach ($feed as $a)
                        <a class="card" href="{{ '/' . $a->type . '/' . $a->category . '/' . $a->slug }}" wire:navigate wire:key="feed-{{ $a->id }}">
                            <h3 class="card-title">{{ $a->title }}</h3>
                            @if ($a->summary)<p class="card-sum">{{ \Illuminate\Support\Str::limit($a->summary, 120) }}</p>@endif
                            @if ($a->updated_at)<span class="card-meta">Updated {{ $a->updated_at->format('M j, Y') }}</span>@endif
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ---------- Saved articles ---------- --}}
        <section class="dash-section">
            <h2 class="section-head">Saved articles</h2>
            @if ($favorites->isEmpty())
                <p class="empty">Nothing saved yet. Tap <b>Save</b> on any article to bookmark it here.</p>
            @else
                <div class="saved-list">
                    @foreach ($favorites as $a)
                        <div class="saved-row" wire:key="fav-{{ $a->id }}">
                            <a href="{{ '/' . $a->type . '/' . $a->category . '/' . $a->slug }}" wire:navigate>{{ $a->title }}</a>
                            <button type="button" class="btn-link danger" wire:click="unsave({{ $a->id }})">Remove</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</div>
