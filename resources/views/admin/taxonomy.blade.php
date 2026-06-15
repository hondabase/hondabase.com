@extends('layouts.app')

@section('title', 'Product taxonomy')

@push('head')
<meta name="robots" content="noindex">
@endpush

@section('content')
    <livewire:taxonomy-manager />
@endsection
