<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · Vaasal Villa</title>
    <link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32"><link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/css/site.css') }}">
    <script>(function(){try{var t=localStorage.getItem('vv-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
</head>
<body style="min-height:100vh;display:grid;place-items:center;padding:24px">
<main class="form-card" style="max-width:520px;width:100%;text-align:center">
    <x-brand-logo :size="112" :eager="true" style="margin:0 auto 14px" />
    <span class="eyebrow">@yield('code')</span>
    <h1 style="font-size:32px">@yield('title')</h1>
    <p class="lead" style="margin:14px auto 24px">@yield('message')</p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">@yield('actions')</div>
</main>
</body>
</html>
