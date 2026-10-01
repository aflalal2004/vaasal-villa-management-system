@extends('layouts.operator')
@section('title', 'Statement')
@section('content')
<x-page-header title="Statement of account" :sub="fmt_date($s['from']).' – '.fmt_date($s['to'])">
    <form method="get" class="row"><input type="date" name="from" value="{{ $s['from'] }}" aria-label="From"><input type="date" name="to" value="{{ $s['to'] }}" aria-label="To"><button class="btn" type="submit">Show</button></form>
</x-page-header>
<div class="stats">
    @foreach ($s['ageing'] as $b => $a)<x-stat :label="$b.' days'" :value="money($a)" />@endforeach
    <x-stat label="Total outstanding" :value="money($s['outstanding'])" class="accent" />
</div>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Reference</th><th>Type</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead>
        <tbody>
        @forelse ($s['lines'] as $l)
            <tr><td>{{ fmt_date($l['date']) }}</td><td class="mono">{{ $l['ref'] }}</td><td>{{ $l['type'] }}</td><td class="num">{{ $l['debit'] ? money($l['debit']) : '' }}</td><td class="num">{{ $l['credit'] ? money($l['credit']) : '' }}</td><td class="num">{{ money($l['balance']) }}</td></tr>
        @empty
            <tr><td colspan="6" class="muted">No transactions in this period.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
