@extends('layouts.app')

@section('title', 'Edit ' . $slug)

@push('head')
<meta name="robots" content="noindex">
@vite('resources/js/editor.js')
@endpush

@section('content')
    <livewire:article-editor :type="$type" :category="$category" :slug="$slug" />
@endsection
