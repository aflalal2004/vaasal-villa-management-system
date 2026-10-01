@extends('layouts.admin')
@section('title', 'Invoices & receipts')
@section('content')
<x-page-header title="Invoices & receipts" sub="Numbered, immutable documents. Every re-print is marked as a copy." />
<form class="filters" method="get">
    <div class="field" style="flex:2 1 200px"><label for="q">Number or name</label><input id="q" type="search" name="q" value="{{ request('q') }}"></div>
    <div class="field"><label for="type">Type</label><select id="type" name="type" data-autosubmit><option value="">All types</option>
        @foreach (\App\Models\Invoice::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $k === 'operator_invoice' ? 'Operator invoice' : $v }}</option>@endforeach</select></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status" data-autosubmit><option value="">Any</option>
        @foreach (['issued', 'partially_paid', 'paid', 'void'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ label($s) }}</option>@endforeach</select></div>
    <div class="field"><label for="from">From</label><input id="from" type="date" name="from" value="{{ request('from') }}"></div>
    <div class="field"><label for="to">To</label><input id="to" type="date" name="to" value="{{ request('to') }}"></div>
    <button class="btn" type="submit">Apply</button>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Number</th><th>Type</th><th>Issued</th><th>Bill to</th><th>Booking</th><th>Status</th><th class="num">Total</th><th class="num">Balance</th></tr></thead>
        <tbody>
        @forelse ($invoices as $i)
            <tr>
                <td><a class="mono" href="{{ route('admin.invoices.show', $i) }}" target="_blank">{{ $i->number }}</a></td>
                <td>{{ $i->type === 'operator_invoice' ? 'Operator invoice' : $i->typeLabel() }}</td>
                <td class="nowrap">{{ fmt_date($i->issued_at) }}</td>
                <td>{{ $i->bill_to_name }}</td>
                <td>@if($i->booking)<a href="{{ route('admin.bookings.show', $i->booking) }}" class="mono">{{ $i->booking->reference }}</a>@else — @endif</td>
                <td><x-badge :status="$i->status" /></td>
                <td class="num">{{ money($i->grand_total) }}</td>
                <td class="num">{{ money($i->balance) }}</td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty title="No documents found" icon="file" /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $invoices->links() }}
</div>
@endsection
