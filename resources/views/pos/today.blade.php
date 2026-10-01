@extends('layouts.pos')
@section('title', 'Today · cash & bank')
@section('content')
<x-page-header :title="($date->isToday() ? 'Today' : fmt_date($date, 'D d M Y')).' · cash & bank'"
    :sub="$scope === 'all' ? 'All cashiers and outlets. Live from POS payments and drawer movements.' : 'Your own shift and payments. Live from POS payments and drawer movements.'">
    <form class="row" method="get" style="gap:6px">
        <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" aria-label="Date" data-autosubmit>
        @if ($scope === 'all')<select name="outlet" aria-label="Outlet" data-autosubmit><option value="">All outlets</option>@foreach ($outlets as $id => $name)<option value="{{ $id }}" @selected(request('outlet') == $id)>{{ $name }}</option>@endforeach</select>@endif
    </form>
</x-page-header>
@include('pos.partials.cash-tabs')

<div class="stats today-stats" data-stagger-admin>
    <x-stat label="Today's sales" :value="money($totals['sales'])" hint="All tenders, before refunds" class="accent" />
    <x-stat label="Cash" :value="money($totals['cash'])" hint="Net of cash refunds" />
    <x-stat label="Card" :value="money($totals['card'])" />
    <x-stat label="Bank transfer" :value="money($totals['bank'])" />
    <x-stat label="Online / digital" :value="money($totals['digital'])" />
    <x-stat label="Refunds" :value="money($totals['refunds'])" />
    <x-stat label="Room charges" :value="money($totals['room_charges'])" hint="Posted to guest folios" />
    <x-stat label="Today's total" :value="money($totals['total'])" hint="Sales − refunds" />
</div>

<div class="grid cols-2 mb">
    <div class="card">
        <div class="card-head"><h2><x-icon name="drawer" /> Cash drawer</h2></div>
        <div class="card-body">
            <div class="recon">
                <div class="recon-row total"><span>Cash currently in drawer{{ $drawer['open_shifts'] > 1 ? 's' : '' }} ({{ $drawer['open_shifts'] }} open)</span><span>{{ money($drawer['in_drawer']) }}</span></div>
                <div class="recon-row"><span>Expected cash — closed shifts</span><span>{{ money($drawer['expected_closed']) }}</span></div>
                <div class="recon-row"><span>Counted cash — closed shifts</span><span>{{ money($drawer['counted_closed']) }}</span></div>
                <div class="recon-row total" style="color:{{ abs($drawer['variance']) >= 1 ? 'var(--crit)' : 'var(--ok)' }}"><span>Difference (variance)</span><span>{{ $drawer['variance'] > 0 ? '+' : '' }}{{ money($drawer['variance']) }}</span></div>
                <div class="recon-row minus"><span>Today's bank deposits / safe drops</span><span>{{ money($drawer['deposits']) }}</span></div>
            </div>
            @if ($mine)
                <div class="row mt"><a class="btn" href="{{ route('pos.shifts.show', $mine) }}"><x-icon name="file" /> My X report</a><a class="btn btn-primary" href="{{ route('pos.day-end', ['shift' => $mine->id]) }}"><x-icon name="lock" /> Day-end closing</a></div>
            @endif
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2><x-icon name="wallet" /> By payment method</h2></div>
        <div class="card-body">
            @php $max = max(1, collect($by_method)->map(fn ($v) => abs($v))->max()); @endphp
            <div class="method-bars">
                @foreach ($by_method as $m => $amt)
                    <div class="method-bar"><span class="mb-label"><x-icon :name="\App\Models\PosPayment::ICONS[$m] ?? 'cash'" /> {{ \App\Models\PosPayment::label($m) }}</span>
                        <span class="mb-track"><span style="width:{{ round(abs($amt) / $max * 100) }}%"></span></span><span class="mb-val">{{ money($amt, null, false) }}</span></div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head"><h2>{{ $scope === 'all' ? 'Cashier shifts' : 'My shifts' }}</h2></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Cashier</th><th>Outlet</th><th>Status</th><th class="num">Float</th><th class="num">Cash sales</th><th class="num">Card</th><th class="num">Bank</th><th class="num">Digital</th><th class="num">Expected</th><th class="num">Counted</th><th class="num">Variance</th></tr></thead>
        <tbody>
        @forelse ($shifts as $x)
            @php $s = $x['shift']; $r = $x['r']; @endphp
            <tr><td><a href="{{ route('pos.shifts.show', $s) }}">{{ $s->user->name }}</a><div class="small muted">{{ fmt_dt($s->opened_at) }}</div></td><td>{{ $s->outlet->name }}</td>
                <td><x-badge :status="$s->status === 'open' ? 'active' : 'neutral'" :label="ucfirst($s->status)" /></td>
                <td class="num">{{ money($r['opening_float'], null, false) }}</td><td class="num">{{ money($r['cash_sales'], null, false) }}</td><td class="num">{{ money($r['card_total'], null, false) }}</td>
                <td class="num">{{ money($r['bank_total'], null, false) }}</td><td class="num">{{ money($r['digital_total'], null, false) }}</td><td class="num">{{ money($r['expected_cash'], null, false) }}</td>
                <td class="num">{{ $r['counted_cash'] !== null ? money($r['counted_cash'], null, false) : '—' }}</td>
                <td class="num" style="color:{{ $r['variance'] !== null && abs($r['variance']) >= 1 ? 'var(--crit)' : 'inherit' }}">{{ $r['variance'] !== null ? money($r['variance'], null, false) : '—' }}</td></tr>
        @empty
            <tr><td colspan="11"><x-empty title="No shifts for this day" icon="drawer" /></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
