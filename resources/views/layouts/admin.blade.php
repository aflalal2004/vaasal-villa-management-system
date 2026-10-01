<!doctype html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <title>@yield('title', 'Dashboard') · Vaasal Villa HMS</title>
    @stack('head')
</head>
<body class="console console-pms" data-theme-url="{{ route('preferences.theme') }}">
@php $user = auth()->user(); $nav = \App\Modules\Core\Navigation::admin($user); @endphp
<div class="shell">
    @include('layouts.partials.sidebar', ['nav' => $nav, 'sub' => 'Admin console · Hotel PMS', 'home' => route($user->homeRoute())])

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-toggle" type="button" data-nav-toggle aria-label="Open menu"><x-icon name="menu" /></button>
            @perm('bookings.view')
            <form class="search" action="{{ route('admin.bookings.index') }}" method="get" role="search">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search bookings, guests, OTA refs…" aria-label="Search bookings">
            </form>
            @endperm
            <div class="topbar-actions">
                @perm('pos.access')<a class="btn btn-sm system-switch" href="{{ route('pos.dashboard') }}" title="Switch to the Restaurant POS"><x-icon name="utensils" /> <span>Restaurant POS</span></a>@endperm
                <a class="icon-btn" href="{{ route('home') }}" target="_blank" title="Open website" aria-label="Open website"><x-icon name="globe" /></a>
                <button class="icon-btn" type="button" data-theme-toggle title="Theme: light / dark / system" aria-label="Switch theme"><x-icon name="theme" /></button>
                @include('layouts.partials.bell')
                <div class="dropdown">
                    <button class="icon-btn" type="button" data-dropdown aria-label="Account menu" style="width:auto;padding:0 4px;gap:8px;display:inline-flex">
                        <span class="avatar">{{ $user->initials() }}</span>
                    </button>
                    <div class="dropdown-menu" hidden>
                        <div style="padding:8px 10px">
                            <strong>{{ $user->name }}</strong><br>
                            <span class="small muted">{{ $user->roles->pluck('name')->implode(', ') }}</span>
                        </div>
                        <div class="sep"></div>
                        <a href="{{ route('profile.edit') }}"><x-icon name="user" /> My profile</a>
                        <a href="{{ route('password.change') }}"><x-icon name="lock" /> Change password</a>
                        @perm('attendance.self')<a href="{{ route('admin.staff.my-timecard') }}"><x-icon name="clock" /> My time card</a>@endperm
                        <button type="button" data-theme-toggle><x-icon name="theme" /> Theme: <span data-theme-label>{{ ucfirst($user->theme) }}</span></button>
                        <div class="sep"></div>
                        <form method="post" action="{{ route('logout') }}">@csrf<button type="submit"><x-icon name="logout" /> Sign out</button></form>
                    </div>
                </div>
            </div>
        </header>
        <main class="content" id="main">
            <x-flash />
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset_v('assets/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
