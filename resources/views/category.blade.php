@extends('layouts.app')

@section('title', $category_label)

@php
    $locale = $locale ?? \App\Support\Locales::default();
    $localizedCat = function (string $loc) use ($type, $category) {
        return \App\Support\Locales::isDefault($loc)
            ? route('article.category', ['type' => $type, 'category' => $category])
            : route('article.category.localized', ['locale' => $loc, 'type' => $type, 'category' => $category]);
    };
    $canonical = $localizedCat($locale);
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => __('Home'), 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $category_label, 'item' => $canonical],
        ],
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
        <span class="sep">/</span>
        <span class="current" aria-current="page">{{ $category_label }}</span>
    </nav>

    <section class="hero compact">
        <div class="tag">{{ $type_label }} &middot; {{ __('Knowledgebase') }}</div>
        <h2>{{ $category_label }}</h2>
    </section>

    <livewire:explorer :type="$type" :category="$category" />
@endsection
