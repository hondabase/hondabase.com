@extends('layouts.app')

@section('title', $category_label)

@push('head')
<link rel="stylesheet" href="/assets/article.css">
<link rel="stylesheet" href="/assets/explorer.css">
@endpush

@section('content')
    <nav class="crumbs">
        <a href="/">Home</a>
        <span class="sep">/</span>
        <span class="current">{{ $category_label }}</span>
    </nav>

    <section class="hero compact">
        <div class="tag">{{ $type_label }} &middot; Knowledgebase</div>
        <h2>{{ $category_label }}</h2>
    </section>

    <livewire:explorer :type="$type" :category="$category" />
@endsection
