@extends('layouts.admin')
@section('title', 'My time card')
@section('content')
@if (! $e)
    <x-page-header title="My time card" />
    <div class="card"><x-empty title="No employee profile" icon="user">Your login is not linked to an employee record. Ask HR to link it.</x-empty></div>
@else
<x-page-header title="My time card" :sub="$e->fullName().' · '.$e->department->name.' · '.$e->employee_no">
    <form method="get" class="row"><input type="month" name="month" value="{{ $month->format('Y-m') }}" data-autosubmit aria-label="Month"></form>
</x-page-header>
<div class="grid cols-main mb">
    <div class="card">
        <div class="card-body row between" style="gap:20px">
            <div>
                <div class="small muted">{{ now()->format('l, d F') }}</div>
                <div style="font-family:var(--serif);font-size:42px;font-weight:600;line-height:1.1" id="clock">{{ now()->format('H:i') }}</div>
                <div class="small">{{ $open ? 'On duty since '.$open->clock_in->format('H:i').($open->late_minutes ? ' · '.$open->late_minutes.' min late' : '') : 'Not clocked in' }}</div>
                @if ($roster->first())<div class="small muted">Today's shift: {{ $roster->first()->work_date->isToday() ? $roster->first()->shift->label() : 'none' }}</div>@endif
            </div>
            <form method="post" action="{{ route('admin.staff.clock') }}">@csrf
                <button class="btn btn-lg {{ $open ? 'btn-danger' : 'btn-primary' }}" type="submit" style="min-width:200px;min-height:64px;font-size:18px"><x-icon name="clock" /> {{ $open ? 'Clock out' : 'Clock in' }}</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Next 7 days</h2></div>
        <ul class="list">@forelse ($roster as $r)<li class="small"><strong style="width:90px">{{ fmt_date($r->work_date, 'D d M') }}</strong> {{ $r->shift->label() }}</li>@empty<li class="muted small">No shifts rostered.</li>@endforelse</ul>
    </div>
</div>
<div class="stats">
    <x-stat label="Days worked" :value="$summary['days']" :hint="$month->format('F Y')" />
    <x-stat label="Hours" :value="minutes_hm($summary['worked'])" />
    <x-stat label="Overtime" :value="minutes_hm($summary['overtime'])" />
    <x-stat label="Late arrivals" :value="$summary['late_days']" />
    <x-stat label="Early departures" :value="minutes_hm($summary['early'])" />
</div>
<div class="grid cols-main">
    <div class="card">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Date</th><th>Shift</th><th>In</th><th>Out</th><th class="num">Worked</th><th class="num">Late</th><th class="num">Early</th><th class="num">OT</th><th>Status</th></tr></thead>
            <tbody>@forelse ($records as $r)
                <tr><td class="nowrap">{{ fmt_date($r->work_date, 'D d') }}</td><td class="small">{{ $r->shift?->name }}</td><td>{{ $r->clock_in?->format('H:i') }}</td><td>{{ $r->clock_out?->format('H:i') }}</td>
                    <td class="num">{{ minutes_hm($r->worked_minutes) }}</td><td class="num">{{ $r->late_minutes ?: '' }}</td><td class="num">{{ $r->early_leave_minutes ?: '' }}</td><td class="num">{{ $r->overtime_minutes ? minutes_hm($r->overtime_minutes) : '' }}</td>
                    <td><x-badge :status="$r->status" /></td></tr>
            @empty<tr><td colspan="9" class="muted">No records this month.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
    <div class="stack" style="align-content:start">
        <form method="post" action="{{ route('admin.staff.leave.store') }}" class="card">
            @csrf
            <div class="card-head"><h2>Request leave</h2></div>
            <div class="card-body form-grid">
                <x-select name="leave_type_id" label="Type" :options="$leaveTypes->mapWithKeys(fn ($t) => [$t->id => $t->name.($balances[$t->id] !== null ? ' ('.$balances[$t->id].' left)' : '')])" required col="f-12" />
                <x-input name="start_date" type="date" label="From" required col="f-6" />
                <x-input name="end_date" type="date" label="To" required col="f-6" />
                <x-checkbox name="half_day" label="Half day" col="f-12" />
                <x-input name="reason" label="Reason" col="f-12" />
            </div>
            <div class="card-foot"><button class="btn btn-primary btn-sm" type="submit">Submit request</button></div>
        </form>
        <div class="card"><div class="card-head"><h2>My requests</h2></div>
            <ul class="list">@forelse ($leaveRequests as $l)<li class="small">{{ $l->type->name }} · {{ fmt_date($l->start_date, 'd M') }}–{{ fmt_date($l->end_date, 'd M') }} ({{ (float) $l->days }}d)<span class="spacer"></span><x-badge :status="$l->status" /></li>@empty<li class="muted small">None</li>@endforelse</ul>
        </div>
    </div>
</div>
@push('scripts')<script>setInterval(() => { const d = new Date(); document.getElementById('clock').textContent = d.toTimeString().slice(0, 5); }, 15000);</script>@endpush
@endif
@endsection
