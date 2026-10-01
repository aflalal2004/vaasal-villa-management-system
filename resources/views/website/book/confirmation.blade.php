@extends('layouts.site')
@section('title', $b->status === 'confirmed' ? 'Booking confirmed' : 'Booking '.$b->reference)
@section('header_class', 'always')
@section('content')
<section class="block" style="padding-top:155px">
    <div class="wrap">
        <div class="steps"><span>1 · Choose villa</span><span>2 · Your details</span><span>3 · Secure payment</span><span class="on">4 · Confirmation</span></div>
        <div class="detail-layout">
            <div class="form-card">
                @if ($b->status === 'confirmed')
                    <span class="eyebrow">Confirmed · {{ $b->reference }}</span>
                    <h1 style="font-size:clamp(32px,4vw,48px)">Thank you, {{ $b->guest->first_name }}. See you soon.</h1>
                    <p class="lead" style="margin-top:14px">A confirmation, receipt and voucher are on their way to {{ $b->guest->email }}.</p>
                @else
                    <span class="eyebrow">{{ $b->statusLabel() }} · {{ $b->reference }}</span>
                    <h1 style="font-size:36px">Your booking is {{ strtolower($b->statusLabel()) }}.</h1>
                @endif
                <div class="summary" style="margin-top:24px">
                    <span class="muted">Arrival</span><strong>{{ fmt_date($b->arrival, 'l d F Y') }} from {{ substr(property()->check_in_time, 0, 5) }}</strong>
                    <span class="muted">Departure</span><strong>{{ fmt_date($b->departure, 'l d F Y') }} by {{ substr(property()->check_out_time, 0, 5) }}</strong>
                    @foreach ($b->villas as $bv)<span class="muted">Villa</span><strong>{{ $bv->villa->type->name }}</strong>@endforeach
                    <span class="muted">Rate</span><span>{{ $b->ratePlan?->name }}</span>
                    <span class="muted">Total</span><strong><x-price :amount="$b->grand_total" mode="charge" /></strong>
                    <span class="muted">Paid</span><span>{{ money($paid) }}</span>
                    <span class="total">Balance at the villa</span><span class="total"><x-price :amount="max(0, $b->grand_total - $paid)" mode="charge" /></span>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:26px">
                    <a class="btn btn-primary" href="{{ route('manage.voucher', $b->manage_token) }}" target="_blank"><x-icon name="file" /> Voucher</a>
                    <a class="btn" href="{{ route('manage.show', $b->manage_token) }}">Manage booking</a>
                    <a class="btn" href="{{ whatsapp_link('Hello! My booking reference is '.$b->reference.'. I would like to arrange an airport transfer.') }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Arrange a transfer</a>
                </div>
            </div>
            <aside class="sticky-book">
                @php $bv = $b->villas->first(); @endphp
                <img src="{{ $bv?->villa->type->coverUrl() }}" alt="" style="border-radius:10px;aspect-ratio:16/10;object-fit:cover;width:100%" data-fallback="{{ asset('assets/img/placeholder-villa.svg') }}">
                <p class="small" style="margin:0">Keep your reference <strong>{{ $b->reference }}</strong>. You can view this booking any time from “Manage my booking” with your email.</p>
                <div class="support-box">
                    <strong>Need help with this booking?</strong>
                    <a href="{{ whatsapp_link('Hello Vaasal Villa, my booking reference is '.$b->reference.'.') }}" target="_blank" rel="noopener"><x-icon name="whatsapp-fill" /> WhatsApp {{ contact('whatsapp_display') }}</a>
                    <a href="tel:{{ contact('phone_link') }}"><x-icon name="phone" /> {{ contact('phone') }}</a>
                    <a href="mailto:{{ contact('email') }}?subject={{ rawurlencode('Booking '.$b->reference) }}"><x-icon name="mail" /> {{ contact('email') }}</a>
                    <span class="small muted"><x-icon name="pin" /> {{ contact('location') }} · amounts are charged in LKR</span>
                </div>
            </aside>
        </div>
    </div>
</section>
@endsection
