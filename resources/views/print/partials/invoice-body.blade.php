@php $p = property(); @endphp
<div style="font-family:'IBM Plex Sans',Arial,sans-serif;color:#111;font-size:13px">
    <table style="width:100%;margin-bottom:22px"><tr>
        <td style="vertical-align:top;border:0">
            <div style="font-family:Georgia,serif;font-size:26px;font-weight:600">{{ $p->name }}</div>
            <div style="color:#555;line-height:1.6">{{ $p->legal_name }}<br>{{ collect([$p->address, $p->city, $p->country])->filter()->unique()->implode(', ') }}<br>{{ $p->phone }} · {{ $p->email }}@if($p->tax_id)<br>Tax ID {{ $p->tax_id }}@endif</div>
        </td>
        <td style="vertical-align:top;text-align:right;border:0">
            <div style="font-family:Georgia,serif;font-size:22px;color:#0E6B63;font-weight:600">{{ $inv->typeLabel() }}</div>
            <div style="color:#555;line-height:1.6">No. <strong>{{ $inv->number }}</strong><br>Date {{ $inv->issued_at->format('d M Y') }}
                @if ($inv->due_date && $inv->type !== 'receipt')<br>Due {{ $inv->due_date->format('d M Y') }}@endif
                @if ($inv->booking)<br>Booking {{ $inv->booking->reference }}@endif</div>
            @if (! empty($copy))<div style="margin-top:6px;color:#9A620C;font-weight:600;letter-spacing:.1em">COPY</div>@endif
        </td>
    </tr></table>

    <div style="border:1px solid #e3e3e3;border-radius:8px;padding:10px 12px;margin-bottom:16px">
        <strong>{{ $inv->type === 'receipt' ? 'Received from' : 'Bill to' }}</strong><br>{{ $inv->bill_to_name }}@if($inv->bill_to_details)<br><span style="color:#555;white-space:pre-line">{{ $inv->bill_to_details }}</span>@endif
        @if ($inv->booking)<br><span style="color:#555">Stay {{ fmt_date($inv->booking->arrival) }} – {{ fmt_date($inv->booking->departure) }}</span>@endif
    </div>

    <table style="width:100%;border-collapse:collapse">
        <thead><tr>
            <th style="text-align:left;padding:7px 8px;border-bottom:1px solid #ccc;font-size:11px;text-transform:uppercase;color:#666">Date</th>
            <th style="text-align:left;padding:7px 8px;border-bottom:1px solid #ccc;font-size:11px;text-transform:uppercase;color:#666">Description</th>
            <th style="text-align:right;padding:7px 8px;border-bottom:1px solid #ccc;font-size:11px;text-transform:uppercase;color:#666">Qty</th>
            <th style="text-align:right;padding:7px 8px;border-bottom:1px solid #ccc;font-size:11px;text-transform:uppercase;color:#666">Net</th>
            <th style="text-align:right;padding:7px 8px;border-bottom:1px solid #ccc;font-size:11px;text-transform:uppercase;color:#666">Svc + tax</th>
            <th style="text-align:right;padding:7px 8px;border-bottom:1px solid #ccc;font-size:11px;text-transform:uppercase;color:#666">Total</th>
        </tr></thead>
        <tbody>
        @foreach (collect($inv->lines)->groupBy('department') as $dept => $lines)
            <tr><td colspan="6" style="padding:10px 8px 4px;font-weight:600;color:#0E6B63">{{ $dept }}</td></tr>
            @foreach ($lines as $l)
                <tr>
                    <td style="padding:6px 8px;border-bottom:1px solid #eee;white-space:nowrap">{{ fmt_date($l['date'], 'd M') }}</td>
                    <td style="padding:6px 8px;border-bottom:1px solid #eee">{{ $l['description'] }}</td>
                    <td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right">{{ rtrim(rtrim(number_format($l['qty'], 2), '0'), '.') }}</td>
                    <td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right">{{ number_format($l['amount'], 2) }}</td>
                    <td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right">{{ number_format(($l['tax'] ?? 0) + ($l['service'] ?? 0), 2) }}</td>
                    <td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right">{{ number_format($l['total'], 2) }}</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>

    <table style="margin-left:auto;margin-top:14px;min-width:300px;font-size:13.5px">
        @if ($inv->type !== 'receipt')
            <tr><td style="padding:3px 8px">Net amount</td><td style="padding:3px 8px;text-align:right">{{ money($inv->subtotal) }}</td></tr>
            @if ($inv->discount_total > 0)<tr><td style="padding:3px 8px">Discount</td><td style="padding:3px 8px;text-align:right">− {{ money($inv->discount_total) }}</td></tr>@endif
            <tr><td style="padding:3px 8px">Service charge</td><td style="padding:3px 8px;text-align:right">{{ money($inv->service_total) }}</td></tr>
            <tr><td style="padding:3px 8px">Tax</td><td style="padding:3px 8px;text-align:right">{{ money($inv->tax_total) }}</td></tr>
        @endif
        <tr><td style="padding:8px;border-top:2px solid #111;font-weight:700;font-size:16px">{{ $inv->type === 'receipt' ? 'Amount received' : 'Total' }}</td><td style="padding:8px;border-top:2px solid #111;text-align:right;font-weight:700;font-size:16px">{{ money($inv->grand_total, $inv->currency) }}</td></tr>
        @if ($inv->type !== 'receipt')
            <tr><td style="padding:3px 8px">Paid</td><td style="padding:3px 8px;text-align:right">{{ money($inv->paid_total) }}</td></tr>
            <tr><td style="padding:3px 8px"><strong>Balance</strong></td><td style="padding:3px 8px;text-align:right"><strong>{{ money($inv->balance) }}</strong></td></tr>
        @endif
    </table>

    @if ($inv->status === 'paid')<div style="margin-top:14px"><span class="stamp" style="border:2px solid #247A45;color:#247A45;padding:4px 12px;border-radius:6px;font-weight:700;letter-spacing:.1em;display:inline-block">PAID</span></div>@endif
    @if ($inv->type === 'operator_invoice' && $inv->operator)
        <p style="margin-top:16px;color:#555">Payment terms: {{ $inv->operator->payment_terms_days }} days. Please quote {{ $inv->number }} with your transfer.</p>
    @endif
    <div style="margin-top:30px;font-size:11.5px;color:#777;border-top:1px solid #e3e3e3;padding-top:10px">
        All amounts are in {{ $inv->currency ?? config('vaasal.currency') }} ({{ ($inv->currency ?? config('vaasal.currency')) === 'LKR' ? 'Sri Lankan Rupees' : $inv->currency }}); payments are charged in this currency. {{ contact('location') }} · {{ contact('phone') }} · {{ contact('email') }}<br>
        Thank you for staying with us. Document hash {{ substr($inv->hash, 0, 16) }} · Issued {{ $inv->issued_at->format('d M Y H:i') }}{{ $inv->issuer ? ' by '.$inv->issuer->name : '' }}.
    </div>
</div>
