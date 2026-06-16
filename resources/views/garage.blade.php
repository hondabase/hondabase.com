@extends('layouts.app')

@section('title', 'My Garage')

@section('content')
    <nav class="crumbs">
        <a href="/me" wire:navigate>My Hondabase</a>
        <span class="sep">/</span>
        <span class="current">Garage</span>
    </nav>

    <section class="page-head">
        <h2 class="section-head">My Garage</h2>
        <p class="page-sub">Add your Honda products to personalize your experience. Details like engines
        or chassis are followed automatically, so matching articles surface in your feed.</p>
    </section>

    <livewire:garage />
@endsection
