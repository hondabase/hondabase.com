@extends('layouts.app')

@section('title', $art['title'])
@section('description', $art['seo_description'])

@php
    // Localized URL for this article in a given locale: canonical (unprefixed) for the default,
    // /{locale}/... otherwise. Drives the canonical link, hreflang alternates and the switcher.
    // The category may be an arbitrary-depth path (electronics/ecu), so URLs interpolate it whole.
    $localizedUrl = function (string $loc) use ($art) {
        $p = \App\Support\Locales::isDefault($loc) ? '' : "/{$loc}";

        return url("{$p}/{$art['type']}/{$art['category']}/{$art['slug']}");
    };
    $canonical = $localizedUrl($art['locale']);
    $localePrefix = \App\Support\Locales::isDefault($art['locale']) ? '' : "/{$art['locale']}";
    // One breadcrumb per category path segment (electronics, electronics/ecu, ...).
    $catCrumbs = [];
    $acc = '';
    foreach (array_filter(explode('/', $art['category'])) as $seg) {
        $acc = $acc === '' ? $seg : "{$acc}/{$seg}";
        $catCrumbs[] = ['name' => \Illuminate\Support\Str::headline($seg), 'url' => url("{$localePrefix}/{$art['type']}/{$acc}")];
    }
    $schemaAuthors = $art['authors']->map(fn ($credit) => [
        '@type' => 'Person',
        'name' => $credit->user->displayName(),
    ])->values()->all();
    $articleSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'TechArticle',
        'headline' => $art['title'],
        'description' => $art['seo_description'],
        'dateModified' => $art['updated'],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $canonical,
        ],
        'author' => $schemaAuthors ?: [[
            '@type' => 'Organization',
            'name' => 'Hondabase',
            'url' => config('app.url'),
        ]],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'Hondabase',
            'url' => config('app.url'),
        ],
        'keywords' => $art['tags'] ?: null,
    ], fn ($value) => $value !== null);
    $crumbItems = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')]];
    $pos = 2;
    foreach ($catCrumbs as $c) {
        $crumbItems[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $c['name'], 'item' => $c['url']];
    }
    $crumbItems[] = ['@type' => 'ListItem', 'position' => $pos, 'name' => $art['title'], 'item' => $canonical];
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $crumbItems,
    ];
@endphp

@push('head')
<link rel="canonical" href="{{ $canonical }}">
@foreach ($art['available_locales'] as $loc)
<link rel="alternate" hreflang="{{ \App\Support\Locales::hreflang($loc) }}" href="{{ $localizedUrl($loc) }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $localizedUrl(\App\Support\Locales::default()) }}">
<meta property="og:type" content="article">
<meta property="og:site_name" content="Hondabase">
<meta property="og:title" content="{{ $art['title'] }} - Honda Knowledgebase">
<meta property="og:description" content="{{ $art['seo_description'] }}">
<meta property="og:url" content="{{ $canonical }}">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $art['title'] }} - Honda Knowledgebase">
<meta name="twitter:description" content="{{ $art['seo_description'] }}">
@if ($art['updated'])
<meta property="article:modified_time" content="{{ $art['updated'] }}">
@endif
@foreach ($art['tags'] as $tag)
<meta property="article:tag" content="{{ $tag }}">
@endforeach
<script type="application/ld+json">{!! json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush

@section('content')
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="/">{{ __('Home') }}</a>
        @foreach ($catCrumbs as $c)
            <span class="sep">/</span>
            <a href="{{ $c['url'] }}">{{ $c['name'] }}</a>
        @endforeach
        <span class="sep">/</span>
        <span class="current" aria-current="page">{{ $art['title'] }}</span>
    </nav>

    @if (count($art['available_locales']) > 1)
        <nav class="article-langs" aria-label="{{ __('Language') }}">
            @foreach ($art['available_locales'] as $loc)
                @if ($loc === $art['locale'])
                    <span class="article-lang is-current" aria-current="true">{{ \App\Support\Locales::all()[$loc]['native'] }}</span>
                @else
                    <a class="article-lang" href="{{ $localizedUrl($loc) }}" hreflang="{{ \App\Support\Locales::hreflang($loc) }}">{{ \App\Support\Locales::all()[$loc]['native'] }}</a>
                @endif
            @endforeach
        </nav>
    @endif

    @if (!empty($art['is_fallback']))
        <p class="article-fallback" role="note">{{ __('This article is not translated yet. Showing the English version.') }}</p>
    @endif

    @php $at = $art['applies_to'] ?? []; @endphp
    <article class="article"
        data-ga-article="{{ $art['slug'] }}"
        data-ga-category="{{ $art['category'] }}"
        data-ga-type="{{ $art['type'] }}"
        data-ga-complexity="{{ $art['complexity'] }}"
        data-ga-obd="{{ implode(',', (array) ($at['obd'] ?? [])) }}"
        data-ga-engine="{{ implode(',', (array) ($at['engines'] ?? [])) }}"
        data-ga-tags="{{ implode(',', $art['tags']) }}">
        <header class="article-head">
            <div class="kicker">{{ $art['type_label'] }} &middot; {{ $art['category_label'] }}</div>
            <h1>{{ $art['title'] }}</h1>
            @if (!empty($art['summary']))
                <p class="summary">{{ $art['summary'] }}</p>
            @endif
            <p class="meta">
                @if ($art['updated'])
                    <time datetime="{{ $art['updated'] }}">{{ __('Updated :date', ['date' => \Illuminate\Support\Carbon::parse($art['updated'])->locale(app()->getLocale())->isoFormat('LL')]) }}</time>
                @endif
                @if (!empty($art['complexity']))
                    <span class="badge badge-{{ $art['complexity'] }}">{{ __(ucfirst($art['complexity'])) }}</span>
                @endif
            </p>
            @if (!empty($art['sources']))
                <p class="source-note">{{ __('Adapted from') }}
                    @if (str_starts_with($art['sources'][0]['url'], '/pgmfi/wiki'))
                        {{ $art['sources'][0]['name'] }}
                    @else
                        <a href="{{ $art['sources'][0]['url'] }}">{{ $art['sources'][0]['name'] }}</a>
                    @endif
                </p>
            @endif
            <div class="article-actions">
                <livewire:favorite-button :type="$art['type']" :category="$art['category']" :slug="$art['slug']" />
            </div>
        </header>

        @include('partials.facts', ['art' => $art])

        {{-- Context-aware search, article scope: find-in-this-article. Pure client-side
             (Alpine, re-inits on wire:navigate), highlights matches in the prose below. --}}
        <div class="article-find" x-data="articleFind()" x-cloak>
            <button type="button" class="find-toggle" @click="toggle()" :aria-expanded="open.toString()">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <span>{{ __('Find in this article') }}</span>
            </button>
            <div class="find-panel" x-show="open" x-transition x-cloak>
                <input type="search" x-ref="input" x-model="q" @input.debounce.200ms="run()"
                    @keydown.enter.prevent="$event.shiftKey ? prev() : next()" @keydown.escape="close()"
                    placeholder="{{ __('Type to search this page…') }}" aria-label="{{ __('Find in this article') }}">
                <span class="find-count" x-text="label()" x-show="q.trim().length >= 2"></span>
                <button type="button" class="find-nav" @click="prev()" :disabled="!count" aria-label="Previous match">&#8249;</button>
                <button type="button" class="find-nav" @click="next()" :disabled="!count" aria-label="Next match">&#8250;</button>
                <button type="button" class="find-nav" @click="close()" aria-label="Close find">&#10005;</button>
            </div>
        </div>

        <div class="prose-article">
            {!! $art['html'] !!}
        </div>

        @if ($art['authors']->isNotEmpty() || !empty($art['sources']))
            <section class="article-attribution" aria-labelledby="article-attribution-heading">
                <h2 id="article-attribution-heading">{{ __('Credits and source') }}</h2>
                @if ($art['authors']->isNotEmpty())
                    <p><span class="attribution-label">{{ __('Authors') }}</span> {{ $art['authors']->map(fn ($credit) => $credit->user->displayName())->join(', ') }}</p>
                @endif
                @foreach ($art['sources'] as $source)
                    <p>
                        <span class="attribution-label">{{ __('Source') }}</span>
                        @if (!empty($source['adapted'])) {{ __('Adapted from') }} @endif
                        @if (str_starts_with($source['url'], '/pgmfi/wiki'))
                            {{ $source['title'] ?? $source['name'] }}
                        @else
                            <a href="{{ $source['url'] }}">{{ $source['title'] ?? $source['name'] }}</a>
                        @endif
                        {{ __('on :name.', ['name' => $source['name']]) }}
                        @if (!empty($source['license']) && !empty($source['license_url']))
                            {!! __('Licensed under :license.', ['license' => '<a href="'.e($source['license_url']).'" rel="license">'.e($source['license']).'</a>']) !!}
                        @endif
                    </p>
                @endforeach
            </section>
        @endif

        @if (!empty($art['attachments']))
            <section class="article-attachments">
                <h2>{{ __('Attachments & Downloads') }}</h2>
                <div class="attachments-grid">
                    @foreach ($art['attachments'] as $attachment)
                        <div class="attachment-card">
                            <div class="attachment-header">
                                <span class="attachment-badge">{{ $attachment['ext'] }}</span>
                                <a href="{{ $attachment['url'] }}" class="attachment-name" title="{{ __('Download :name', ['name' => $attachment['name']]) }}" download>{{ $attachment['name'] }}</a>
                            </div>
                            <div class="attachment-meta">
                                <span>{{ __('Size: :kb KB', ['kb' => number_format($attachment['size'] / 1024, 1)]) }}</span>
                            </div>
                            <div class="attachment-hash" data-hash="{{ $attachment['hash'] }}">
                                <span>SHA-256: {{ substr($attachment['hash'], 0, 8) }}...{{ substr($attachment['hash'], -8) }}</span>
                                <button class="copy-hash-btn" title="Copy full SHA-256 hash" aria-label="Copy hash">
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <footer class="article-foot">
            @auth
                <a class="btn edit-cta" href="/edit/{{ $art['type'] }}/{{ $art['category'] }}/{{ $art['slug'] }}" wire:navigate>{{ __('Edit this article') }}</a>
                @foreach (\App\Support\Locales::others() as $loc)
                    @php $native = \App\Support\Locales::all()[$loc]['native']; @endphp
                    <a class="btn edit-cta edit-cta-lang" href="/{{ $loc }}/edit/{{ $art['type'] }}/{{ $art['category'] }}/{{ $art['slug'] }}" wire:navigate>
                        {{ in_array($loc, $art['available_locales'], true) ? __('Edit the :language translation', ['language' => $native]) : __('Translate to :language', ['language' => $native]) }}
                    </a>
                @endforeach
                @can('manage-articles')
                    <a class="edit-history-link" href="/admin/history/{{ $art['type'] }}/{{ $art['category'] }}/{{ $art['slug'] }}">{{ __('View edit history') }}</a>
                @endcan
                <p class="contribute">{{ __('Spotted an error or have something to add? Suggest an edit right here.') }}
                    @cannot('manage-articles') {{ __('Every change is reviewed before it goes live.') }} @endcannot</p>
            @else
                <a class="btn edit-cta" href="/auth/login?return={{ urlencode('https://www.hondabase.com/edit/' . $art['type'] . '/' . $art['category'] . '/' . $art['slug']) }}">{{ __('Sign in to suggest an edit') }}</a>
                <p class="contribute">{{ __('Spotted an error or have something to add? Sign in with Discord to suggest an edit. Every change is reviewed before it goes live.') }}</p>
            @endauth
        </footer>
    </article>
@endsection

@push('scripts')
<script>
// Site-wide Alpine components (like articleFind) are registered in resources/js/app.js to survive wire:navigate.
// Document-level event delegation is used here for copying hashes so it works after dynamic page transitions.
const copyText = (text) => {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        return navigator.clipboard.writeText(text);
    } else {
        return new Promise((resolve, reject) => {
            try {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.top = "0";
                textArea.style.left = "0";
                textArea.style.position = "fixed";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                const successful = document.execCommand('copy');
                document.body.removeChild(textArea);
                if (successful) {
                    resolve();
                } else {
                    reject(new Error('execCommand returned false'));
                }
            } catch (err) {
                reject(err);
            }
        });
    }
};

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.copy-hash-btn');
    if (!btn) return;
    
    e.preventDefault();
    e.stopPropagation();
    const container = btn.closest('.attachment-hash');
    const hash = container.getAttribute('data-hash');
    
    copyText(hash).then(() => {
        const originalSvg = btn.innerHTML;
        btn.classList.add('copied');
        btn.innerHTML = `<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="var(--green)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>`;
        
        setTimeout(() => {
            btn.classList.remove('copied');
            btn.innerHTML = originalSvg;
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
});
</script>
@endpush
