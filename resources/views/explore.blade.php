@extends('layouts.app')

@section('title', __('Search'))
@section('description', __('Search the whole Hondabase catalog: filter Honda and Acura technical articles by category, OBD generation, chassis code, engine family and more.'))

@section('content')
    <section class="hero compact-hero">
        <div class="tag">Honda &amp; Acura &middot; {{ __('Technical Knowledgebase') }}</div>
        <h1>{{ __('Search the') }} <span class="accent">{{ __('whole') }}</span> {{ __('catalog.') }}</h1>
        <p>{{ __('Filter by category, OBD tag, chassis, engine family and more.') }}</p>
    </section>

    <livewire:explorer />
@endsection
