@extends('layouts.admin')
@section('title', $e->fullName())
@section('content')
<x-page-header :title="$e->fullName()" :crumbs="['Employees' => route('admin.staff.employees.index')]" :sub="$e->employee_no.' · '.$e->department->name.($e->jobRole ? ' · '.$e->jobRole->title : '').' · since '.fmt_date($e->hire_date)">
    <x-badge :status="$e->status" />
    @perm('staff.manage')
        <a class="btn" href="{{ route('admin.staff.employees.edit', $e) }}"><x-icon name="edit" /> Edit</a>
        @if ($e->status !== 'terminated')
            <form method="post" action="{{ route('admin.staff.employees.terminate', $e) }}" data-confirm="Offboard {{ $e->fullName() }}? Their login is disabled, sessions ended and key cards revoked." data-reason data-danger>@csrf<button class="btn btn-danger" type="submit">Offboard</button></form>
        @endif
    @endperm
</x-page-header>
<div class="stats">
    <x-stat label="Days worked (30d)" :value="$summary['days']" />
    <x-stat label="Hours (30d)" :value="minutes_hm($summary['worked'])" />
    <x-stat label="Overtime (30d)" :value="minutes_hm($summary['overtime'])" />
    <x-stat label="Late arrivals (30d)" :value="$summary['late_days']" :hint="minutes_hm($summary['late']).' total'" />
    <x-stat label="Absences (30d)" :value="$summary['absent']" />
</div>
<div class="grid cols-main">
    <div class="card">
        <div class="card-head"><h2>Time card — last 30 days</h2>@perm('attendance.manage')<a class="small" href="{{ route('admin.staff.attendance.index', ['employee' => $e->id]) }}">Correct entries</a>@endperm</div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Date</th><th>Shift</th><th>In</th><th>Out</th><th class="num">Worked</th><th class="num">Late</th><th class="num">OT</th><th>Status</th></tr></thead>
            <tbody>@forelse ($records as $r)
                <tr><td class="nowrap">{{ fmt_date($r->work_date, 'D d M') }}</td><td class="small">{{ $r->shift?->name ?? '—' }}</td><td>{{ $r->clock_in?->format('H:i') ?? '—' }}</td><td>{{ $r->clock_out?->format('H:i') ?? '—' }}</td>
                    <td class="num">{{ minutes_hm($r->worked_minutes) }}</td><td class="num">{{ $r->late_minutes ? $r->late_minutes.'m' : '' }}</td><td class="num">{{ $r->overtime_minutes ? minutes_hm($r->overtime_minutes) : '' }}</td>
                    <td><x-badge :status="$r->status" />@if($r->corrected_by)<span class="small muted" title="{{ $r->correction_reason }}"> · corrected</span>@endif</td></tr>
            @empty<tr><td colspan="8" class="muted">No attendance recorded.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
    <div class="stack" style="align-content:start">
        <div class="card"><div class="card-head"><h2>Profile</h2></div><div class="card-body"><dl class="dl">
            <dt>Phone</dt><dd>{{ $e->phone ?? '—' }}</dd><dt>Email</dt><dd>{{ $e->email ?? '—' }}</dd><dt>Employment</dt><dd>{{ label($e->employment_type) }}</dd>
            <dt>Emergency</dt><dd>{{ $e->emergency_contact_name }} {{ $e->emergency_contact_phone }}</dd><dt>RFID badge</dt><dd class="mono">{{ $e->rfid_uid ?? '—' }}</dd>
            <dt>QR token</dt><dd class="mono small">{{ $e->qr_token ? substr($e->qr_token, 0, 8).'…' : '—' }}</dd>
        </dl></div></div>
        <div class="card">
            <div class="card-head"><h2>Leave balance {{ now()->year }}</h2></div>
            <div class="card-body">@foreach ($balances as $name => $bal)<div class="row between small"><span>{{ $name }}</span><strong>{{ $bal === null ? 'unlimited / unpaid' : $bal.' days' }}</strong></div>@endforeach</div>
        </div>
        @perm('staff.manage')
        <form method="post" action="{{ route('admin.staff.employees.login', $e) }}" class="card">
            @csrf
            <div class="card-head"><h2>System login</h2>@if($e->user)<x-badge :status="$e->user->status" />@endif</div>
            <div class="card-body form-grid">
                <x-input name="email" type="email" label="Login email" :value="$e->user?->email ?? $e->email" required col="f-12" />
                <x-input name="password" type="password" :label="$e->user ? 'New password (optional)' : 'Password'" :required="! $e->user" col="f-12" autocomplete="new-password" />
                <div class="field f-12"><span class="label">Roles</span>
                    <div class="row" style="gap:4px 14px">@foreach ($roles as $id => $n)<label class="check small"><input type="checkbox" name="roles[]" value="{{ $id }}" @checked($e->user?->roles->contains($id) || (! $e->user && $e->jobRole?->default_role_id == $id))> {{ $n }}</label>@endforeach</div></div>
            </div>
            <div class="card-foot"><button class="btn btn-primary btn-sm" type="submit">{{ $e->user ? 'Update login' : 'Create login' }}</button></div>
        </form>
        @endperm
    </div>
</div>
@endsection
