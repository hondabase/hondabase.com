@extends('layouts.app')

@section('title', $node->name)

@php
    $localizedNode = fn (string $loc) => (\App\Support\Locales::isDefault($loc) ? '' : "/{$loc}").'/'.$node->path;
    $canonical = url($localizedNode($locale));
    $chassis = collect($node->meta['chassis_codes'] ?? [])->map(fn ($c) => strtoupper($c));

    $crumbItems = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')]];
    $pos = 2;
    foreach ($crumbs as $c) {
        $crumbItems[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $c['name'], 'item' => url($c['url'])];
    }
    $breadcrumbSchema = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $crumbItems];
@endphp

@push('head')
<link rel="canonical" href="{{ $canonical }}">
@foreach (\App\Support\Locales::codes() as $loc)
<link rel="alternate" hreflang="{{ \App\Support\Locales::hreflang($loc) }}" href="{{ url($localizedNode($loc)) }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ url($localizedNode(\App\Support\Locales::default())) }}">
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush

@section('content')
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="/">{{ __('Home') }}</a>
        @foreach ($crumbs as $i => $c)
            <span class="sep">/</span>
            @if ($loop->last)
                <span class="current" aria-current="page">{{ $c['name'] }}</span>
            @else
                <a href="{{ $c['url'] }}">{{ $c['name'] }}</a>
            @endif
        @endforeach
    </nav>

    <section class="hero compact">
        <div class="tag">{{ ucfirst($node->kind) }} &middot; {{ __('Knowledgebase') }}</div>
        <h2>{{ $node->name }}</h2>
        @if ($node->yearRange() || $chassis->isNotEmpty())
            <p class="node-meta">
                @if ($node->yearRange())<span class="node-years">{{ $node->yearRange() }}</span>@endif
                @if ($chassis->isNotEmpty())<span class="node-chassis">{{ $chassis->join(', ') }}</span>@endif
            </p>
        @endif
    </section>

    @if ($children->isNotEmpty())
        <nav class="node-children" aria-label="{{ __('Sub-categories') }}">
            @foreach ($children as $child)
                <a class="node-child" href="{{ ($locale === \App\Support\Locales::default() ? '' : "/{$locale}").'/'.$child->path }}" wire:navigate>
                    {{ $child->name }}
                </a>
            @endforeach
        </nav>
    @endif

    <section class="node-articles">
        <h3 class="node-section">{{ trans_choice('{0}No articles yet|{1}:count article|[2,*]:count articles', $articles->count(), ['count' => $articles->count()]) }}</h3>
        @if ($articles->isNotEmpty())
            <div class="ex-results">
                @foreach ($articles as $a)
                    <a class="ex-card" href="{{ $a->url() }}" wire:navigate wire:key="node-{{ $a->id }}">
                        <div class="ex-card-kicker">{{ ucfirst($a->type) }} &middot; {{ ucwords(str_replace('/', ' / ', str_replace('-', ' ', $a->category))) }}</div>
                        <h3 class="ex-card-title">{{ $a->title }}</h3>
                    </a>
                @endforeach
            </div>
        @else
            <p class="node-empty">{{ __('No articles are filed under this yet. Browse the knowledgebase or suggest one.') }}</p>
        @endif
    </section>
@endsection
