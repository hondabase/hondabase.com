@extends('layouts.app')

@section('title', 'Hondabase')

@section('content')
    <section class="hero compact-hero">
        <div class="tag">Honda &amp; Acura · Technical Knowledgebase</div>
        <h2>Explore the <span class="accent">whole</span> catalog.</h2>
        <p>Search every article and filter by category, OBD tag, engine family, chassis
        and more. Type to reshape the results.</p>
    </section>

    <section class="product-lines">
        <h2 class="section-head">Product Lines</h2>
        <div class="pl-grid">
            <a class="pl-card" href="/cars">
                <span class="pl-card-name">Cars</span>
                <span class="pl-card-desc">Automobiles &amp; light trucks</span>
            </a>
            <a class="pl-card" href="/motorcycles">
                <span class="pl-card-name">Motorcycles</span>
                <span class="pl-card-desc">Road, off-road &amp; sport bikes</span>
            </a>
            <a class="pl-card" href="/aircraft">
                <span class="pl-card-name">Aircraft</span>
                <span class="pl-card-desc">HondaJet &amp; turbofan engines</span>
            </a>
            <div class="pl-card pl-card--soon">
                <span class="pl-card-name">Marine</span>
                <span class="pl-card-desc">Outboard motors &amp; jet boats</span>
                <span class="pl-card-tag">Coming soon</span>
            </div>
            <div class="pl-card pl-card--soon">
                <span class="pl-card-name">Power Equipment</span>
                <span class="pl-card-desc">Generators, tillers &amp; mowers</span>
                <span class="pl-card-tag">Coming soon</span>
            </div>
            <div class="pl-card pl-card--soon">
                <span class="pl-card-name">ATVs</span>
                <span class="pl-card-desc">All-terrain vehicles</span>
                <span class="pl-card-tag">Coming soon</span>
            </div>
            <div class="pl-card pl-card--soon">
                <span class="pl-card-name">Side-by-Sides</span>
                <span class="pl-card-desc">Pioneer SxS series</span>
                <span class="pl-card-tag">Coming soon</span>
            </div>
        </div>
    </section>

    <livewire:explorer />

    <section>
        <div class="callout prose">
            <p>Found a gap or an error? Sign in with Discord to suggest an edit (reviewed before
            it goes live), or join the community on Discord and GitHub.</p>
            <a class="btn" href="https://discord.hondabase.com">Join the Discord &rarr;</a>
        </div>
    </section>
@endsection
