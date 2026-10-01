@extends('layouts.admin')
@section('title', $t->ticket_no)
@section('content')
<x-page-header :title="$t->ticket_no.' · '.$t->title" :crumbs="['Maintenance' => route('admin.maintenance.index')]"
    :sub="($t->villa ? 'Villa '.$t->villa->code.' · ' : $t->location.' · ').(\App\Models\MaintenanceTicket::CATEGORIES[$t->category] ?? $t->category).' · reported '.fmt_dt($t->created_at).' by '.($t->reporter?->name ?? '—')">
    <x-badge :status="$t->severity" :label="\App\Models\MaintenanceTicket::SEVERITIES[$t->severity]" />
    <x-badge :status="$t->status" :label="\App\Models\MaintenanceTicket::STATUSES[$t->status]" />
</x-page-header>
<div class="grid cols-main">
    <div class="stack">
        <div class="card">
            <div class="card-body">
                <p style="white-space:pre-line">{{ $t->description ?: 'No details provided.' }}</p>
                @if ($t->photo_path)<img src="{{ asset('storage/'.$t->photo_path) }}" alt="Issue photo" style="max-width:420px;border-radius:10px">@endif
                @if ($t->blocks_inventory)
                    <div class="alert alert-danger" style="margin-top:12px">Villa is out of order{{ $t->block ? ' · nights blocked '.fmt_date($t->block->start_date).' → '.fmt_date($t->block->end_date) : '' }}. It returns to sale when this ticket is resolved.</div>
                @endif
                @if ($t->resolution_notes)<p><strong>Resolution:</strong> {{ $t->resolution_notes }} @if($t->cost)· cost {{ money($t->cost) }}@endif</p>@endif
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h2>History</h2></div>
            <div class="card-body"><ul class="timeline">@foreach ($history as $h)<li><div><strong>{{ label($h->action) }}</strong> · <span class="muted">{{ fmt_dt($h->created_at) }} {{ $h->user?->name }}</span><br>{{ $h->description }}</div></li>@endforeach</ul></div>
        </div>
    </div>
    <div class="stack" style="align-content:start">
        @perm('maintenance.manage')
        <form method="post" action="{{ route('admin.maintenance.status', $t) }}" class="card">
            @csrf
            <div class="card-head"><h2>Update status</h2></div>
            <div class="card-body form-grid">
                <x-select name="status" label="Status" :options="\App\Models\MaintenanceTicket::STATUSES" :value="$t->status" col="f-12" />
                <x-textarea name="resolution_notes" label="Resolution notes" :value="$t->resolution_notes" rows="3" help="Required when resolving." />
                <x-input name="cost" type="number" step="0.01" min="0" label="Cost" :value="$t->cost" col="f-12" />
            </div>
            <div class="card-foot"><button class="btn btn-primary" type="submit">Save</button></div>
        </form>
        <form method="post" action="{{ route('admin.maintenance.assign', $t) }}" class="card">
            @csrf
            <div class="card-body form-grid">
                <x-select name="assigned_to" label="Technician" :options="$staff" :value="$t->assigned_to" placeholder="Unassigned" col="f-12" />
                <div class="f-12"><button class="btn btn-sm" type="submit">Assign</button></div>
            </div>
        </form>
        @else
            <div class="card"><div class="card-body small">Assigned to {{ $t->assignee?->fullName() ?? 'nobody yet' }}.</div></div>
        @endperm
    </div>
</div>
@endsection
