@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="err-container">
        <div class="err-graphic">
            <svg class="err-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 9h4V6h3V4h6v2h3v3h4v5h-4v3h-3v2h-6v-2H6v-3H2z" />
                <path d="M12 8v4" />
                <path d="M12 16h.01" />
            </svg>
            <div class="err-badge">{{ $code }}</div>
        </div>
        
        <h2 class="err-title">{{ $title }}</h2>
        <p class="err-message">{{ $message }}</p>
        
        <div class="err-actions">
            <a class="btn" href="/">&larr; {{ __('Back to Home') }}</a>
        </div>
    </div>
@endsection
