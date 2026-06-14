@extends('layouts.app')

@section('title', 'Edit ' . $slug)

@push('head')
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/article.css">
<link rel="stylesheet" href="/assets/editor.css">
@endpush

@section('content')
    <livewire:article-editor :type="$type" :category="$category" :slug="$slug" />
@endsection
