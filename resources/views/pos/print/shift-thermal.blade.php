<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Closing report #{{ $shift->id }}</title>
    <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
    <style>:root{color-scheme:light} body{background:#eee;color:#111} .receipt-80{background:#fff;margin:16px auto;border:1px dashed #bbb} .r-row{display:flex;justify-content:space-between;gap:8px} hr{border:0;border-top:1px dashed #999;margin:8px 0}
    @media print{body{background:#fff}.receipt-80{border:0;margin:0}}</style>
</head>
<body>
<div class="print-toolbar no-print" style="max-width:320px"><button class="btn btn-primary" type="button" onclick="window.print()">Print</button></div>
<div class="receipt-80">
    <div style="text-align:center"><img src="{{ asset('assets/brand/vaasal-logo-192.png') }}" width="64" height="64" alt="Vaasal Villa" style="display:inline-block"><br>
        <strong style="font-size:15px">{{ $shift->outlet->name }}</strong><br>{{ contact('location') }} · {{ contact('phone') }}</div>
    <hr>
    <div style="text-align:center;font-weight:700">{{ $shift->status === 'closed' ? 'CASHIER CLOSING (Z)' : 'X REPORT — SHIFT OPEN' }}</div>
    <div class="r-row"><span>Shift #{{ $shift->id }}</span><span>{{ now()->format('d/m/Y H:i') }}</span></div>
    <div class="r-row"><span>Cashier</span><span>{{ $shift->user->name }}</span></div>
    <div class="r-row"><span>Opened</span><span>{{ $shift->opened_at->format('d/m H:i') }}</span></div>
    @if ($shift->closed_at)<div class="r-row"><span>Closed</span><span>{{ $shift->closed_at->format('d/m H:i') }}</span></div>@endif
    <hr>
    @foreach ([['Opening float', $r['opening_float']], ['Cash sales', $r['cash_sales']], ['Cash in', $r['cash_in']], ['Cash out', -$r['cash_out']], ['Refunds', -$r['cash_refunds']], ['Deposits', -$r['deposits']], ['Adjustments', $r['adjustments']]] as [$l, $v])
        @if ($l === 'Opening float' || abs($v) > 0.009)<div class="r-row"><span>{{ $l }}</span><span>{{ number_format($v, 2) }}</span></div>@endif
    @endforeach
    <div class="r-row" style="font-weight:700"><span>EXPECTED CASH</span><span>{{ number_format($r['expected_cash'], 2) }}</span></div>
    @if ($r['counted_cash'] !== null)
        <div class="r-row"><span>Counted cash</span><span>{{ number_format($r['counted_cash'], 2) }}</span></div>
        <div class="r-row" style="font-weight:700"><span>VARIANCE</span><span>{{ number_format($r['variance'], 2) }}</span></div>
    @endif
    <hr>
    <div class="r-row"><span>Card</span><span>{{ number_format($r['card_total'], 2) }}</span></div>
    <div class="r-row"><span>Bank transfer</span><span>{{ number_format($r['bank_total'], 2) }}</span></div>
    <div class="r-row"><span>Digital / online</span><span>{{ number_format($r['digital_total'], 2) }}</span></div>
    <div class="r-row"><span>Room charges</span><span>{{ number_format($r['room_charges'], 2) }}</span></div>
    <div class="r-row"><span>Refunds (all)</span><span>{{ number_format($r['refunds'], 2) }}</span></div>
    <div class="r-row" style="font-size:15px;font-weight:700;margin-top:4px"><span>GRAND TOTAL LKR</span><span>{{ number_format($r['grand_total'], 2) }}</span></div>
    <div class="r-row" style="font-size:11px"><span>{{ $r['orders'] }} checks</span><span>tax {{ number_format($r['tax'], 2) }}</span></div>
    <hr>
    <div style="margin-top:18px">Cashier: ____________________</div>
    <div style="margin-top:14px">Manager: ____________________</div>
</div>
</body>
</html>
