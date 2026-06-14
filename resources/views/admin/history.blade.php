@extends('layouts.app')

@section('title', 'Article history')

@push('head')
<meta name="robots" content="noindex">
@endpush

@section('content')
    <livewire:article-history :type="$type" :category="$category" :slug="$slug" />
@endsection
