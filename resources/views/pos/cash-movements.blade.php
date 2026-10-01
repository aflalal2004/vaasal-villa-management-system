@extends('layouts.pos')
@section('title', 'Cash movements')
@section('content')
<x-page-header title="Cash movements" sub="Every change to a cash drawer: opening floats, cash sales, cash in, cash out, refunds, bank deposits and adjustments. Adjustments are audited." />
@include('pos.partials.cash-tabs')

<div class="grid cols-main mb">
    <div class="stack">
        <div class="stats">
            @foreach (\App\Models\CashMovement::TYPES as $type => [$label, $sign, $icon])
                @continue(! isset($totals[$type]))
                <x-stat :label="$label" :value="money($totals[$type]->total)" :hint="$totals[$type]->n.' entr'.($totals[$type]->n == 1 ? 'y' : 'ies')" />
            @endforeach
        </div>
        <div class="card">
            <div class="card-head"><h2>Ledger</h2>
                <form class="row" method="get" style="gap:6px">
                    <select name="type" aria-label="Type" data-autosubmit><option value="">All types</option>@foreach (\App\Models\CashMovement::TYPES as $k => $t)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $t[0] }}</option>@endforeach</select>
                    @if ($cashiers->isNotEmpty())<select name="user" aria-label="Cashier" data-autosubmit><option value="">All cashiers</option>@foreach ($cashiers as $id => $n)<option value="{{ $id }}" @selected(request('user') == $id)>{{ $n }}</option>@endforeach</select>@endif
                    <input type="date" name="from" value="{{ request('from') }}" aria-label="From" data-autosubmit>
                    <input type="date" name="to" value="{{ request('to') }}" aria-label="To" data-autosubmit>
                    @if (request()->hasAny(['type', 'user', 'from', 'to', 'shift']))<a class="btn btn-sm btn-ghost" href="{{ route('pos.cash-movements') }}">Reset</a>@endif
                </form>
            </div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Date</th><th>Time</th><th>Type</th><th>Cashier / shift</th><th>Recorded by</th><th>Reason</th><th>Reference</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @forelse ($movements as $m)
                    <tr>
                        <td class="nowrap">{{ $m->created_at->format('d M Y') }}</td><td class="nowrap small">{{ $m->created_at->format('H:i') }}</td>
                        <td class="nowrap"><x-icon :name="$m->icon()" /> {{ $m->typeLabel() }}</td>
                        <td><a href="{{ route('pos.shifts.show', $m->pos_shift_id) }}">{{ $m->shift?->user?->name }}</a><div class="small muted">{{ $m->shift?->outlet?->name }} · #{{ $m->pos_shift_id }}</div></td>
                        <td class="small">{{ $m->user?->name }}</td>
                        <td>{{ $m->reason }}@if($m->notes)<div class="small muted">{{ $m->notes }}</div>@endif</td>
                        <td class="small">{{ $m->reference ?? ($m->order?->invoice_no ?? $m->order?->order_no) }}</td>
                        <td class="num" style="color:{{ $m->amount < 0 ? 'var(--crit)' : 'inherit' }}">{{ money($m->amount, null, false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty title="No cash movements" icon="coins">Nothing matches these filters.</x-empty></td></tr>
                @endforelse
                </tbody>
            </table></div>
            {{ $movements->links() }}
        </div>
    </div>
    <div class="stack" style="align-self:start">
        @if ($mine)
        <form method="post" action="{{ route('pos.shifts.cash', $mine) }}" class="card">
            @csrf
            <div class="card-head"><h2><x-icon name="plus" /> Record a movement</h2></div>
            <div class="card-body form-grid">
                @php
                    $types = collect(\App\Models\CashMovement::TYPES)->only(\App\Models\CashMovement::MANUAL)->map(fn ($t) => $t[0]);
                    if (! auth()->user()->hasPermission('pos.shift_review')) $types->forget('adjustment');
                @endphp
                <x-select name="type" label="Type" :options="$types->all()" col="f-12" />
                <x-input name="amount" type="number" step="0.01" label="Amount (LKR)" required col="f-12" help="Adjustments: use a negative amount to remove cash." />
                <x-input name="reason" label="Reason" required col="f-12" />
                <x-input name="reference" label="Reference" col="f-12" placeholder="Deposit slip / voucher no." />
                <x-textarea name="notes" label="Notes" rows="2" />
            </div>
            <div class="card-foot"><span class="small muted">Shift #{{ $mine->id }} · {{ $mine->outlet->name }}</span><button class="btn btn-primary" type="submit">Record</button></div>
        </form>
        @else
            <div class="card"><div class="card-body small muted">Open a shift to record cash in, cash out or bank deposits.</div></div>
        @endif
    </div>
</div>
@endsection
