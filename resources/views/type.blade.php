@extends('layouts.app')

@section('title', ucfirst($type).' — Hondabase')

@section('content')
    <section class="hero compact">
        <div class="tag">{{ ucfirst($type) }} &middot; {{ __('Knowledgebase') }}</div>
        <h2>{{ ucfirst($type) }}</h2>
    </section>

    <livewire:explorer :type="$type" />
@endsection
