@extends('layouts.print')
@section('title', 'Statement '.$op->company_name)
@section('content')
<div class="print-page">
    @include('print.partials.letterhead', ['title' => 'Statement of Account', 'meta' => e($op->company_name).'<br>'.fmt_date($s['from']).' – '.fmt_date($s['to'])])
    <div class="doc-grid">
        <div class="doc-box"><strong>{{ $op->company_name }}</strong><br>{{ $op->legal_name }}<br>{{ $op->address }}<br>{{ $op->email }}</div>
        <div class="doc-box"><strong>Account</strong><br>Code {{ $op->code }}<br>Payment terms {{ $op->payment_terms_days }} days<br>Credit limit {{ money($op->credit_limit) }}</div>
    </div>
    <table>
        <thead><tr><th>Date</th><th>Reference</th><th>Type</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead>
        <tbody>
        @forelse ($s['lines'] as $l)
            <tr><td>{{ fmt_date($l['date']) }}</td><td>{{ $l['ref'] }}</td><td>{{ $l['type'] }}</td><td class="num">{{ $l['debit'] ? money($l['debit'], null, false) : '' }}</td>
                <td class="num">{{ $l['credit'] ? money($l['credit'], null, false) : '' }}</td><td class="num">{{ money($l['balance'], null, false) }}</td></tr>
        @empty
            <tr><td colspan="6">No transactions in this period.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="doc-totals">
        @foreach ($s['ageing'] as $b => $amt)<div><span>{{ $b }} days</span><span>{{ money($amt) }}</span></div>@endforeach
        <div class="grand"><span>Total outstanding</span><span>{{ money($s['outstanding']) }}</span></div>
    </div>
    <div class="doc-foot">Please remit to {{ property()->legal_name }}. Quote invoice numbers with your payment. Queries: {{ setting('contact_email') }}.</div>
</div>
@endsection
