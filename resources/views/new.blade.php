@extends('layouts.app')

@section('title', 'New article')

@push('head')
<meta name="robots" content="noindex">
@vite('resources/js/editor.js')
@endpush

@section('content')
    <livewire:article-creator />
@endsection
