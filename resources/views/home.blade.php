@extends('layouts.app')

@section('title', 'Hondabase')

@push('head')
<link rel="stylesheet" href="/assets/explorer.css">
@endpush

@section('content')
    <section class="hero compact-hero">
        <div class="tag">Honda &amp; Acura · Technical Knowledgebase</div>
        <h2>Explore the <span class="accent">whole</span> catalog.</h2>
        <p>Search every article and filter by category, OBD generation, engine family, chassis
        and more. Type to reshape the results.</p>
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
