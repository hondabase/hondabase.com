@extends('layouts.app')

@section('title', $category_label)

@php
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $category_label, 'item' => url("/{$type}/{$category}")],
        ],
    ];
@endphp

@push('head')
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush

@section('content')
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="/">Home</a>
        <span class="sep">/</span>
        <span class="current" aria-current="page">{{ $category_label }}</span>
    </nav>

    <section class="hero compact">
        <div class="tag">{{ $type_label }} &middot; Knowledgebase</div>
        <h2>{{ $category_label }}</h2>
    </section>

    <livewire:explorer :type="$type" :category="$category" />
@endsection
