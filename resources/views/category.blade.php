@extends('layouts.app')

@section('title', $category_label)

@php
    $locale = $locale ?? \App\Support\Locales::default();
    $localizedCat = function (string $loc) use ($type, $category) {
        $p = \App\Support\Locales::isDefault($loc) ? '' : "/{$loc}";

        return url("{$p}/{$type}/{$category}");
    };
    $canonical = $localizedCat($locale);
    // Taxonomy-aware breadcrumbs from the controller (node + subject names); last = current page.
    $catCrumbs = $crumbs ?? [];
    $crumbItems = [['@type' => 'ListItem', 'position' => 1, 'name' => __('Home'), 'item' => url('/')]];
    $pos = 2;
    foreach ($catCrumbs as $c) {
        $crumbItems[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $c['name'], 'item' => url($c['url'])];
    }
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $crumbItems,
    ];
@endphp

@push('head')
<link rel="canonical" href="{{ $canonical }}">
@foreach (\App\Support\Locales::codes() as $loc)
<link rel="alternate" hreflang="{{ \App\Support\Locales::hreflang($loc) }}" href="{{ $localizedCat($loc) }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $localizedCat(\App\Support\Locales::default()) }}">
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush

@section('content')
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="/">{{ __('Home') }}</a>
        @foreach ($catCrumbs as $i => $c)
            <span class="sep">/</span>
            @if ($loop->last)
                <span class="current" aria-current="page">{{ $c['name'] }}</span>
            @else
                <a href="{{ $c['url'] }}">{{ $c['name'] }}</a>
            @endif
        @endforeach
    </nav>

    <section class="hero compact">
        <div class="tag">{{ $type_label }} &middot; {{ __('Knowledgebase') }}</div>
        <h2>{{ $category_label }}</h2>
    </section>

    <livewire:explorer :type="$type" :category="$category" />
@endsection
