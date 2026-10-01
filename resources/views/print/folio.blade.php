@extends('layouts.print')
@section('title', 'Folio '.$folio->folio_no)
@section('content')
<div class="print-page">
    @include('print.partials.letterhead', ['title' => 'Guest Folio', 'meta' => 'Folio <strong>'.$folio->folio_no.'</strong><br>Booking '.$folio->booking->reference.'<br>'.ucfirst($folio->status).' · printed '.now()->format('d M Y H:i')])
    <div class="doc-grid">
        <div class="doc-box"><strong>Account</strong><br>{{ $folio->payerName() }}</div>
        <div class="doc-box"><strong>Stay</strong><br>{{ fmt_date($folio->booking->arrival) }} → {{ fmt_date($folio->booking->departure) }}<br>Villa {{ $folio->booking->villas->pluck('villa.code')->implode(', ') }}</div>
    </div>
    <table>
        <thead><tr><th>Date</th><th>Department</th><th>Description</th><th class="num">Charges</th><th class="num">Credits</th></tr></thead>
        <tbody>
        @foreach ($folio->lines as $l)
            <tr><td>{{ fmt_date($l->business_date, 'd M') }}</td><td>{{ $l->departmentLabel() }}</td><td>{{ $l->description }}</td><td class="num">{{ money($l->total, null, false) }}</td><td></td></tr>
        @endforeach
        @foreach ($folio->payments as $pmt)
            <tr><td>{{ fmt_date($pmt->paid_at, 'd M') }}</td><td>{{ ucfirst($pmt->type) }}</td><td>{{ $pmt->methodLabel() }} {{ $pmt->reference }}</td><td></td><td class="num">{{ money($pmt->amount, null, false) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <div class="doc-totals">
        @foreach ($s['by_department'] as $dept => $amt)<div><span>{{ config('vaasal.departments.'.$dept, $dept) }}</span><span>{{ money($amt) }}</span></div>@endforeach
        <div class="grand"><span>Total charges</span><span>{{ money($s['charges']) }}</span></div>
        <div><span>Payments</span><span>{{ money($s['paid']) }}</span></div>
        <div><span><strong>Balance</strong></span><span><strong>{{ money($s['balance']) }}</strong></span></div>
    </div>
</div>
@endsection
