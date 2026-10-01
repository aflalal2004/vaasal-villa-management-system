@extends('layouts.site')
@section('title', 'Confirming payment')
@section('header_class', 'always')
@push('head')<meta http-equiv="refresh" content="5">@endpush
@section('content')
<section class="block" style="padding-top:170px"><div class="wrap center">
    <span class="eyebrow">Almost there</span>
    <h1 style="font-size:40px">We're confirming your payment…</h1>
    <p class="lead" style="margin-top:14px">Booking {{ $intent->booking->reference }}. This page refreshes automatically; your confirmation email follows as soon as the payment provider notifies us.</p>
    <div class="skeleton" style="max-width:380px;height:10px;margin:30px auto"></div>
    <a class="btn" href="{{ route('manage.show', $intent->booking->manage_token) }}">View booking</a>
</div></section>
@endsection
