@extends('layouts.print')
@section('title', $inv->typeLabel().' '.$inv->number)
@section('content')
@if (auth()->user()?->isStaff())
<div class="print-toolbar no-print" style="margin-top:0">
    @if ($inv->booking)<a class="btn" href="{{ route('admin.bookings.show', $inv->booking) }}">Open booking</a>@endif
    <form method="post" action="{{ route('admin.invoices.email', $inv) }}" class="row">
        @csrf
        <input type="email" name="email" value="{{ $inv->operator?->email ?? $inv->booking?->guest?->email }}" placeholder="Email address" required style="width:240px">
        <button class="btn" type="submit"><x-icon name="mail" /> Email</button>
    </form>
</div>
@endif
<div class="print-page">
    @include('print.partials.invoice-body')
</div>
@endsection
