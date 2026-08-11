@extends('layouts.app')

@section('title', 'Hondabase')
@section('description', __('Hondabase is a community-driven, GitHub-preserved technical knowledgebase for Honda and Acura: find guides for your exact model, generation and engine.'))

@section('content')
    <section class="hero compact-hero">
        <div class="tag">Honda &amp; Acura &middot; Technical Knowledgebase</div>
        <h1>Find your <span class="accent">vehicle.</span></h1>
        <p>Browse by product line, or type to jump straight to your model.</p>
    </section>

    <livewire:browser />

    <section>
        <div class="callout prose">
            <p>Found a gap or an error? Sign in with Discord to suggest an edit (reviewed before
            it goes live), or join the community on Discord and GitHub.</p>
            <a class="btn" href="https://discord.hondabase.com">Join the Discord &rarr;</a>
        </div>
    </section>
@endsection
