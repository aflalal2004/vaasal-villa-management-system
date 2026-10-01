<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $final ? 'Receipt '.$o->invoice_no : 'Bill '.$o->order_no }}</title>
    <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
    <style>:root{color-scheme:light} body{background:#eee;color:#111} .receipt-80{background:#fff;margin:16px auto;border:1px dashed #bbb} .r-row{display:flex;justify-content:space-between;gap:8px} hr{border:0;border-top:1px dashed #999;margin:8px 0}
    @media print{body{background:#fff}.receipt-80{border:0;margin:0}}</style>
</head>
<body>
<div class="print-toolbar no-print" style="max-width:320px"><button class="btn btn-primary" type="button" onclick="window.print()">Print</button></div>
<div class="receipt-80">
    <div style="text-align:center;white-space:pre-line"><img src="{{ asset('assets/brand/vaasal-logo-192.png') }}" width="64" height="64" alt="Vaasal Villa" style="display:inline-block">
<strong style="font-size:15px">{{ $o->outlet->name }}</strong>
{{ $o->outlet->receipt_header }}
Tax ID {{ property()->tax_id }}</div>
    <hr>
    <div style="text-align:center;font-weight:700">{{ $final ? 'TAX INVOICE / RECEIPT' : 'GUEST BILL — NOT A RECEIPT' }}</div>
    <div class="r-row"><span>{{ $final ? $o->invoice_no : $o->order_no }}</span><span>{{ now()->format('d/m/Y H:i') }}</span></div>
    <div class="r-row"><span>{{ $o->table ? 'Table '.$o->table->name : ($o->type === 'room_service' ? 'Villa '.$o->villa?->code : 'Takeaway') }} · {{ $o->covers }} pax</span><span>{{ $o->waiter?->name }}</span></div>
    @if ($o->booking)<div>Guest: {{ $o->booking->guest->fullName() }} ({{ $o->booking->reference }})</div>@endif
    <hr>
    @foreach ($o->liveItems as $i)
        <div class="r-row"><span>{{ rtrim(rtrim($i->quantity, '0'), '.') }} × {{ $i->name }}</span><span>{{ number_format($i->line_total, 2) }}</span></div>
        @if ($i->modifiers)<div style="padding-left:14px;font-size:11px">{{ collect($i->modifiers)->pluck('name')->implode(', ') }}</div>@endif
    @endforeach
    <hr>
    <div class="r-row"><span>Subtotal</span><span>{{ number_format($o->subtotal, 2) }}</span></div>
    @if ($o->discount_amount > 0)<div class="r-row"><span>Discount</span><span>-{{ number_format($o->discount_amount, 2) }}</span></div>@endif
    <div class="r-row"><span>Service {{ (float) $o->outlet->service_charge_pct }}%</span><span>{{ number_format($o->service_charge, 2) }}</span></div>
    <div class="r-row"><span>Tax {{ (float) $o->outlet->tax_pct }}%</span><span>{{ number_format($o->tax_amount, 2) }}</span></div>
    <div class="r-row" style="font-size:15px;font-weight:700;margin-top:4px"><span>TOTAL {{ config('vaasal.currency') }}</span><span>{{ number_format($o->total, 2) }}</span></div>
    @if ($final)
        <hr>
        @foreach ($o->payments as $p)
            <div class="r-row"><span>{{ $p->type === 'refund' ? 'Refund' : '' }} {{ $p->method === 'room_charge' ? 'Charged to villa '.$o->villa?->code : \App\Models\PosPayment::label($p->method) }}</span><span>{{ number_format($p->amount, 2) }}</span></div>
            @if ($p->tendered)<div class="r-row" style="font-size:11px"><span>Tendered / change</span><span>{{ number_format($p->tendered, 2) }} / {{ number_format($p->change_due, 2) }}</span></div>@endif
        @endforeach
        @if ($o->status === 'charged_to_room' || $o->payments->contains('method', 'room_charge'))
            <div style="margin-top:16px">Guest signature: ____________________</div>
        @endif
        <div>Cashier: {{ $o->cashier?->name }}</div>
    @else
        <div style="margin-top:14px">Villa no. (to charge): ________ Signature: __________</div>
    @endif
    <hr>
    <div style="text-align:center">{{ $o->outlet->receipt_footer }}</div>
    @php $socials = \App\Models\SocialLink::for('restaurant'); @endphp
    @if ($socials->isNotEmpty())
        <div style="text-align:center;font-size:11px;margin-top:6px">Follow us: {{ $socials->map(fn ($s) => $s->name())->implode(' · ') }}</div>
    @endif
    <div style="text-align:center;font-size:11px;margin-top:4px">{{ contact('location') }} · {{ contact('phone') }}</div>
</div>
</body>
</html>
