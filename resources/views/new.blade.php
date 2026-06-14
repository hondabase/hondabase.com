@extends('layouts.app')

@section('title', 'New article')

@push('head')
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/article.css">
<link rel="stylesheet" href="/assets/editor.css">
@endpush

@section('content')
    <livewire:article-creator />
@endsection
