@extends('layouts.auth')
@section('title', 'Choose a new password')
@section('content')
    <div>
        <h2>Choose a new password</h2>
        <p class="muted small" style="margin-top:6px">At least {{ config('vaasal.security.password_min') }} characters with upper- and lower-case letters and a number.</p>
    </div>
    <form method="post" action="{{ route('password.update') }}" class="stack">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="{{ old('email', $email) }}" required></div>
        <div class="field"><label for="password">New password</label><input type="password" id="password" name="password" autocomplete="new-password" required></div>
        <div class="field"><label for="password_confirmation">Confirm new password</label><input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required></div>
        <button class="btn btn-primary btn-lg btn-block" type="submit">Reset password</button>
    </form>
@endsection
