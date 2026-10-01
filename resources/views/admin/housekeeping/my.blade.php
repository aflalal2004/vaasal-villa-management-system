@extends('layouts.admin')
@section('title', 'My tasks')
@section('content')
<x-page-header title="My housekeeping tasks" :sub="$employee ? $employee->fullName().' · '.now()->format('l d M') : 'Your login is not linked to an employee profile.'">
    @perm('maintenance.report')<a class="btn" href="{{ route('admin.maintenance.create') }}"><x-icon name="wrench" /> Report issue</a>@endperm
    @perm('lostfound.manage')<a class="btn" href="{{ route('admin.lost-found.create') }}"><x-icon name="box" /> Lost item</a>@endperm
</x-page-header>

@if ($pool->isNotEmpty())
<section class="mb" aria-labelledby="pool-title">
    <h2 id="pool-title" class="section-title"><x-icon name="bell-ring" /> Ready for cleaning <span class="badge tone-warning">{{ $pool->count() }}</span></h2>
    <div class="stack" style="gap:10px">
        @foreach ($pool as $t)
            <div class="card hk-card hk-{{ $t->priority }}">
                <span class="hk-code">{{ $t->villa->code }}</span>
                <span><strong>{{ \App\Models\HkTask::TYPES[$t->type] }}</strong> · {{ ucfirst($t->priority) }} priority
                    <br><span class="small muted">{{ $t->booking ? 'After '.$t->booking->guest->fullName().' checked out · ' : '' }}created {{ $t->created_at->diffForHumans() }}</span></span>
                <form method="post" action="{{ route('admin.housekeeping.tasks.accept', $t) }}">@csrf<button class="btn btn-primary" type="submit"><x-icon name="check" /> Accept</button></form>
            </div>
        @endforeach
    </div>
</section>
@endif

<h2 class="section-title"><x-icon name="broom" /> Assigned to me</h2>
<div class="stack" style="gap:10px">
@forelse ($tasks as $t)
    <div class="card hk-card hk-{{ $t->priority }} {{ $t->status === 'in_progress' ? 'is-active' : '' }}">
        <a class="hk-code" href="{{ route('admin.housekeeping.tasks.show', $t) }}">{{ $t->villa->code }}</a>
        <a href="{{ route('admin.housekeeping.tasks.show', $t) }}" style="color:inherit;text-decoration:none">
            <strong>{{ \App\Models\HkTask::TYPES[$t->type] }}</strong> <x-badge :status="$t->status" :label="\App\Models\HkTask::STATUSES[$t->status]" /><br>
            <span class="small muted">{{ $t->items->where('is_checked', true)->count() }}/{{ $t->items->count() }} checklist · {{ ucfirst($t->priority) }} priority{{ $t->notes ? ' · '.$t->notes : '' }}</span>
            @if ($t->inspection_result === 'fail' && $t->status === 'in_progress')<br><span class="small" style="color:var(--crit)">Returned: {{ $t->inspection_notes }}</span>@endif
        </a>
        <span class="row" style="gap:6px;justify-content:flex-end">
            @if (in_array($t->status, ['pending', 'paused'], true))
                <form method="post" action="{{ route('admin.housekeeping.tasks.start', $t) }}">@csrf<button class="btn btn-primary" type="submit"><x-icon name="play" /> {{ $t->status === 'paused' ? 'Resume' : 'Start cleaning' }}</button></form>
            @elseif ($t->status === 'in_progress')
                <form method="post" action="{{ route('admin.housekeeping.tasks.pause', $t) }}">@csrf<button class="btn" type="submit"><x-icon name="pause" /> Pause</button></form>
                <a class="btn btn-primary" href="{{ route('admin.housekeeping.tasks.show', $t) }}"><x-icon name="check" /> Checklist</a>
            @endif
        </span>
    </div>
@empty
    <div class="card"><x-empty title="No tasks assigned" icon="broom">Accept a villa from “Ready for cleaning”, or your supervisor will assign one.</x-empty></div>
@endforelse
</div>
@endsection
