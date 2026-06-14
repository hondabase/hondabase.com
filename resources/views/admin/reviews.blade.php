@extends('layouts.app')

@section('title', 'Review queue')

@push('head')
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/editor.css">
@endpush

@section('content')
    <livewire:revision-review />
@endsection
