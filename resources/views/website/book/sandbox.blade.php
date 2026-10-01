<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Sandbox payment provider</title>
    <link rel="stylesheet" href="{{ asset_v('assets/css/site.css') }}">
    <style>body{display:grid;place-items:center;min-height:100vh;padding:20px;background:#EEF1F6;color:#1a1f36;font-family:system-ui,sans-serif}
        .box{background:#fff;border-radius:14px;max-width:440px;width:100%;padding:30px;box-shadow:0 20px 50px rgba(0,0,0,.12)} .tag{font-size:12px;font-weight:700;letter-spacing:.1em;color:#6b4bd8}
        .btn{width:100%;margin-top:10px}</style>
</head>
<body>
<div class="box">
    <div class="tag">SANDBOX PAYMENT PROVIDER · DEVELOPMENT ONLY</div>
    <h1 style="font-size:24px;margin:10px 0 6px;font-family:system-ui">Pay Vaasal Villa</h1>
    <p style="color:#555">Booking {{ $intent->booking->reference }} · {{ $intent->booking->guest->fullName() }}</p>
    <div style="font-size:34px;font-weight:700;margin:14px 0">{{ money($intent->amount, $intent->currency) }}</div>
    <p style="font-size:14px;color:#555">This page stands in for the real hosted checkout (Stripe / PayHere). In production the guest enters card details here, on the provider's domain — never on the Vaasal Villa server.</p>
    @if ($intent->status === 'paid')
        <p><strong>Already paid.</strong></p><a class="btn btn-primary" href="{{ route('pay.return', $intent->token) }}">Continue</a>
    @else
    <form method="post" action="{{ route('pay.sandbox.complete', $intent->token) }}">@csrf
        <button class="btn btn-primary" name="result" value="approve" type="submit">Approve test payment</button>
        <button class="btn" name="result" value="decline" type="submit">Decline</button>
    </form>
    @endif
</div>
</body>
</html>
