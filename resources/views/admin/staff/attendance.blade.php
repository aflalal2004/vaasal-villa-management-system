@extends('layouts.admin')
@section('title', 'Attendance')
@section('content')
<x-page-header title="Attendance & time cards" sub="Late = after shift start + grace. Overtime = worked beyond the rostered shift (8h when not rostered). Corrections keep the original punches.">
    <a class="btn" href="{{ route('admin.staff.attendance.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"><x-icon name="download" /> Timesheet CSV</a>
    <button class="btn btn-primary" type="button" data-modal-open="#manual-modal"><x-icon name="plus" /> Manual entry</button>
</x-page-header>
<div class="stats">
    <x-stat label="On duty now" :value="$onDuty->count()" :hint="$onDuty->map(fn ($r) => $r->employee->first_name)->take(6)->implode(', ')" />
    <x-stat label="Days present" :value="$summary['days']" />
    <x-stat label="Late arrivals" :value="$summary['late_days']" />
    <x-stat label="Absences" :value="$summary['absent']" />
    <x-stat label="Overtime" :value="minutes_hm($summary['overtime'])" />
</div>
<form class="filters" method="get">
    <div class="field"><label for="from">From</label><input id="from" type="date" name="from" value="{{ $from->toDateString() }}"></div>
    <div class="field"><label for="to">To</label><input id="to" type="date" name="to" value="{{ $to->toDateString() }}"></div>
    <div class="field"><label for="department">Department</label><select id="department" name="department"><option value="">All</option>@foreach ($departments as $id => $n)<option value="{{ $id }}" @selected(request('department') == $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="field"><label for="employee">Employee</label><select id="employee" name="employee"><option value="">All</option>@foreach ($employees as $id => $n)<option value="{{ $id }}" @selected(request('employee') == $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All</option>@foreach (['present', 'late', 'absent', 'on_leave', 'incomplete'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ label($s) }}</option>@endforeach</select></div>
    <button class="btn" type="submit">Apply</button>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Employee</th><th>Shift</th><th>In</th><th>Out</th><th class="num">Worked</th><th class="num">Late</th><th class="num">Early</th><th class="num">OT</th><th>Status</th><th>Source</th><th></th></tr></thead>
        <tbody>
        @forelse ($records as $r)
            <tr>
                <td class="nowrap">{{ fmt_date($r->work_date, 'D d M') }}</td>
                <td><a href="{{ route('admin.staff.employees.show', $r->employee) }}">{{ $r->employee->fullName() }}</a><div class="small muted">{{ $r->employee->department->name }}</div></td>
                <td class="small">{{ $r->shift?->label() ?? '—' }}</td>
                <td>{{ $r->clock_in?->format('H:i') ?? '—' }}</td><td>{{ $r->clock_out?->format('H:i') ?? '—' }}</td>
                <td class="num">{{ minutes_hm($r->worked_minutes) }}</td><td class="num">{{ $r->late_minutes ?: '' }}</td><td class="num">{{ $r->early_leave_minutes ?: '' }}</td>
                <td class="num">{{ $r->overtime_minutes ? minutes_hm($r->overtime_minutes) : '' }}</td>
                <td><x-badge :status="$r->status" />@if($r->corrected_by)<div class="small muted" title="{{ $r->correction_reason }}">corrected by {{ $r->corrector?->name }}</div>@endif</td>
                <td class="small">{{ trim(($r->source_in ?? '').' / '.($r->source_out ?? ''), ' /') }}</td>
                <td class="actions"><button class="btn btn-sm btn-ghost" type="button" data-modal-open="#correct-modal" data-action="{{ route('admin.staff.attendance.correct', $r) }}"
                    data-in="{{ $r->clock_in?->format('Y-m-d\TH:i') ?? $r->work_date->format('Y-m-d').'T08:00' }}" data-out="{{ $r->clock_out?->format('Y-m-d\TH:i') }}" aria-label="Correct"><x-icon name="edit" /></button></td>
            </tr>
        @empty
            <tr><td colspan="12"><x-empty title="No attendance records" icon="clock" /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $records->links() }}
</div>

<x-modal id="correct-modal" title="Correct time card">
    <form method="post" action="#">
        @csrf
        <div class="modal-body form-grid">
            <div class="field f-6"><label for="c_in">Clock in</label><input type="datetime-local" id="c_in" name="clock_in" data-fill="in"></div>
            <div class="field f-6"><label for="c_out">Clock out</label><input type="datetime-local" id="c_out" name="clock_out" data-fill="out"></div>
            <x-select name="status" label="Override status (optional)" :options="['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'on_leave' => 'On leave']" placeholder="Calculate automatically" col="f-6" />
            <x-input name="reason" label="Reason" required col="f-6" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Save correction</button></div>
    </form>
</x-modal>
<x-modal id="manual-modal" title="Manual time entry">
    <form method="post" action="{{ route('admin.staff.attendance.manual') }}">
        @csrf
        <div class="modal-body form-grid">
            <x-select name="employee_id" label="Employee" :options="$employees" required col="f-12" />
            <x-input name="work_date" type="date" label="Date" :value="now()" required col="f-4" />
            <x-input name="clock_in" type="time" label="In" required col="f-4" />
            <x-input name="clock_out" type="time" label="Out" col="f-4" />
            <x-input name="reason" label="Reason" required col="f-12" placeholder="e.g. Forgot to clock in; confirmed by supervisor" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    </form>
</x-modal>
@endsection
