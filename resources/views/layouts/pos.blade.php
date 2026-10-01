<!doctype html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <title>@yield('title', 'POS') · Vaasal Villa Restaurant POS</title>
    @stack('head')
</head>
@php
    $user = auth()->user();
    $shift = app(\App\Modules\POS\Services\PosShiftService::class)->current($user);
    $nav = \App\Modules\POS\Navigation::for($user);
    $fullscreen = request()->routeIs('pos.terminal', 'pos.order');
@endphp
<body @class(['console', 'console-pos', 'pos-fullscreen' => $fullscreen]) data-theme-url="{{ route('preferences.theme') }}" @if($fullscreen) data-sidebar-default="min" @endif>
<div class="shell">
    @include('layouts.partials.sidebar', ['nav' => $nav, 'sub' => 'Restaurant POS', 'home' => route('pos.access')])

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-toggle" type="button" data-nav-toggle aria-label="Open menu"><x-icon name="menu" /></button>
            <span class="topbar-title">@yield('title', 'POS')</span>
            <div class="topbar-actions">
                @perm('pos.shift')
                    @if ($shift)<a class="badge tone-success" href="{{ route('pos.shifts.show', $shift) }}"><x-icon name="drawer" /> Shift open · {{ $shift->outlet->name }} · since {{ $shift->opened_at->format('H:i') }}</a>
                    @else<a class="badge tone-warning" href="{{ route('pos.shifts.index') }}"><x-icon name="drawer" /> No open shift — open one</a>@endif
                @endperm
                @perm('dashboard.view')<a class="btn btn-sm system-switch" href="{{ route('admin.dashboard') }}" title="Switch to the Hotel PMS"><x-icon name="villa" /> <span>Hotel PMS</span></a>@endperm
                @include('layouts.partials.bell')
                <button class="icon-btn" type="button" data-theme-toggle aria-label="Switch theme"><x-icon name="theme" /></button>
                <div class="dropdown">
                    <button class="icon-btn" type="button" data-dropdown aria-label="Account" style="width:auto;padding:0 4px"><span class="avatar">{{ $user->initials() }}</span></button>
                    <div class="dropdown-menu" hidden>
                        <div style="padding:8px 10px"><strong>{{ $user->name }}</strong><br><span class="small muted">{{ $user->roles->pluck('name')->implode(', ') }}</span></div>
                        <div class="sep"></div>
                        @perm('attendance.self')<a href="{{ route('admin.staff.my-timecard') }}"><x-icon name="clock" /> My time card</a>@endperm
                        <a href="{{ route('password.change') }}"><x-icon name="lock" /> Change password</a>
                        <form method="post" action="{{ route('logout') }}">@csrf<button type="submit"><x-icon name="logout" /> Sign out</button></form>
                    </div>
                </div>
            </div>
        </header>
        <main class="content pos-main" id="main">
            <x-flash />
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset_v('assets/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
