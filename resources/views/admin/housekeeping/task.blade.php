@extends('layouts.admin')
@section('title', $task->villa->code.' task')
@section('content')
<x-page-header :title="$task->villa->code.' · '.\App\Models\HkTask::TYPES[$task->type]" :crumbs="auth()->user()->hasPermission('housekeeping.view') ? ['Housekeeping' => route('admin.housekeeping.index')] : ['My tasks' => route('admin.housekeeping.my')]"
    :sub="$task->villa->name.' · scheduled '.fmt_date($task->scheduled_date).' · '.($task->assignee?->fullName() ?? 'unassigned')">
    <x-badge :status="$task->status" :label="\App\Models\HkTask::STATUSES[$task->status]" />
</x-page-header>

@if ($task->inspection_result === 'fail' && $task->status === 'in_progress')
    <div class="alert alert-danger"><strong>Returned by inspection:</strong> {{ $task->inspection_notes }}</div>
@endif
@if ($task->booking)<div class="alert alert-info">Departure of {{ $task->booking->guest->fullName() }} ({{ $task->booking->reference }}). {{ $task->notes }}</div>@elseif($task->notes)<div class="alert alert-info">{{ $task->notes }}</div>@endif

<div class="grid cols-main">
    <div class="card">
        <div class="card-head"><h2>Checklist</h2><span class="small muted" id="progress">{{ $task->items->where('is_checked', true)->count() }}/{{ $task->items->count() }}</span></div>
        <div class="card-body">
            @if (! $task->assigned_to && $task->status === 'pending' && auth()->user()->employee)
                <form method="post" action="{{ route('admin.housekeeping.tasks.accept', $task) }}" style="margin-bottom:8px">@csrf<button class="btn btn-lg btn-block" type="submit"><x-icon name="check" /> Accept this task</button></form>
            @endif
            @if (in_array($task->status, ['pending', 'paused'], true))
                <form method="post" action="{{ route('admin.housekeeping.tasks.start', $task) }}">@csrf<button class="btn btn-primary btn-lg btn-block" type="submit"><x-icon name="{{ $task->status === 'paused' ? 'play' : 'broom' }}" /> {{ $task->status === 'paused' ? 'Resume cleaning' : 'Start cleaning' }}</button></form>
                <p class="small muted" style="margin-top:8px">Starting records the time and sets the villa to Cleaning.</p>
            @elseif ($task->status === 'in_progress')
                <form method="post" action="{{ route('admin.housekeeping.tasks.pause', $task) }}" class="row" style="margin-bottom:8px">@csrf<input name="reason" placeholder="Pause reason (optional)" maxlength="120" style="flex:1" aria-label="Pause reason"><button class="btn" type="submit"><x-icon name="pause" /> Pause</button></form>
            @endif
            <ul class="checklist" style="margin-top:10px">
                @foreach ($task->items as $item)
                    <li><label><input type="checkbox" data-toggle-url="{{ route('admin.housekeeping.tasks.toggle', [$task, $item]) }}" @checked($item->is_checked) @disabled($task->status !== 'in_progress')><span>{{ $item->label }}</span></label></li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="stack" style="align-content:start">
        @if ($task->status === 'in_progress')
        <form method="post" action="{{ route('admin.housekeeping.tasks.submit', $task) }}" class="card">
            @csrf
            <div class="card-head"><h2>Linen used</h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Item</th><th class="num">Fresh out</th><th class="num">Soiled in</th></tr></thead>
                <tbody>@foreach ($linen as $l)
                    <tr><td>{{ $l->name }} <span class="small muted">(par {{ $l->par_per_villa }})</span></td>
                        <td class="num"><input type="number" min="0" max="50" name="linen[{{ $l->id }}][out]" value="0" style="width:70px" aria-label="{{ $l->name }} out"></td>
                        <td class="num"><input type="number" min="0" max="50" name="linen[{{ $l->id }}][in]" value="0" style="width:70px" aria-label="{{ $l->name }} in"></td></tr>
                @endforeach</tbody>
            </table></div>
            <div class="card-body"><button class="btn btn-primary btn-lg btn-block" type="submit"><x-icon name="check" /> Finish & request inspection</button></div>
        </form>
        @endif

        @if ($task->status === 'inspection')
            @perm('housekeeping.inspect')
            <div class="card">
                <div class="card-head"><h2>Inspection</h2></div>
                <div class="card-body stack">
                    <form method="post" action="{{ route('admin.housekeeping.tasks.approve', $task) }}" class="stack" style="gap:8px">@csrf
                        <input type="text" name="notes" placeholder="Notes (optional)" aria-label="Approval notes">
                        <button class="btn btn-primary btn-block" type="submit"><x-icon name="check" /> Pass — villa ready</button></form>
                    <form method="post" action="{{ route('admin.housekeeping.tasks.reject', $task) }}" class="stack" style="gap:8px">@csrf
                        <input type="text" name="notes" required placeholder="What needs fixing?" aria-label="Rejection notes">
                        <button class="btn btn-block" type="submit">Fail — send back</button></form>
                </div>
            </div>
            @else
                <div class="alert alert-warning">Waiting for a supervisor to inspect.</div>
            @endperm
        @endif

        <div class="card">
            <div class="card-head"><h2>Details</h2></div>
            <div class="card-body">
                <dl class="dl">
                    <dt>Priority</dt><dd>{{ ucfirst($task->priority) }}</dd>
                    <dt>Started</dt><dd>{{ fmt_dt($task->started_at) }}</dd>
                    <dt>Finished</dt><dd>{{ fmt_dt($task->finished_at) }}</dd>
                    <dt>Duration</dt><dd>{{ $task->durationMinutes() ? $task->durationMinutes().' min' : '—' }}</dd>
                    <dt>Inspection</dt><dd>{{ $task->inspection_result ? ucfirst($task->inspection_result).' · '.$task->inspector?->name.' '.fmt_dt($task->inspected_at) : '—' }}</dd>
                    @if ($task->linen->isNotEmpty())<dt>Linen</dt><dd>@foreach ($task->linen as $m){{ $m->item->name }} {{ $m->qty_out }}/{{ $m->qty_in }}<br>@endforeach</dd>@endif
                </dl>
                @perm('housekeeping.manage')
                @if (! in_array($task->status, ['completed', 'cancelled']))
                    <form method="post" action="{{ route('admin.housekeeping.tasks.assign', $task) }}" class="row" style="margin-top:12px">@csrf
                        <select name="assigned_to" aria-label="Reassign" style="flex:1"><option value="">Unassigned</option>@foreach ($staff as $id => $n)<option value="{{ $id }}" @selected($task->assigned_to == $id)>{{ $n }}</option>@endforeach</select>
                        <button class="btn btn-sm" type="submit">Assign</button></form>
                    <form method="post" action="{{ route('admin.housekeeping.tasks.cancel', $task) }}" data-confirm="Cancel this task?" style="margin-top:8px">@csrf<button class="btn btn-sm btn-ghost" type="submit">Cancel task</button></form>
                @endif
                @endperm
            </div>
        </div>
        <div class="row">
            @perm('maintenance.report')<a class="btn btn-block" href="{{ route('admin.maintenance.create', ['villa_id' => $task->villa_id]) }}"><x-icon name="wrench" /> Report maintenance issue</a>@endperm
            @perm('lostfound.manage')<a class="btn btn-block" href="{{ route('admin.lost-found.create', ['villa_id' => $task->villa_id]) }}"><x-icon name="box" /> Log lost & found item</a>@endperm
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-toggle-url]').forEach(cb => cb.addEventListener('change', async () => {
    try {
        await VV.api(cb.dataset.toggleUrl, 'POST', { checked: cb.checked });
        const all = document.querySelectorAll('[data-toggle-url]');
        document.getElementById('progress').textContent = [...all].filter(x => x.checked).length + '/' + all.length;
    } catch (e) { cb.checked = !cb.checked; VV.toast(e.message, 'error'); }
}));
</script>
@endpush
