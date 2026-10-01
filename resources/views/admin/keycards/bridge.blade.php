@extends('layouts.admin')
@section('title', 'Lock Bridge')
@section('content')
<x-page-header title="Lock Bridge" :crumbs="['Key cards' => route('admin.keycards.index')]"
    sub="The Lock Bridge is a small service on the property network. It polls this system for encoder jobs, drives the vendor encoder / lock server, and uploads door events.">
    @perm('keycards.manage')<form method="post" action="{{ route('admin.keycards.bridge.token') }}" data-confirm="Generate a new bridge token? The running bridge stops working until you update its .env.">@csrf<button class="btn" type="submit"><x-icon name="refresh" /> Rotate token</button></form>@endperm
</x-page-header>

@if (session('bridge_token'))
    <div class="alert alert-warning">New token (shown once): <code>{{ session('bridge_token') }}</code> <button class="btn btn-sm" type="button" data-copy="{{ session('bridge_token') }}">Copy</button></div>
@endif

<div class="stats">
    <x-stat label="Driver" :value="ucfirst($driver)" :hint="$driver === 'simulator' ? 'Jobs complete instantly inside the app (development)' : 'Jobs wait for the on-prem bridge'" />
    <x-stat label="Pending jobs" :value="$pending" />
    <x-stat label="Failed (recent)" :value="$failed->count()" />
</div>

<div class="grid cols-main">
    <div class="card">
        <div class="card-head"><h2>Job queue</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Created</th><th>Action</th><th>Card</th><th>Status</th><th>By</th><th>Result</th></tr></thead>
            <tbody>
            @foreach ($jobs as $j)
                <tr><td class="small nowrap">{{ fmt_dt($j->created_at) }}</td><td>{{ label($j->action) }}</td><td class="mono small">{{ $j->payload['card_uid'] ?? '' }}</td>
                    <td><x-badge :status="$j->status" /></td><td class="small">{{ $j->requester?->name }}</td><td class="small muted">{{ $j->error ?? ($j->result['message'] ?? '') }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
    <div class="stack" style="align-content:start">
        <div class="card">
            <div class="card-head"><h2>Registered bridges</h2></div>
            <ul class="list">
                @foreach ($bridges as $b)
                    <li><x-badge :status="$b->isOnline() ? 'active' : 'failed'" :label="$b->isOnline() ? 'Online' : 'Offline'" />
                        <span class="small"><strong>{{ $b->name }}</strong> · {{ $b->vendor }} {{ $b->version }}<br><span class="muted">Last seen {{ $b->last_seen_at?->diffForHumans() ?? 'never' }} {{ $b->ip_address }}</span></span></li>
                @endforeach
            </ul>
        </div>
        <div class="card">
            <div class="card-head"><h2>Connecting a real lock system</h2></div>
            <div class="card-body small">
                <ol style="padding-left:18px;margin:0">
                    <li>Confirm the lock brand/model and obtain its PMS interface SDK (VingCard Visionline, dormakaba Ambiance, Salto Space, Onity…).</li>
                    <li>Implement <code>lock-bridge/src/Adapters/&lt;Vendor&gt;Adapter.php</code> using the provided interface.</li>
                    <li>Set <code>LOCK_DRIVER=bridge</code> in this app's <code>.env</code>, and the token + <code>LOCK_VENDOR</code> in <code>lock-bridge/.env</code>.</li>
                    <li>Run <code>php lock-bridge/bridge.php</code> as a Windows service on the front-office PC connected to the encoder.</li>
                    <li>Map each villa's door <em>Lock ID</em> on the villa edit screen.</li>
                </ol>
                <p style="margin-top:8px">API: <code>{{ url('/api/v1/lock-bridge') }}</code></p>
            </div>
        </div>
    </div>
</div>
@endsection
