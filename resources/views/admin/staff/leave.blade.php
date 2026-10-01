@extends('layouts.admin')
@section('title', 'Leave')
@section('content')
<x-page-header title="Leave management" :sub="$onLeaveToday->count() ? 'On leave today: '.$onLeaveToday->map(fn ($l) => $l->employee->fullName())->implode(', ') : 'Nobody is on leave today.'">
    <button class="btn btn-primary" type="button" data-modal-open="#leave-modal"><x-icon name="plus" /> {{ $canApprove ? 'Record leave' : 'Request leave' }}</button>
    @perm('staff.manage')<button class="btn" type="button" data-modal-open="#type-modal">Leave types</button>@endperm
</x-page-header>
<div class="tabs">@foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $v)<a href="?status={{ $k }}" @class(['active' => $status === $k])>{{ $v }}</a>@endforeach</div>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th class="num">Days</th><th>Reason</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($requests as $l)
            <tr>
                <td>{{ $l->employee->fullName() }}<div class="small muted">{{ $l->employee->department->name }}</div></td>
                <td>{{ $l->type->name }}</td><td class="nowrap small">{{ fmt_date($l->start_date) }} – {{ fmt_date($l->end_date) }}</td><td class="num">{{ (float) $l->days }}</td>
                <td class="small">{{ $l->reason }}</td>
                <td><x-badge :status="$l->status" />@if($l->approver)<div class="small muted">{{ $l->approver->name }}{{ $l->remarks ? ': '.$l->remarks : '' }}</div>@endif</td>
                <td class="actions">
                    @if ($canApprove && $l->status === 'pending')
                        <form method="post" action="{{ route('admin.staff.leave.decide', $l) }}" style="display:inline">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-primary" type="submit">Approve</button></form>
                        <form method="post" action="{{ route('admin.staff.leave.decide', $l) }}" style="display:inline" data-confirm="Reject this leave request?" data-reason data-reason-field="remarks">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-sm" type="submit">Reject</button></form>
                    @endif
                    @if (in_array($l->status, ['pending', 'approved']))
                        <form method="post" action="{{ route('admin.staff.leave.cancel', $l) }}" style="display:inline" data-confirm="Cancel this leave?">@csrf<button class="btn btn-sm btn-ghost" type="submit">Cancel</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty title="No leave requests" icon="leaf" /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $requests->links() }}
</div>
<x-modal id="leave-modal" title="Leave request">
    <form method="post" action="{{ route('admin.staff.leave.store') }}">
        @csrf
        <div class="modal-body form-grid">
            @if ($canApprove)<x-select name="employee_id" label="Employee (leave blank for yourself)" :options="$employees" placeholder="Myself" col="f-12" />@endif
            <x-select name="leave_type_id" label="Type" :options="$types->pluck('name', 'id')" required col="f-12" />
            <x-input name="start_date" type="date" label="From" required col="f-6" /><x-input name="end_date" type="date" label="To" required col="f-6" />
            <x-checkbox name="half_day" label="Half day" col="f-12" />
            <x-input name="reason" label="Reason" col="f-12" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Submit</button></div>
    </form>
</x-modal>
<x-modal id="type-modal" title="Leave types">
    <div class="modal-body"><ul class="list">@foreach ($types as $t)<li class="small">{{ $t->name }} ({{ $t->code }}) · {{ $t->days_per_year ?: 'no limit' }} days · {{ $t->is_paid ? 'paid' : 'unpaid' }}</li>@endforeach</ul></div>
    <form method="post" action="{{ route('admin.staff.leave-types.store') }}">
        @csrf
        <div class="modal-body form-grid"><x-input name="code" label="Code" required col="f-3" /><x-input name="name" label="Name" required col="f-5" /><x-input name="days_per_year" type="number" min="0" label="Days / year" required col="f-4" /><x-checkbox name="is_paid" label="Paid" :checked="true" col="f-12" /></div>
        <div class="modal-foot"><button class="btn btn-primary" type="submit">Add type</button></div>
    </form>
</x-modal>
@endsection
