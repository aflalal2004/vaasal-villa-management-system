@extends($layout)
@section('title', 'My profile')
@section('content')
<x-page-header title="My profile" :sub="$user->roles->pluck('name')->implode(' · ')">
    <a class="btn" href="{{ route('password.change') }}"><x-icon name="lock" /> Change password</a>
</x-page-header>
<div class="grid cols-main">
    <div class="card">
        <div class="card-head"><h2>Account</h2></div>
        <form method="post" action="{{ route('profile.update') }}">
            @csrf @method('put')
            <div class="card-body form-grid">
                <x-input name="name" label="Full name" :value="$user->name" required />
                <x-input name="email" type="email" label="Email (sign-in)" :value="$user->email" required />
                <x-input name="phone" label="Phone" :value="$user->phone" />
                <x-select name="theme" label="Theme" :options="['system' => 'Match my device', 'light' => 'Light', 'dark' => 'Dark / night']" :value="$user->theme" required />
            </div>
            <div class="card-foot"><button class="btn btn-primary" type="submit">Save profile</button></div>
        </form>
    </div>
    <div class="card">
        <div class="card-head"><h2>Recent sign-ins</h2></div>
        <ul class="list">
            @forelse ($logins as $l)
                <li><x-badge :status="$l->success ? 'ok' : 'failed'" :label="$l->success ? 'Success' : label($l->reason)" />
                    <span class="small">{{ fmt_dt($l->created_at) }}<br><span class="muted">{{ $l->ip_address }}</span></span></li>
            @empty
                <li class="muted">No sign-ins recorded.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
