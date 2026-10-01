@extends('layouts.pos')
@section('title', 'Cashier shifts')
@section('content')
<x-page-header title="Cashier shifts & drawer" sub="Open a shift with a float before taking cash, card, bank or digital payments. Close it through Day-end closing.">
    @if ($pendingReview)<a class="btn" href="{{ route('pos.shifts.index', ['review' => 'pending']) }}"><x-icon name="eye" /> {{ $pendingReview }} awaiting review</a>@endif
</x-page-header>
@include('pos.partials.cash-tabs')

<div class="grid cols-main mb">
    <div class="stack">
        @forelse ($mine as $s)
            @php $r = $reports[$s->id]; @endphp
            <div class="card">
                <div class="card-head"><h2><x-icon name="drawer" /> Open shift · {{ $s->outlet->name }}</h2><span class="small muted">since {{ fmt_dt($s->opened_at) }}</span></div>
                <div class="card-body">
                    <div class="stats" style="margin-bottom:14px">
                        <x-stat label="Opening float" :value="money($r['opening_float'])" />
                        <x-stat label="Cash sales" :value="money($r['cash_sales'])" />
                        <x-stat label="Expected in drawer" :value="money($r['expected_cash'])" class="accent" />
                        <x-stat label="All tenders" :value="money($r['grand_total'])" :hint="$r['orders'].' check(s)'" />
                    </div>
                    <form method="post" action="{{ route('pos.shifts.cash', $s) }}" class="form-grid">
                        @csrf
                        @php
                            $types = collect(\App\Models\CashMovement::TYPES)->only(\App\Models\CashMovement::MANUAL)->map(fn ($t) => $t[0]);
                            if (! auth()->user()->hasPermission('pos.shift_review')) $types->forget('adjustment');
                        @endphp
                        <x-select name="type" label="Drawer movement" :options="$types->all()" col="f-4" :id="'t'.$s->id" />
                        <x-input name="amount" type="number" step="0.01" label="Amount (LKR)" required col="f-4" :id="'a'.$s->id" />
                        <x-input name="reference" label="Reference" col="f-4" :id="'ref'.$s->id" placeholder="Slip / voucher no." />
                        <x-input name="reason" label="Reason" required col="f-12" :id="'r'.$s->id" placeholder="e.g. Ice purchase, change from bank, safe drop" />
                        <div class="f-12 row">
                            <button class="btn" type="submit"><x-icon name="plus" /> Record movement</button>
                            <a class="btn btn-ghost" href="{{ route('pos.shifts.show', $s) }}"><x-icon name="file" /> X report</a>
                            <span class="spacer"></span>
                            <a class="btn btn-primary" href="{{ route('pos.day-end', ['shift' => $s->id]) }}"><x-icon name="lock" /> Day-end closing</a>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            @perm('pos.shift')
            <form method="post" action="{{ route('pos.shifts.open') }}" class="card">
                @csrf
                <div class="card-head"><h2><x-icon name="coins" /> Open a shift</h2></div>
                <div class="card-body form-grid">
                    <x-select name="outlet_id" label="Outlet" :options="$outlets" col="f-6" required />
                    <x-input name="opening_float" type="number" step="0.01" min="0" label="Opening float (LKR)" value="10000" required col="f-6" />
                    <x-input name="notes" label="Notes" col="f-12" />
                </div>
                <div class="card-foot"><button class="btn btn-primary" type="submit">Open shift</button></div>
            </form>
            @else
            <x-empty title="No shift of your own" icon="drawer">Managers review cashier shifts below and in Today.</x-empty>
            @endperm
        @endforelse
    </div>
    <div class="card" style="align-self:start"><div class="card-body small">
        <strong>How cash control works</strong>
        <ul style="padding-left:18px;margin:6px 0 0;display:grid;gap:4px">
            <li>Expected cash = float + cash sales + cash in − cash out − cash refunds − bank deposits ± adjustments.</li>
            <li>Card, bank transfer and digital payments are totalled separately — they never enter the drawer.</li>
            <li>At close the counted cash and variance are stored and the shift is locked. Variances of {{ money(1) }} or more alert managers.</li>
            <li>Only a manager can post adjustments, review a closed shift or reopen it (with a reason, audited).</li>
        </ul>
    </div></div>
</div>

<div class="card">
    <div class="card-head"><h2>Shift history</h2>
        <form class="row" method="get" style="gap:6px">
            <select name="status" aria-label="Status" data-autosubmit><option value="">All statuses</option>@foreach (['open' => 'Open', 'closed' => 'Closed'] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select>
            <select name="review" aria-label="Review" data-autosubmit><option value="">Any review</option>@foreach (['pending' => 'Awaiting review', 'approved' => 'Approved', 'flagged' => 'Flagged'] as $k => $v)<option value="{{ $k }}" @selected(request('review') === $k)>{{ $v }}</option>@endforeach</select>
            <input type="date" name="date" value="{{ request('date') }}" aria-label="Date" data-autosubmit>
        </form>
    </div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Opened</th><th>Outlet</th><th>Cashier</th><th>Status</th><th>Review</th><th class="num">Float</th><th class="num">Expected</th><th class="num">Counted</th><th class="num">Variance</th><th></th></tr></thead>
        <tbody>@forelse ($shifts as $s)
            <tr><td><a href="{{ route('pos.shifts.show', $s) }}">{{ fmt_dt($s->opened_at) }}</a></td><td>{{ $s->outlet->name }}</td><td>{{ $s->user->name }}</td>
                <td><x-badge :status="$s->status === 'open' ? 'active' : 'neutral'" :label="$s->status === 'open' ? 'Open' : 'Closed · locked'" /></td>
                <td>@if ($s->status === 'closed')<x-badge :tone="['approved' => 'success', 'flagged' => 'danger'][$s->review_status] ?? 'warning'" :label="['approved' => 'Approved', 'flagged' => 'Flagged'][$s->review_status] ?? 'Pending'" />@else<span class="muted">—</span>@endif</td>
                <td class="num">{{ money($s->opening_float, null, false) }}</td><td class="num">{{ $s->expected_cash !== null ? money($s->expected_cash, null, false) : '—' }}</td>
                <td class="num">{{ $s->counted_cash !== null ? money($s->counted_cash, null, false) : '—' }}</td>
                <td class="num" style="color:{{ $s->variance !== null && abs($s->variance) >= 1 ? 'var(--crit)' : 'inherit' }}">{{ $s->variance !== null ? money($s->variance, null, false) : '—' }}</td>
                <td class="nowrap"><a class="btn btn-sm btn-ghost" href="{{ route('pos.shifts.print', $s) }}" target="_blank" aria-label="Print report"><x-icon name="printer" /></a></td></tr>
        @empty
            <tr><td colspan="10"><x-empty title="No shifts found" icon="drawer" /></td></tr>
        @endforelse</tbody>
    </table></div>
    {{ $shifts->links() }}
</div>
@endsection
