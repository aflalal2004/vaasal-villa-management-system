@extends('layouts.admin')
@section('title', 'Employees')
@section('content')
<x-page-header title="Employees" sub="Profiles, departments and job roles. A system login is optional — not every employee needs one.">
    @perm('staff.manage')
        <a class="btn" href="{{ route('admin.staff.departments.index') }}"><x-icon name="briefcase" /> Departments & roles</a>
        <a class="btn btn-primary" href="{{ route('admin.staff.employees.create') }}"><x-icon name="plus" /> New employee</a>
    @endperm
</x-page-header>
<form class="filters" method="get">
    <div class="field" style="flex:2 1 200px"><label for="q">Search</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Name or employee no."></div>
    <div class="field"><label for="department">Department</label><select id="department" name="department" data-autosubmit><option value="">All</option>@foreach ($departments as $id => $n)<option value="{{ $id }}" @selected(request('department') == $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status" data-autosubmit>@foreach (['active' => 'Active', 'on_leave' => 'On leave', 'terminated' => 'Terminated', 'all' => 'All'] as $k => $v)<option value="{{ $k }}" @selected(request('status', 'active') === $k)>{{ $v }}</option>@endforeach</select></div>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Employee</th><th>Department / role</th><th>Contact</th><th>System login</th><th>Today</th><th>Since</th></tr></thead>
        <tbody>
        @forelse ($employees as $e)
            @php $t = $today->get($e->id); @endphp
            <tr>
                <td><a href="{{ route('admin.staff.employees.show', $e) }}"><strong>{{ $e->fullName() }}</strong></a><div class="small muted mono">{{ $e->employee_no }}</div></td>
                <td>{{ $e->department->name }}<div class="small muted">{{ $e->jobRole?->title }}</div></td>
                <td class="small">{{ $e->phone }}<br>{{ $e->email }}</td>
                <td class="small">{{ $e->user ? $e->user->roles->pluck('name')->implode(', ') : '—' }}</td>
                <td>@if($t)<x-badge :status="$t->clock_out ? $t->status : ($t->clock_in ? 'active' : $t->status)" :label="$t->clock_in && ! $t->clock_out ? 'On duty since '.$t->clock_in->format('H:i') : label($t->status)" />@else<span class="muted small">—</span>@endif</td>
                <td class="small">{{ fmt_date($e->hire_date, 'M Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="6"><x-empty title="No employees" icon="users" /></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
