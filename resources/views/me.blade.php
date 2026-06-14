@extends('layouts.app')

@section('title', 'My Hondabase')

@push('head')
<link rel="stylesheet" href="/assets/me.css">
@endpush

@section('content')
    <section class="page-head">
        <h2 class="section-head">My Hondabase</h2>
        <p class="page-sub">Your garage, your feed and your saved articles.</p>
    </section>

    <livewire:dashboard />
@endsection
