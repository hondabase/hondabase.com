@extends('layouts.app')

@section('title', __('Search') . ' — Hondabase')

@section('content')
    <section class="hero compact-hero">
        <div class="tag">Honda &amp; Acura &middot; {{ __('Technical Knowledgebase') }}</div>
        <h2>{{ __('Search the') }} <span class="accent">{{ __('whole') }}</span> {{ __('catalog.') }}</h2>
        <p>{{ __('Filter by category, OBD tag, chassis, engine family and more.') }}</p>
    </section>

    <livewire:explorer />
@endsection
