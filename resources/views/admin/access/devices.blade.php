@extends('layouts.admin')
@section('title', 'RFID devices')
@section('content')
<x-page-header title="RFID devices" eyebrow="Access control" sub="Door readers and staff time clocks that post scans to the RFID API. Each device authenticates with its own token.">
    @if (config('vaasal.locks.rfid_simulator'))<a class="btn" href="{{ route('admin.access.simulator') }}"><x-icon name="rfid" /> Simulator</a>@endif
    <a class="btn" href="{{ route('admin.keycards.logs') }}"><x-icon name="history" /> Access logs</a>
</x-page-header>
@include('admin.access.partials.tabs')

@if (session('device_token'))
    <div class="alert alert-warning" role="alert"><strong>Device API token (shown once):</strong> <code>{{ session('device_token') }}</code>
        <button class="btn btn-sm" type="button" data-copy="{{ session('device_token') }}"><x-icon name="file" /> Copy</button></div>
@endif

<div class="grid cols-main">
    <div class="card">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Device</th><th>Purpose</th><th>Door / zone</th><th>Mode</th><th>Last seen</th><th class="num">Scans today</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($devices as $d)
                <tr>
                    <td><strong>{{ $d->name }}</strong><div class="small muted">{{ \App\Models\AttendanceDevice::TYPES[$d->type] ?? $d->type }}{{ $d->location ? ' · '.$d->location : '' }}</div></td>
                    <td>{{ \App\Models\AttendanceDevice::PURPOSES[$d->purpose] ?? $d->purpose }}</td>
                    <td>{{ $d->doorLabel() }}</td>
                    <td><x-badge :tone="$d->mode === 'simulator' ? 'info' : 'neutral'" :label="ucfirst($d->mode)" /></td>
                    <td class="small">{{ $d->last_seen_at?->diffForHumans() ?? 'never' }}</td>
                    <td class="num">{{ $d->scans_today }}</td>
                    <td><x-badge :status="$d->is_active ? 'active' : 'inactive'" /></td>
                    <td class="nowrap">
                        <button class="btn btn-sm btn-ghost" type="button" data-modal-open="#dev-{{ $d->id }}" aria-label="Edit {{ $d->name }}"><x-icon name="edit" /></button>
                        <form method="post" action="{{ route('admin.access.devices.token', $d) }}" style="display:inline" data-confirm="Issue a new token for {{ $d->name }}? The current token stops working immediately.">@csrf
                            <button class="btn btn-sm btn-ghost" type="submit" aria-label="Rotate token"><x-icon name="key" /></button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty title="No devices yet" icon="device">Register a reader on the right.</x-empty></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="card-body small">
            <strong>Integration</strong> — <code>POST {{ url('/api/v1/rfid/scan') }}</code> with <code>Authorization: Bearer &lt;token&gt;</code> and
            <code>{"identifier_type":"rfid","identifier":"04AABBCC1122","occurred_at":"{{ now()->toIso8601String() }}"}</code>.
            The reader opens its relay only when the response has <code>"decision":"allow"</code>. Time-clock readers also punch attendance. Full protocol: <code>docs/RFID.md</code>.
        </div>
    </div>

    <form method="post" action="{{ route('admin.access.devices.store') }}" class="card" style="align-self:start">
        @csrf
        <div class="card-head"><h2><x-icon name="plus" /> Register device</h2></div>
        <div class="card-body form-grid">
            @include('admin.access.partials.device-fields', ['d' => new \App\Models\AttendanceDevice(['type' => 'rfid', 'purpose' => 'access', 'mode' => 'hardware'])])
        </div>
        <div class="card-foot"><button class="btn btn-primary" type="submit">Register & show token</button></div>
    </form>
</div>

@foreach ($devices as $d)
    <x-modal :id="'dev-'.$d->id" :title="'Edit '.$d->name">
        <form method="post" action="{{ route('admin.access.devices.update', $d) }}">
            @csrf @method('put')
            <div class="modal-body form-grid">
                @include('admin.access.partials.device-fields', ['d' => $d, 'prefix' => 'e'.$d->id])
                <x-checkbox name="is_active" label="Active (accepts scans)" :checked="$d->is_active" col="f-12" />
            </div>
            <div class="modal-foot"><button class="btn" type="button" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
        </form>
    </x-modal>
@endforeach
@endsection
