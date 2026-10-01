@extends('layouts.pos')
@section('title', ($shift->status === 'open' ? 'X' : 'Z').' report')
@section('content')
<x-page-header :title="($shift->status === 'open' ? 'X report (mid-shift)' : 'Closing report (Z)').' · '.$shift->outlet->name"
    :sub="'Cashier '.$shift->user->name.' · '.fmt_dt($shift->opened_at).' → '.($shift->closed_at ? fmt_dt($shift->closed_at) : 'open')">
    <a class="btn" href="{{ route('pos.shifts.print', $shift) }}" target="_blank"><x-icon name="printer" /> A4 report</a>
    <a class="btn" href="{{ route('pos.shifts.print', [$shift, 'format' => 'thermal']) }}" target="_blank"><x-icon name="receipt" /> Thermal</a>
    @if ($shift->status === 'open' && ($shift->user_id === auth()->id() || auth()->user()->hasPermission('pos.shift_review')))
        <a class="btn btn-primary" href="{{ route('pos.day-end', ['shift' => $shift->id]) }}"><x-icon name="lock" /> Day-end closing</a>
    @endif
</x-page-header>
@include('pos.partials.cash-tabs')

<div class="row mb" style="gap:8px">
    <x-badge :status="$shift->status === 'open' ? 'active' : 'neutral'" :label="$shift->status === 'open' ? 'Shift open' : 'Closed & locked'" />
    @if ($shift->status === 'closed')
        <x-badge :tone="['approved' => 'success', 'flagged' => 'danger'][$shift->review_status] ?? 'warning'" :label="['approved' => 'Approved by '.$shift->reviewer?->name, 'flagged' => 'Flagged by '.$shift->reviewer?->name][$shift->review_status] ?? 'Awaiting manager review'" />
        <span class="small muted">Closed by {{ $shift->closer?->name }}</span>
    @endif
    @if ($shift->reopened_at)<span class="badge tone-warning">Reopened {{ fmt_dt($shift->reopened_at) }} by {{ $shift->reopener?->name }} — {{ $shift->reopen_reason }}</span>@endif
    @if ($billedOpen)<span class="badge tone-warning">{{ $billedOpen }} billed check(s) unpaid</span>@endif
</div>

@include('pos.partials.shift-reconciliation')

@if ($shift->denominations)
<div class="card mt"><div class="card-head"><h2>Drawer count by denomination</h2></div><div class="card-body">
    <div class="chips">@foreach ($shift->denominations as $d => $n)<span class="chip">{{ number_format($d) }} × {{ $n }} = {{ money($d * $n, null, false) }}</span>@endforeach</div>
</div></div>
@endif

<div class="grid cols-main mt">
    <div class="card">
        <div class="card-head"><h2>Drawer movements</h2><a class="small" href="{{ route('pos.cash-movements', ['shift' => $shift->id]) }}">Open ledger</a></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Time</th><th>Type</th><th>Reason / reference</th><th>By</th><th class="num">Amount</th></tr></thead>
            <tbody>@foreach ($shift->movements as $m)
                <tr><td class="small nowrap">{{ $m->created_at->format('d M H:i') }}</td><td class="nowrap"><x-icon :name="$m->icon()" /> {{ $m->typeLabel() }}</td>
                    <td>{{ $m->reason }}@if($m->reference)<span class="small muted"> · {{ $m->reference }}</span>@endif</td><td class="small">{{ $m->user?->name }}</td>
                    <td class="num" style="color:{{ $m->amount < 0 ? 'var(--crit)' : 'inherit' }}">{{ money($m->amount, null, false) }}</td></tr>
            @endforeach</tbody>
        </table></div>
    </div>
    <div class="stack">
        @if ($shift->notes)<div class="card"><div class="card-head"><h2>Notes</h2></div><div class="card-body small" style="white-space:pre-line">{{ $shift->notes }}</div></div>@endif
        @if ($shift->review_notes)<div class="card"><div class="card-head"><h2>Manager review</h2></div><div class="card-body small">{{ $shift->review_notes }}<br><span class="muted">{{ $shift->reviewer?->name }} · {{ fmt_dt($shift->reviewed_at) }}</span></div></div>@endif
        @if ($shift->status === 'closed')
            @perm('pos.shift_review')
            <form method="post" action="{{ route('pos.shifts.review', $shift) }}" class="card">
                @csrf
                <div class="card-head"><h2><x-icon name="eye" /> Manager review</h2></div>
                <div class="card-body form-grid">
                    <x-select name="review_status" label="Decision" :options="['approved' => 'Approve — count accepted', 'flagged' => 'Flag — follow up variance']" col="f-12" />
                    <x-textarea name="review_notes" label="Notes (required when flagging)" rows="2" />
                </div>
                <div class="card-foot"><button class="btn btn-primary" type="submit">Save review</button></div>
            </form>
            <form method="post" action="{{ route('pos.shifts.reopen', $shift) }}" class="card" data-confirm="Reopen this closed shift? The cashier must count and close it again. This is recorded in the audit log." data-danger>
                @csrf
                <div class="card-head"><h2><x-icon name="refresh" /> Reopen shift</h2></div>
                <div class="card-body form-grid"><x-input name="reason" label="Reason" required col="f-12" placeholder="e.g. Missed card slip, recount required" /></div>
                <div class="card-foot"><button class="btn btn-danger" type="submit">Reopen</button></div>
            </form>
            @endperm
        @endif
    </div>
</div>
@endsection
