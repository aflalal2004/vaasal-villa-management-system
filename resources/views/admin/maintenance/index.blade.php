@extends('layouts.admin')
@section('title', 'Maintenance')
@section('content')
<x-page-header title="Maintenance" sub="Tickets from housekeeping, front desk and technicians. A blocking ticket takes the villa out of order until resolved.">
    @perm('maintenance.report')<a class="btn btn-primary" href="{{ route('admin.maintenance.create') }}"><x-icon name="plus" /> Report issue</a>@endperm
</x-page-header>
<div class="tabs">
    @foreach (['open_all' => 'All open', 'open' => 'New', 'in_progress' => 'In progress', 'on_hold' => 'On hold', 'resolved' => 'Resolved', 'all' => 'All'] as $k => $v)
        <a href="?status={{ $k }}" @class(['active' => $status === $k])>{{ $v }}</a>
    @endforeach
</div>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Ticket</th><th>Issue</th><th>Villa / location</th><th>Severity</th><th>Status</th><th>Assigned</th><th>Reported</th></tr></thead>
        <tbody>
        @forelse ($tickets as $t)
            <tr>
                <td><a class="mono" href="{{ route('admin.maintenance.show', $t) }}">{{ $t->ticket_no }}</a></td>
                <td>{{ $t->title }}<div class="small muted">{{ \App\Models\MaintenanceTicket::CATEGORIES[$t->category] ?? $t->category }}</div></td>
                <td>{{ $t->villa?->code ?? $t->location }}</td>
                <td><x-badge :status="$t->severity" :label="ucfirst($t->severity)" /></td>
                <td><x-badge :status="$t->status" :label="\App\Models\MaintenanceTicket::STATUSES[$t->status]" /></td>
                <td>{{ $t->assignee?->fullName() ?? '—' }}</td>
                <td class="small">{{ $t->created_at->diffForHumans() }}<br>{{ $t->reporter?->name }}</td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty title="No tickets" icon="wrench" /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $tickets->links() }}
</div>
@endsection
