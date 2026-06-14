<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('description', 'Hondabase - a community-driven, GitHub-preserved technical knowledgebase for Honda and Acura vehicles.')">
    <title>@yield('title', 'Hondabase') - Honda Knowledgebase</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=IBM+Plex+Mono:ital,wght@0,400;0,500;1,400&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/base.css">
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
    <header class="site-header">
        <div class="wrap">
            <a href="/" class="brand" style="color:inherit">
                <h1>Honda<b>base</b></h1>
                <p>Community-Driven Honda Knowledgebase</p>
            </a>
            <nav class="nav">
                <a href="/pgmfi/wiki/">Wiki</a>
                <a href="https://files.hondabase.com">Files</a>
                <a href="/cars/engine/injector-offsets">Injector&nbsp;Offsets</a>
                <a href="/reference/error-codes/">Error&nbsp;Codes</a>
                <a href="https://discord.hondabase.com">Discord</a>
                <a href="https://github.com/Hondabase">GitHub</a>
                @auth
                    <a href="/me" class="nav-signin">My&nbsp;Hondabase</a>
                    <a href="/new" class="nav-signin">New&nbsp;article</a>
                    @can('manage-articles')
                        <a href="/admin/reviews" class="nav-signin">Reviews</a>
                    @endcan
                    @can('manage-staff')
                        <a href="/admin/staff" class="nav-signin">Staff</a>
                    @endcan
                    <livewire:notification-bell />
                    <span class="nav-user">{{ auth()->user()->displayName() }}</span>
                    <form method="POST" action="/auth/logout" class="nav-form">@csrf<button type="submit">Sign&nbsp;out</button></form>
                @else
                    <a href="/auth/login" class="nav-signin">Sign&nbsp;in</a>
                @endauth
            </nav>
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
            <span>&copy; {{ date('Y') }} <b>Hondabase</b> - open &amp; ad-free</span>
            <span>Content preserved on <a href="https://github.com/Hondabase">GitHub</a></span>
        </div>
    </footer>
    {{-- Livewire ships its own Alpine, used by both Livewire components and standalone
         Alpine widgets (e.g. error-codes). Do not also load Alpine separately. --}}
    @livewireScripts
    @stack('scripts')
</body>
</html>
