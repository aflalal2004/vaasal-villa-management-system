@extends('layouts.admin')
@section('title', 'Housekeeping')
@section('content')
<x-page-header title="Housekeeping" sub="Needs cleaning → Cleaning → Awaiting inspection → Available. Checkout creates the cleaning task and alerts housekeeping automatically.">
    @perm('housekeeping.manage')
        <form method="post" action="{{ route('admin.housekeeping.stayovers') }}">@csrf<button class="btn" type="submit"><x-icon name="refresh" /> Create stay-over tasks</button></form>
        <a class="btn" href="{{ route('admin.housekeeping.linen') }}"><x-icon name="box" /> Linen</a>
        <a class="btn" href="{{ route('admin.housekeeping.checklists') }}"><x-icon name="check" /> Checklists</a>
        <button class="btn btn-primary" type="button" data-modal-open="#task-modal"><x-icon name="plus" /> New task</button>
    @endperm
</x-page-header>

@php $board = $villas->mapWithKeys(fn ($v) => [$v->id => $v->boardStatus(isset($arrivalsToday[$v->id]))]); @endphp
<div class="status-strip" role="list" aria-label="Villa status summary">
    @foreach (\App\Models\Villa::BOARD as $k => [$lbl, $icon, $tone])
        <div class="status-pill tone-{{ $tone }}" role="listitem"><x-icon :name="$icon" /><span class="sp-n">{{ $board->filter(fn ($s) => $s === $k)->count() }}</span><span class="sp-l">{{ $lbl }}</span></div>
    @endforeach
</div>

<div class="tabs">
    <a href="?view=board" @class(['active' => $view === 'board'])>Villa board</a>
    <a href="?view=tasks" @class(['active' => $view === 'tasks'])>Task board</a>
</div>

@if ($view === 'board')
<div class="board">
    @foreach ($villas as $v)
        @php $open = $tasks->where('villa_id', $v->id)->whereIn('status', \App\Models\HkTask::OPEN)->first(); @endphp
        @php [$bLabel, $bIcon, $bTone] = \App\Models\Villa::BOARD[$board[$v->id]]; @endphp
        <div class="tile hk-{{ $v->hk_status }} board-{{ $board[$v->id] }}">
            <div class="row between"><span class="code">{{ $v->code }}</span>
                <span class="row">@if(isset($arrivalsToday[$v->id]))<x-badge tone="accent" label="Arrival today" />@endif<span class="badge tone-{{ $bTone }} board-badge"><x-icon :name="$bIcon" /> {{ $bLabel }}</span></span></div>
            <div class="small muted">{{ $v->type->name }}@if($v->currentStay) · {{ $v->currentStay->booking->guest->last_name }} until {{ fmt_date($v->currentStay->departure, 'd M') }}@endif</div>
            <div class="row"><x-badge :status="$v->hk_status" :label="\App\Models\Villa::HK_LABELS[$v->hk_status]" />
                @if ($v->maintenance_status !== 'ok')<x-badge :status="$v->maintenance_status" :label="label($v->maintenance_status)" />@endif</div>
            @if ($open)
                <a class="small" href="{{ route('admin.housekeeping.tasks.show', $open) }}"><x-icon name="broom" /> {{ \App\Models\HkTask::TYPES[$open->type] }} · {{ \App\Models\HkTask::STATUSES[$open->status] }}</a>
                <span class="small"><x-icon name="user" /> {{ $open->assignee?->fullName() ?? 'Unassigned — open for housekeeping to accept' }}</span>
            @endif
            <span class="small muted"><x-icon name="clock" /> Updated {{ $v->updated_at?->diffForHumans() }}</span>
            @perm('housekeeping.manage')
            <form method="post" action="{{ route('admin.housekeeping.villa-status', $v) }}" class="row" style="gap:4px">
                @csrf
                <select name="hk_status" aria-label="Set status for {{ $v->code }}" style="min-height:30px;padding:3px 8px;flex:1">@foreach (\App\Models\Villa::HK_LABELS as $k => $lbl)<option value="{{ $k }}" @selected($v->hk_status === $k)>{{ $lbl }}</option>@endforeach</select>
                <button class="btn btn-sm" type="submit">Set</button>
            </form>
            @endperm
        </div>
    @endforeach
</div>
@else
<div class="kanban">
    @foreach (['pending' => 'To do', 'in_progress' => 'Cleaning / paused', 'inspection' => 'Inspection', 'completed' => 'Done today'] as $status => $title)
        @php $col = $tasks->whereIn('status', $status === 'in_progress' ? ['in_progress', 'paused'] : [$status])->when($status === 'completed', fn ($c) => $c->filter(fn ($t) => $t->scheduled_date->toDateString() === $date)); @endphp
        <div class="col">
            <h3><span>{{ $title }}</span><span>{{ $col->count() }}</span></h3>
            @foreach ($col as $t)
                <div class="card" style="padding:10px 12px;display:grid;gap:6px">
                    <div class="row between"><a href="{{ route('admin.housekeeping.tasks.show', $t) }}"><strong>{{ $t->villa->code }}</strong> · {{ \App\Models\HkTask::TYPES[$t->type] }}</a>
                        @if(in_array($t->priority, ['high', 'urgent']))<x-badge :status="$t->priority" :label="ucfirst($t->priority)" />@endif</div>
                    <div class="small muted">{{ $t->items->where('is_checked', true)->count() }}/{{ $t->items->count() }} checklist · {{ $t->started_at ? 'started '.$t->started_at->format('H:i') : 'not started' }}@if($t->rejection_count) · <span style="color:var(--crit)">returned {{ $t->rejection_count }}×</span>@endif</div>
                    @perm('housekeeping.manage')
                    @if ($status !== 'completed')
                    <form method="post" action="{{ route('admin.housekeeping.tasks.assign', $t) }}" class="row" style="gap:4px">
                        @csrf
                        <select name="assigned_to" data-autosubmit aria-label="Assign" style="min-height:30px;padding:3px 8px;flex:1"><option value="">Unassigned</option>@foreach ($staff as $id => $n)<option value="{{ $id }}" @selected($t->assigned_to == $id)>{{ $n }}</option>@endforeach</select>
                    </form>
                    @endif
                    @endperm
                    @if ($status === 'inspection')
                        @perm('housekeeping.inspect')
                        <div class="row">
                            <form method="post" action="{{ route('admin.housekeeping.tasks.approve', $t) }}">@csrf<button class="btn btn-sm btn-primary" type="submit"><x-icon name="check" /> Pass</button></form>
                            <form method="post" action="{{ route('admin.housekeeping.tasks.reject', $t) }}" data-confirm="Send {{ $t->villa->code }} back for re-cleaning?" data-reason data-reason-field="notes">@csrf<button class="btn btn-sm" type="submit">Fail</button></form>
                        </div>
                        @endperm
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>
@endif

<x-modal id="task-modal" title="New housekeeping task">
    <form method="post" action="{{ route('admin.housekeeping.tasks.store') }}">
        @csrf
        <div class="modal-body form-grid">
            <x-select name="villa_id" label="Villa" :options="$villas->mapWithKeys(fn ($v) => [$v->id => $v->code.' — '.$v->name])" required col="f-6" />
            <x-select name="type" label="Type" :options="\App\Models\HkTask::TYPES" required col="f-6" />
            <x-select name="priority" label="Priority" :options="['normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent', 'low' => 'Low']" col="f-4" />
            <x-input name="scheduled_date" type="date" label="Date" :value="now()" required col="f-4" />
            <x-select name="assigned_to" label="Assign to" :options="$staff" placeholder="Unassigned" col="f-4" />
            <x-textarea name="notes" label="Notes" rows="2" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Create task</button></div>
    </form>
</x-modal>
@endsection
