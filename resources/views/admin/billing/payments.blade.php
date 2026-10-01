@extends('layouts.admin')
@section('title', 'Payments')
@section('content')
<x-page-header title="Payments" sub="Deposits, settlements, refunds and operator remittances. Online payments are completed by the gateway — card data never touches this server." />
<div class="stats">
    @foreach ($totals as $t)
        <x-stat :label="\App\Models\Payment::METHODS[$t->method] ?? $t->method" :value="money($t->total)" :hint="$t->n.' transaction(s)'" />
    @endforeach
</div>
<form class="filters" method="get">
    <div class="field" style="flex:2 1 200px"><label for="q">Reference / booking</label><input id="q" type="search" name="q" value="{{ request('q') }}"></div>
    <div class="field"><label for="method">Method</label><select id="method" name="method" data-autosubmit><option value="">All</option>
        @foreach (\App\Models\Payment::METHODS as $k => $v)<option value="{{ $k }}" @selected(request('method') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="field"><label for="type">Type</label><select id="type" name="type" data-autosubmit><option value="">All</option>
        @foreach (['payment', 'deposit', 'refund'] as $k)<option value="{{ $k }}" @selected(request('type') === $k)>{{ ucfirst($k) }}</option>@endforeach</select></div>
    <div class="field"><label for="from">From</label><input id="from" type="date" name="from" value="{{ request('from') }}"></div>
    <div class="field"><label for="to">To</label><input id="to" type="date" name="to" value="{{ request('to') }}"></div>
    <button class="btn" type="submit">Apply</button>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Reference</th><th>Date</th><th>Payer</th><th>Booking</th><th>Type</th><th>Method</th><th>Received by</th><th class="num">Amount</th><th></th></tr></thead>
        <tbody>
        @forelse ($payments as $p)
            <tr>
                <td class="mono">{{ $p->reference }}@if($p->gateway_ref)<div class="small muted">{{ $p->gateway }} · {{ \Illuminate\Support\Str::limit($p->gateway_ref, 18) }}</div>@endif</td>
                <td class="nowrap">{{ fmt_dt($p->paid_at) }}</td>
                <td>{{ $p->operator?->company_name ?? $p->booking?->guest?->fullName() ?? '—' }}</td>
                <td>@if($p->booking)<a class="mono" href="{{ route('admin.bookings.show', $p->booking) }}">{{ $p->booking->reference }}</a>@else — @endif</td>
                <td><x-badge :tone="$p->type === 'refund' ? 'danger' : ($p->type === 'deposit' ? 'info' : 'success')" :label="ucfirst($p->type)" /></td>
                <td>{{ $p->methodLabel() }}</td>
                <td class="small">{{ $p->receiver?->name ?? 'Online' }}</td>
                <td class="num" style="color:{{ $p->amount < 0 ? 'var(--crit)' : 'inherit' }}">{{ money($p->amount) }}</td>
                <td class="actions">
                    @if($p->invoice_id)<a class="btn btn-sm btn-ghost" href="{{ route('admin.invoices.show', $p->invoice_id) }}" target="_blank" title="Receipt / invoice"><x-icon name="file" /></a>@endif
                    @if($p->proof_path)<a class="btn btn-sm btn-ghost" href="{{ route('admin.payments.proof', $p) }}" target="_blank" title="Payment proof"><x-icon name="eye" /></a>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9"><x-empty title="No payments found" icon="card" /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $payments->links() }}
</div>
@endsection
