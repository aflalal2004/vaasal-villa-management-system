@extends('layouts.site')
@section('title', 'Payment not completed')
@section('header_class', 'always')
@section('content')
<section class="block" style="padding-top:170px"><div class="wrap center">
    <span class="eyebrow">Payment not completed</span>
    <h1 style="font-size:40px">Your villa is still held — for a few more minutes.</h1>
    <p class="lead" style="margin-top:14px">The payment was cancelled or declined. You can try again now; if the hold lapses the villa is released automatically.</p>
    <form method="post" action="{{ route('manage.pay', $intent->booking->manage_token) }}" style="margin-top:26px" data-once>@csrf
        <input type="hidden" name="intent" value="{{ $intent->status === 'pending' ? $intent->token : '' }}">
        <button class="btn btn-primary" type="submit">Try payment again</button>
        <a class="btn" href="{{ whatsapp_link('Hi, I had a problem paying for booking '.$intent->booking->reference) }}" target="_blank" rel="noopener">Get help on WhatsApp</a>
    </form>
</div></section>
@endsection
