@extends('layouts.app')

@section('title', 'Staff management')

@push('head')
<meta name="robots" content="noindex">
@endpush

@section('content')
    <livewire:staff-manager />
@endsection
