@extends('layouts.auth')
@section('title', 'Forgot password')
@section('content')
    <div>
        <h2>Reset your password</h2>
        <p class="muted small" style="margin-top:6px">Enter your account email. We'll send a single-use link that expires in 60 minutes.</p>
    </div>
    <form method="post" action="{{ route('password.email') }}" class="stack">
        @csrf
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <button class="btn btn-primary btn-lg btn-block" type="submit">Send reset link</button>
        <a href="{{ route('login') }}" class="small"><x-icon name="arrow-left" /> Back to sign in</a>
    </form>
    @if (app()->environment('local'))
        <p class="small muted">Local mode: emails are written to <code>storage/logs</code> (MAIL_MAILER=log).</p>
    @endif
@endsection
