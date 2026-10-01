<!doctype html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <title>@yield('title', 'Sign in') · Vaasal Villa</title>
</head>
<body class="auth-body">
<div class="auth-v2" style="--auth-photo:url('{{ setting('site_hero_image') ?: 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1600&q=60' }}')">
    <main class="auth-card @yield('card_class')" id="main">
        <header class="auth-brand">
            <x-logo-mark :size="54" />
            <div>
                <div class="auth-brand-name">Vaasal Villa</div>
                <div class="auth-brand-sub">@yield('subtitle', 'Staff & Management Sign In')</div>
            </div>
            <button type="button" class="icon-btn auth-theme" data-theme-toggle aria-label="Switch theme"><x-icon name="theme" /></button>
        </header>
        <x-flash />
        @yield('content')
        <footer class="auth-foot">
            <a href="{{ route('home') }}"><x-icon name="arrow-left" /> Back to website</a>
            @unless (request()->routeIs('pos.access'))<a href="{{ route('pos.access') }}"><x-icon name="utensils" /> POS system</a>@endunless
            <span class="auth-foot-loc"><x-icon name="pin" /> {{ contact('location') }}</span>
        </footer>
    </main>
</div>
<script src="{{ asset_v('assets/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
