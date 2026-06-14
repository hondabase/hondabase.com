@extends('layouts.app')

@section('title', 'Article history')

@push('head')
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/editor.css">
@endpush

@section('content')
    <livewire:article-history :type="$type" :category="$category" :slug="$slug" />
@endsection
