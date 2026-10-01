<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><title>{{ $kot->kot_no }}</title>
    <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
    <style>:root{color-scheme:light} body{background:#fff;color:#000} .receipt-80{margin:0 auto;font-size:14px} .big{font-size:18px;font-weight:700}</style>
</head>
<body onload="window.print()">
<div class="receipt-80">
    <div class="big">KOT {{ strtoupper($kot->station) }}</div>
    <div>{{ $kot->kot_no }} · {{ $kot->created_at->format('H:i') }}</div>
    <div class="big">{{ $o->table ? 'TABLE '.$o->table->name : ($o->type === 'room_service' ? 'VILLA '.$o->villa?->code : 'TAKEAWAY') }}</div>
    <div>Waiter: {{ $o->waiter?->name }} · {{ $o->covers }} pax</div>
    <hr style="border:0;border-top:1px dashed #000">
    @foreach ($kot->items as $i)
        <div class="big">{{ rtrim(rtrim($i->quantity, '0'), '.') }} × {{ $i->name }}</div>
        @if ($i->modifiers)<div>&nbsp;&nbsp;{{ collect($i->modifiers)->pluck('name')->implode(', ') }}</div>@endif
        @if ($i->notes)<div>&nbsp;&nbsp;** {{ $i->notes }} **</div>@endif
    @endforeach
    @if ($o->notes)<hr style="border:0;border-top:1px dashed #000"><div>{{ $o->notes }}</div>@endif
</div>
</body>
</html>
