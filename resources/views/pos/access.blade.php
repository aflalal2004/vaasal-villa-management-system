@extends('layouts.auth')
@section('title', 'POS access')
@section('subtitle', 'Restaurant POS · Cashier · Kitchen')
@section('content')
    @if ($state === 'guest')
        <div class="auth-note"><x-icon name="utensils" /> The POS is for restaurant staff. Sign in with your POS Manager, Cashier, Waiter or Kitchen account to open the terminal.</div>
        <div class="pos-access-list">
            <div><x-icon name="receipt" /><span><strong>Terminal</strong><small>Tables, orders, KOT, billing, room charge</small></span></div>
            <div><x-icon name="fire" /><span><strong>Kitchen display</strong><small>Live KOT queue for the kitchen</small></span></div>
            <div><x-icon name="drawer" /><span><strong>Cashier</strong><small>Shift, drawer, day-end closing</small></span></div>
        </div>
        <a class="btn btn-primary btn-lg btn-block" href="{{ route('login', ['to' => 'pos']) }}"><x-icon name="login" /> Sign in to POS</a>
    @else
        <div class="alert alert-danger" role="alert"><strong>No POS access for {{ $user->name }}.</strong><br>
            Your role ({{ $user->roles->pluck('name')->implode(', ') ?: 'none' }}) cannot open the restaurant POS. Ask an administrator to grant a POS role.</div>
        <div class="row">
            <a class="btn" href="{{ route($user->homeRoute()) }}"><x-icon name="arrow-left" /> Back to my dashboard</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-primary" type="submit"><x-icon name="logout" /> Sign in as someone else</button></form>
        </div>
    @endif
@endsection
