@extends('layouts.auth')
@section('title', 'Sign in')
@php
    $toPos = str_contains((string) session('url.intended'), '/pos') || request()->query('to') === 'pos';
@endphp
@section('subtitle', $toPos ? 'POS Sign In · Restaurant & Cashier' : 'Staff & Management Sign In')
@section('content')
    @if ($toPos)
        <div class="auth-note"><x-icon name="utensils" /> Sign in with a POS role (POS Manager, Cashier, Waiter or Kitchen) to open the terminal.</div>
    @endif

    <form method="post" action="{{ route('login.attempt') }}" class="auth-form" id="login-form" novalidate>
        @csrf
        <div class="field">
            <label for="login">Username or email</label>
            <div class="input-icon">
                <x-icon name="user" />
                <input type="text" id="login" name="login" value="{{ old('login', old('email')) }}" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus
                    @class(['is-invalid' => $errors->has('login') || $errors->has('email')]) aria-describedby="login-error">
            </div>
            @if ($errors->has('login') || $errors->has('email'))<span class="error" id="login-error" role="alert">{{ $errors->first('login') ?: $errors->first('email') }}</span>@endif
        </div>
        <div class="field">
            <label for="password">Password</label>
            <div class="input-icon has-action">
                <x-icon name="lock" />
                <input type="password" id="password" name="password" autocomplete="current-password" required @class(['is-invalid' => $errors->has('password')])>
                <button type="button" class="input-action" data-toggle-password="password" aria-label="Show password" aria-pressed="false">
                    <x-icon name="eye" class="ico ico-show" /><x-icon name="eye-off" class="ico ico-hide" />
                </button>
            </div>
            @error('password')<span class="error" role="alert">{{ $message }}</span>@enderror
        </div>
        <div class="row between">
            <label class="check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Remember me</label>
            <a class="small" href="{{ route('password.request') }}">Forgot password?</a>
        </div>
        <button class="btn btn-primary btn-lg btn-block auth-submit" type="submit" data-busy-label="Signing in…">
            <x-icon name="spinner" class="ico btn-spinner" /><span class="btn-label">Sign in</span>
        </button>
    </form>

    @if ($demoRoles)
        <section class="demo-roles" aria-labelledby="demo-title" data-demo-password="{{ \App\Modules\Auth\Support\DemoLogin::PASSWORD }}">
            <div class="demo-head">
                <h2 id="demo-title">Quick role sign-in</h2>
                <span class="badge tone-warning">Local demo only</span>
            </div>
            <p class="small muted">Choose a role to fill its demo username and password, then press Sign in. Hidden automatically outside <code>APP_ENV=local</code>.</p>
            <div class="demo-grid" role="group" aria-label="Demo roles">
                @foreach ($demoRoles as $r)
                    <button type="button" class="demo-role" data-username="{{ $r['username'] }}" data-label="{{ $r['label'] }}" aria-pressed="false">
                        <span class="demo-ic"><x-icon :name="$r['icon']" /></span>
                        <span class="demo-txt"><strong>{{ $r['label'] }}</strong><small>{{ $r['description'] }}</small></span>
                    </button>
                @endforeach
            </div>
            <p class="demo-selected small" aria-live="polite" hidden>Selected: <strong data-demo-selected></strong> · username <code data-demo-user></code> · password <code>••••••••</code></p>
        </section>
    @endif

    <p class="small muted center">Tour operator without an account? <a href="{{ route('operator.register') }}">Apply for a partner account</a>.</p>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('login-form'), login = document.getElementById('login'), pwd = document.getElementById('password');
    // Loading state on submit (no double submits)
    form.addEventListener('submit', () => {
        const b = form.querySelector('.auth-submit');
        b.classList.add('is-busy'); b.setAttribute('aria-busy', 'true');
        b.querySelector('.btn-label').textContent = b.dataset.busyLabel;
    });
    // Demo role quick-fill (rendered only in the local environment)
    const panel = document.querySelector('.demo-roles');
    if (!panel) return;
    const sel = panel.querySelector('.demo-selected');
    panel.querySelectorAll('.demo-role').forEach(card => card.addEventListener('click', () => {
        panel.querySelectorAll('.demo-role').forEach(c => { c.classList.toggle('on', c === card); c.setAttribute('aria-pressed', c === card ? 'true' : 'false'); });
        login.value = card.dataset.username;
        pwd.value = panel.dataset.demoPassword;
        pwd.type = 'password';
        sel.hidden = false;
        sel.querySelector('[data-demo-selected]').textContent = card.dataset.label.toUpperCase();
        sel.querySelector('[data-demo-user]').textContent = card.dataset.username;
        form.querySelector('.auth-submit').focus();
    }));
})();
</script>
@endpush
