<!doctype html>
<html lang="en">
<body style="margin:0;background:#F4F6F3;font-family:Arial,Helvetica,sans-serif;color:#17211E">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6F3;padding:24px 12px">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px;overflow:hidden">
    <tr><td style="background:#0E6B63;color:#fff;padding:28px 32px">
        <img src="{{ asset('assets/brand/vaasal-logo-192.png') }}" width="72" height="72" alt="Vaasal Villa" style="display:block;background:#fff;border-radius:14px;padding:4px;margin-bottom:14px">
        <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;opacity:.8">Vaasal Villa · {{ property()->city }}, {{ property()->country }}</div>
        <div style="font-family:Georgia,serif;font-size:26px;margin-top:6px">Your stay is confirmed</div>
        <div style="margin-top:6px;opacity:.9">Booking reference <strong>{{ $b->reference }}</strong></div>
    </td></tr>
    <tr><td style="padding:28px 32px;font-size:15px;line-height:1.6">
        <p>Dear {{ $b->guest->first_name }},</p>
        <p>Thank you for booking with us. Here are your details — please keep this email as your voucher.</p>
        <table role="presentation" width="100%" style="border-collapse:collapse;font-size:14px;margin:12px 0">
            <tr><td style="padding:8px 0;color:#6D7B76">Arrival</td><td style="padding:8px 0;text-align:right"><strong>{{ fmt_date($b->arrival, 'l, d F Y') }}</strong> from {{ substr(property()->check_in_time, 0, 5) }}</td></tr>
            <tr><td style="padding:8px 0;color:#6D7B76">Departure</td><td style="padding:8px 0;text-align:right"><strong>{{ fmt_date($b->departure, 'l, d F Y') }}</strong> by {{ substr(property()->check_out_time, 0, 5) }}</td></tr>
            @foreach ($b->villas->where('status', 'active') as $bv)
            <tr><td style="padding:8px 0;color:#6D7B76">Villa</td><td style="padding:8px 0;text-align:right">{{ $bv->villa->type->name }} · {{ $bv->adults }} adult(s){{ $bv->children ? ', '.$bv->children.' child(ren)' : '' }}</td></tr>
            @endforeach
            <tr><td style="padding:8px 0;color:#6D7B76;border-top:1px solid #E9EDEA">Total</td><td style="padding:8px 0;text-align:right;border-top:1px solid #E9EDEA"><strong>{{ money($b->grand_total) }}</strong></td></tr>
            <tr><td style="padding:4px 0;color:#6D7B76">Paid</td><td style="padding:4px 0;text-align:right">{{ money($paid) }}</td></tr>
            <tr><td style="padding:4px 0;color:#6D7B76">Balance due at the villa</td><td style="padding:4px 0;text-align:right">{{ money(max(0, $b->grand_total - $paid)) }}</td></tr>
        </table>
        <p style="text-align:center;margin:26px 0"><a href="{{ route('manage.show', $b->manage_token) }}" style="background:#0E6B63;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold">View or manage your booking</a></p>
        <p style="font-size:13px;color:#44524D">Need an airport transfer or a table for dinner? Reply to this email, call <a href="tel:{{ contact('phone_link') }}" style="color:#0E6B63">{{ contact('phone') }}</a> or message us on <a href="{{ whatsapp_link('Hello Vaasal Villa, my booking reference is '.$b->reference) }}" style="color:#0E6B63">WhatsApp {{ contact('whatsapp_display') }}</a>.</p>
        <p style="font-size:12px;color:#6D7B76">{{ $b->ratePlan?->name }}: {{ $b->ratePlan?->description }}</p>
    </td></tr>
    <tr><td style="padding:18px 32px;background:#F9FAF8;font-size:12px;color:#6D7B76">{{ property()->name }} · {{ contact('address') }} · <a href="mailto:{{ contact('email') }}" style="color:#6D7B76">{{ contact('email') }}</a></td></tr>
</table>
</td></tr>
</table>
</body>
</html>
