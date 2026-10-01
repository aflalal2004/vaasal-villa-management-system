@extends('layouts.admin')
@section('title', 'Rosters & shifts')
@section('content')
<x-page-header title="Rosters & shifts" :sub="'Week of '.$start->format('d M Y').'. Lateness and overtime are calculated against the rostered shift.'">
    <form method="get" class="row">
        <a class="btn" href="?week={{ $start->copy()->subWeek()->toDateString() }}&department={{ request('department') }}" aria-label="Previous week"><x-icon name="arrow-left" /></a>
        <select name="department" data-autosubmit aria-label="Department"><option value="">All departments</option>@foreach ($departments as $id => $n)<option value="{{ $id }}" @selected(request('department') == $id)>{{ $n }}</option>@endforeach</select>
        <input type="hidden" name="week" value="{{ $start->toDateString() }}">
        <a class="btn" href="?week={{ $start->copy()->addWeek()->toDateString() }}&department={{ request('department') }}" aria-label="Next week"><x-icon name="arrow-right" /></a>
    </form>
    <a class="btn" href="{{ route('admin.staff.devices.index') }}"><x-icon name="id" /> Time clock devices</a>
    <button class="btn" type="button" data-modal-open="#shift-modal"><x-icon name="plus" /> Shift</button>
</x-page-header>
<div class="row mb">@foreach ($shifts as $s)<span class="chip" style="border-left:4px solid {{ $s->color }}">{{ $s->label() }} · grace {{ $s->grace_minutes }}m · break {{ $s->break_minutes }}m</span>@endforeach</div>
<form method="post" action="{{ route('admin.staff.roster.save') }}" class="card">
    @csrf
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Employee</th>@foreach ($days as $d)<th @class(['nowrap'])>{{ $d->format('D d') }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach ($employees as $e)
            <tr>
                <td class="nowrap"><strong>{{ $e->fullName() }}</strong><div class="small muted">{{ $e->department->name }}</div></td>
                @foreach ($days as $d)
                    @php
                        $cur = $entries[$e->id][$d->toDateString()] ?? null;
                        $onLeave = ($leave[$e->id] ?? collect())->first(fn ($l) => $l->start_date->lte($d) && $l->end_date->gte($d));
                    @endphp
                    <td>
                        @if ($onLeave)<x-badge status="on_leave" label="Leave" />@else
                        <select name="roster[{{ $e->id }}][{{ $d->toDateString() }}]" aria-label="{{ $e->fullName() }} {{ $d->format('D d') }}" style="min-width:110px;min-height:32px;padding:3px 6px">
                            <option value="">Off</option>@foreach ($shifts as $s)<option value="{{ $s->id }}" @selected($cur?->shift_id === $s->id)>{{ $s->name }}</option>@endforeach
                        </select>@endif
                    </td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="card-foot"><button class="btn btn-primary" type="submit">Save roster</button></div>
</form>
<x-modal id="shift-modal" title="New shift">
    <form method="post" action="{{ route('admin.staff.shifts.store') }}">
        @csrf
        <div class="modal-body form-grid">
            <x-input name="name" label="Name" required col="f-8" /><x-input name="color" type="color" label="Colour" value="#0E6B63" col="f-4" />
            <x-input name="start_time" type="time" label="Start" required col="f-6" /><x-input name="end_time" type="time" label="End" required col="f-6" />
            <x-input name="grace_minutes" type="number" min="0" label="Grace (min)" value="10" required col="f-6" /><x-input name="break_minutes" type="number" min="0" label="Unpaid break (min)" value="60" required col="f-6" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Add</button></div>
    </form>
</x-modal>
@endsection
