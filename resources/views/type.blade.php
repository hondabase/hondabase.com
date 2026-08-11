@extends('layouts.app')

@section('title', __(ucfirst($type)))
@section('description', e(__('Browse the :type section of Hondabase: community-maintained Honda and Acura technical articles, wiring guides and reference material.', ['type' => __(ucfirst($type))])))

@section('content')
    <section class="hero compact">
        <div class="tag">{{ ucfirst($type) }} &middot; {{ __('Knowledgebase') }}</div>
        <h1>{{ ucfirst($type) }}</h1>
    </section>

    <livewire:explorer :type="$type" />
@endsection
