@extends('layouts.site')
@section('title', 'Booking '.$b->reference)
@section('header_class', 'always')
@section('content')
@php $balance = max(0, round($b->grand_total - $paid, 2)); @endphp
<section class="block" style="padding-top:155px">
    <div class="wrap detail-layout">
        <div class="form-card">
            <span class="eyebrow">{{ $b->statusLabel() }} · {{ $b->reference }}</span>
            <h1 style="font-size:clamp(30px,4vw,44px)">{{ $b->guest->fullName() }}</h1>
            @if ($pending)<div class="alert alert-info" style="margin-top:16px">A payment of <strong>{{ money($pending->amount) }}</strong> is requested for this booking.</div>@endif
            <div class="summary" style="margin-top:20px">
                <span class="muted">Stay</span><strong>{{ fmt_date($b->arrival, 'D d M Y') }} → {{ fmt_date($b->departure, 'D d M Y') }} ({{ $b->nights() }} nights)</strong>
                @foreach ($b->villas->where('status', 'active') as $bv)<span class="muted">Villa</span><span>{{ $bv->villa->type->name }} · {{ $bv->adults }} adult(s){{ $bv->children ? ', '.$bv->children.' child(ren)' : '' }}</span>@endforeach
                <span class="muted">Rate</span><span>{{ $b->ratePlan?->name }}</span>
                <span class="muted">Total</span><strong><x-price :amount="$b->grand_total" mode="charge" /></strong>
                <span class="muted">Paid</span><span>{{ money($paid) }}</span>
                <span class="total">Balance</span><span class="total"><x-price :amount="$balance" mode="charge" /></span>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:24px">
                @if (in_array($b->status, ['hold', 'tentative', 'confirmed', 'checked_in']) && ($balance > 0 || $pending))
                    <form method="post" action="{{ route('manage.pay', $b->manage_token) }}" data-once>@csrf
                        @if($pending)<input type="hidden" name="intent" value="{{ $pending->token }}">@endif
                        <button class="btn btn-primary" type="submit">Pay {{ money($pending?->amount ?? ($b->status === 'hold' ? $b->deposit_due : $balance)) }} securely</button></form>
                @endif
                @if (! in_array($b->status, ['hold', 'expired', 'cancelled']))<a class="btn" href="{{ route('manage.voucher', $b->manage_token) }}" target="_blank"><x-icon name="file" /> Voucher</a>@endif
                <a class="btn" href="{{ whatsapp_link('Hello, about booking '.$b->reference.': ') }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Change or ask</a>
            </div>
            @if ($b->payments->where('status', 'completed')->isNotEmpty())
                <h3 style="margin:28px 0 10px;font-size:20px">Payments</h3>
                <div class="summary small">@foreach ($b->payments->where('status', 'completed')->where('method', '!=', 'city_ledger') as $p)<span>{{ fmt_date($p->paid_at) }} · {{ $p->methodLabel() }} · {{ $p->reference }}</span><span>{{ money($p->amount) }}</span>@endforeach</div>
            @endif
            <p class="small muted" style="margin-top:22px">{{ $b->ratePlan?->description }} To change dates or cancel, contact us — we'll confirm any fee before making changes.</p>
        </div>
        <aside class="sticky-book">
            <img src="{{ $b->villas->first()?->villa->type->coverUrl() }}" alt="" style="border-radius:10px;aspect-ratio:16/10;object-fit:cover;width:100%" data-fallback="{{ asset('assets/img/placeholder-villa.svg') }}">
            <p class="small" style="margin:0">{{ property()->name }} · {{ setting('contact_address') }}<br>{{ setting('contact_phone') }}</p>
        </aside>
    </div>
</section>
@endsection
