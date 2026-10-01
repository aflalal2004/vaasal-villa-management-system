@extends('layouts.print')
@section('title', 'Confirmation '.$b->reference)
@section('content')
<div class="print-page">
    @include('print.partials.letterhead', ['title' => 'Booking Confirmation', 'meta' => 'Ref <strong>'.$b->reference.'</strong><br>'.now()->format('d M Y')])
    <p>Dear {{ $b->guest->first_name }},</p>
    <p>Thank you for choosing {{ property()->name }}. We are pleased to confirm your reservation{{ $b->status !== 'confirmed' ? ' (status: '.$b->statusLabel().')' : '' }}.</p>
    <div class="doc-grid">
        <div class="doc-box"><strong>Arrival</strong><br>{{ fmt_date($b->arrival, 'l, d F Y') }}<br>Check-in from {{ substr(property()->check_in_time, 0, 5) }}</div>
        <div class="doc-box"><strong>Departure</strong><br>{{ fmt_date($b->departure, 'l, d F Y') }}<br>Check-out by {{ substr(property()->check_out_time, 0, 5) }}</div>
    </div>
    <table>
        <thead><tr><th>Villa</th><th>Guests</th><th class="num">Nights</th><th class="num">Amount</th></tr></thead>
        <tbody>
        @foreach ($b->villas->where('status', 'active') as $bv)
            <tr><td>{{ $bv->villa->type->name }} — {{ $bv->villa->name }}</td><td>{{ $bv->adults }}A {{ $bv->children }}C</td><td class="num">{{ count($bv->nightly_rates) }}</td><td class="num">{{ money($bv->total) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <div class="doc-totals">
        @if ($b->discount_total > 0)<div><span>Discount{{ $b->offer ? ' ('.$b->offer->title.')' : '' }}</span><span>− {{ money($b->discount_total) }}</span></div>@endif
        <div><span>Service charge</span><span>{{ money($b->service_total) }}</span></div>
        <div><span>Tax</span><span>{{ money($b->tax_total) }}</span></div>
        <div class="grand"><span>Total</span><span>{{ money($b->grand_total) }}</span></div>
        <div><span>Paid to date</span><span>{{ money($paid) }}</span></div>
        <div><span><strong>Balance due</strong></span><span><strong>{{ money(max(0, $b->grand_total - $paid)) }}</strong></span></div>
    </div>
    <p style="margin-top:18px"><strong>Rate:</strong> {{ $b->ratePlan?->name }} — {{ $b->ratePlan?->description }}</p>
    @if ($b->special_requests)<p><strong>Your requests:</strong> {{ $b->special_requests }}</p>@endif
    <p>Manage your booking online: {{ route('manage.show', $b->manage_token) }}</p>
    <div class="doc-foot">We look forward to welcoming you. — Reservations, {{ property()->name }} · {{ setting('contact_email') }} · WhatsApp {{ setting('contact_phone') }}</div>
</div>
@endsection
