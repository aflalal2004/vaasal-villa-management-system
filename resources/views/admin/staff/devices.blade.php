@extends('layouts.admin')
@section('title', 'Time clock devices')
@section('content')
<x-page-header title="Time clock devices" :crumbs="['Rosters' => route('admin.staff.roster.index')]" sub="RFID badge readers, QR scanners, biometric terminals and PIN kiosks post punches to the attendance API." />
@if (session('device_token'))
    <div class="alert alert-warning">Device API token (shown once): <code>{{ session('device_token') }}</code> <button class="btn btn-sm" type="button" data-copy="{{ session('device_token') }}">Copy</button></div>
@endif
<div class="grid cols-main">
    <div class="card">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Device</th><th>Type</th><th>Location</th><th>Last seen</th><th>Status</th></tr></thead>
            <tbody>@foreach ($devices as $d)<tr><td>{{ $d->name }}</td><td>{{ strtoupper($d->type) }}</td><td>{{ $d->location }}</td><td>{{ $d->last_seen_at?->diffForHumans() ?? 'never' }}</td><td><x-badge :status="$d->is_active ? 'active' : 'inactive'" /></td></tr>@endforeach</tbody>
        </table></div>
        <div class="card-body small">
            <strong>Integration</strong>: <code>POST {{ url('/api/v1/attendance/punch') }}</code> with <code>Authorization: Bearer &lt;token&gt;</code> and JSON
            <code>{"identifier_type":"rfid","identifier":"04A1B2C3"}</code> (or <code>qr</code> with the employee QR token, <code>biometric</code> with the enrolment ID,
            <code>pin</code> with <code>employee_no</code> + PIN). The first punch of a day clocks in; the next clocks out.
        </div>
    </div>
    <form method="post" action="{{ route('admin.staff.devices.store') }}" class="card" style="align-self:start">
        @csrf
        <div class="card-head"><h2>Register device</h2></div>
        <div class="card-body form-grid">
            <x-input name="name" label="Name" required col="f-12" />
            <x-select name="type" label="Type" :options="['kiosk' => 'PIN kiosk', 'rfid' => 'RFID reader', 'qr' => 'QR scanner', 'biometric' => 'Biometric']" col="f-12" />
            <x-input name="location" label="Location" col="f-12" />
        </div>
        <div class="card-foot"><button class="btn btn-primary btn-sm" type="submit">Register & get token</button></div>
    </form>
</div>
@endsection
