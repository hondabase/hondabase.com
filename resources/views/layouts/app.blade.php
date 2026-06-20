<!DOCTYPE html>
<html lang="{{ config('hondabase.locales.'.app()->getLocale().'.hreflang', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', __('Hondabase - a community-driven, GitHub-preserved technical knowledgebase for Honda and Acura vehicles.'))">
    <title>@yield('title', 'Hondabase') - {{ __('Honda Knowledgebase') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=IBM+Plex+Mono:ital,wght@0,400;0,500;1,400&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if (config('hondabase.ga_id'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('hondabase.ga_id') }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){ dataLayer.push(arguments); }
            gtag('js', new Date());
            gtag('config', @json(config('hondabase.ga_id')), { send_page_view: true });
        </script>
        <script defer src="/assets/ga.js"></script>
    @endif
    @stack('head')
    @livewireStyles
</head>
<body>
    <header class="site-header" x-data="{ mobileMenuOpen: false }">
        <div class="wrap header-wrap">
            <a href="/" class="brand" style="color:inherit">
                <h1>Honda<b>base</b></h1>
                <p>{{ __('Community-Driven Honda Knowledgebase') }}</p>
            </a>
            <nav class="nav" :class="{ 'is-active': mobileMenuOpen }" x-cloak>
                <a href="/pgmfi/">pgmfi.org</a>
                <a href="https://files.hondabase.com">{{ __('Files') }}</a>
                @auth
                    <a href="/me" class="nav-signin">{{ __('My Hondabase') }}</a>
                    <a href="/new" class="nav-signin">{{ __('New article') }}</a>
                    @can('manage-articles')
                        <a href="/admin/reviews" class="nav-signin">{{ __('Reviews') }}</a>
                        <a href="/admin/taxonomy" class="nav-signin">Taxonomy</a>
                    @endcan
                    <span class="nav-user">{{ auth()->user()->displayName() }}</span>
                    <form method="POST" action="/auth/logout" class="nav-form">@csrf<button type="submit">{{ __('Sign out') }}</button></form>
                @else
                    <a href="/auth/login" class="nav-signin">{{ __('Sign in') }}</a>
                @endauth
            </nav>
            <div class="flex items-center gap-4">
                @auth
                    <livewire:notification-bell />
                @endauth
                <button type="button" class="burger-btn" @click="mobileMenuOpen = !mobileMenuOpen" :class="{ 'is-active': mobileMenuOpen }" aria-label="Toggle navigation" :aria-expanded="mobileMenuOpen.toString()">
                    <span class="burger-line"></span>
                    <span class="burger-line"></span>
                    <span class="burger-line"></span>
                </button>
            </div>
        </div>
    </header>

    <main class="wrap">
        @if (session('status'))
            <div class="flash flash-ok" role="status">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="wrap">
            <span>&copy; {{ date('Y') }} <b>Hondabase</b> - {{ __('open & ad-free') }}</span>
            <nav class="lang-switch" aria-label="{{ __('Language') }}">
                @foreach (config('hondabase.locales') as $code => $meta)
                    @if ($code === app()->getLocale())
                        <span class="text-dim" aria-current="true">{{ $meta['native'] }}</span>
                    @else
                        <a href="{{ route('locale.switch', $code) }}" hreflang="{{ $meta['hreflang'] }}" rel="nofollow">{{ $meta['native'] }}</a>
                    @endif
                @endforeach
            </nav>
            <span>{!! __('Content preserved on :github', ['github' => '<a href="https://github.com/Hondabase">GitHub</a>']) !!}</span>
        </div>
    </footer>
    {{-- Livewire ships its own Alpine, used by both Livewire components and standalone
         Alpine widgets (e.g. error-codes). Do not also load Alpine separately. --}}
    @livewireScripts
    @stack('scripts')
</body>
</html>
