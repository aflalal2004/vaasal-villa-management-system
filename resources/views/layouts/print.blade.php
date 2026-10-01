<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · Vaasal Villa</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;600&family=IBM+Plex+Mono&family=Source+Serif+4:opsz,wght@8..60,600&display=swap">
    <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
    <style>
        :root { color-scheme: light; }
        body { background: #eef1ee; color: #111; }
        .doc-head { display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; margin-bottom: 28px; }
        .doc-head h1 { font-family: var(--serif); font-size: 28px; margin: 0 0 4px; color: #111; }
        .doc-title { font-family: var(--serif); font-size: 22px; font-weight: 600; text-align: right; color: #0E6B63; }
        .doc-meta { font-size: 12.5px; color: #555; line-height: 1.6; }
        .doc-box { border: 1px solid #e3e3e3; border-radius: 8px; padding: 12px 14px; font-size: 13px; }
        .doc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; }
        .doc-totals { margin-left: auto; width: min(320px, 100%); margin-top: 14px; font-size: 13.5px; }
        .doc-totals div { display: flex; justify-content: space-between; padding: 4px 0; }
        .doc-totals .grand { border-top: 2px solid #111; font-weight: 700; font-size: 16px; padding-top: 8px; margin-top: 4px; }
        .doc-foot { margin-top: 36px; font-size: 11.5px; color: #777; border-top: 1px solid #e3e3e3; padding-top: 12px; }
        .stamp { display: inline-block; border: 2px solid #247A45; color: #247A45; padding: 4px 12px; border-radius: 6px; font-weight: 700; letter-spacing: .1em; transform: rotate(-4deg); }
    </style>
</head>
<body>
<div class="print-toolbar no-print">
    <a class="btn" href="{{ url()->previous() }}">← Back</a>
    <button class="btn btn-primary" type="button" data-print><x-icon name="printer" /> Print / Save as PDF</button>
</div>
@yield('content')
<script src="{{ asset_v('assets/js/app.js') }}"></script>
</body>
</html>
