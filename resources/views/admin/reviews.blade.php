@extends('layouts.app')

@section('title', 'Review queue')

@push('head')
<meta name="robots" content="noindex">
@endpush

@section('content')
    <livewire:revision-review />
@endsection
