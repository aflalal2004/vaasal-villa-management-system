<!doctype html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <title>@yield('title', 'Partner portal') · Vaasal Villa Partners</title>
</head>
<body data-theme-url="{{ route('preferences.theme') }}">
@php $user = auth()->user(); $op = $user->tourOperator; @endphp
<div class="shell">
    <aside class="sidebar" aria-label="Partner navigation">
        <a class="brand" href="{{ route('operator.dashboard') }}" style="text-decoration:none;color:inherit">
            <x-logo-mark :size="36" />
            <span><span class="brand-name">Vaasal Villa</span><br><span class="brand-sub">Partner portal</span></span>
        </a>
        <nav class="nav">
            <div class="nav-group">{{ $op?->company_name }}</div>
            @foreach ([['operator.dashboard', 'Dashboard', 'dashboard', 'operator.dashboard'], ['operator.availability', 'Availability & rates', 'calendar', 'operator.availability'],
                ['operator.bookings.create', 'New group booking', 'plus', 'operator.bookings.create'], ['operator.bookings', 'Bookings', 'booking', 'operator.bookings*'],
                ['operator.invoices', 'Invoices & payments', 'file', 'operator.invoices*'], ['operator.statement', 'Statement', 'chart', 'operator.statement'],
                ['operator.company', 'Company profile', 'briefcase', 'operator.company*']] as [$r, $l, $i, $p])
                <a href="{{ route($r) }}" @class(['active' => request()->routeIs($p) && ! (request()->routeIs('operator.bookings.create') && $r === 'operator.bookings')])><x-icon :name="$i" /> {{ $l }}</a>
            @endforeach
        </nav>
    </aside>
    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-toggle" type="button" data-nav-toggle aria-label="Open menu"><x-icon name="menu" /></button>
            @if ($op?->status === 'pending')<span class="badge tone-warning">Account pending approval — bookings open once approved</span>@endif
            <div class="topbar-actions">
                <button class="icon-btn" type="button" data-theme-toggle aria-label="Switch theme"><x-icon name="theme" /></button>
                <div class="dropdown">
                    <button class="icon-btn" type="button" data-dropdown aria-label="Account" style="width:auto;padding:0 4px"><span class="avatar">{{ $user->initials() }}</span></button>
                    <div class="dropdown-menu" hidden>
                        <div style="padding:8px 10px"><strong>{{ $user->name }}</strong><br><span class="small muted">{{ $user->email }}</span></div>
                        <div class="sep"></div>
                        <a href="{{ route('password.change') }}"><x-icon name="lock" /> Change password</a>
                        <form method="post" action="{{ route('logout') }}">@csrf<button type="submit"><x-icon name="logout" /> Sign out</button></form>
                    </div>
                </div>
            </div>
        </header>
        <main class="content">
            <x-flash />
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset_v('assets/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
