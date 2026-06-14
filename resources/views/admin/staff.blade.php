@extends('layouts.app')

@section('title', 'Staff management')

@push('head')
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/editor.css">
@endpush

@section('content')
    <livewire:staff-manager />
@endsection
