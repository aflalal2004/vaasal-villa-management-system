<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="login-url" content="{{ route('login') }}">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32"><link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&family=Playfair+Display:wght@600;700&display=swap">
<link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
{{-- Apply saved theme before first paint (no flash). Signed-in users: server preference; otherwise browser preference. --}}
<script>
  (function () {
    var t = @json(auth()->user()->theme ?? null);
    try { if (!t || t === 'system') t = localStorage.getItem('vv-theme') || t; } catch (e) {}
    if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
  })();
  window.VV_CURRENCY = @json(config('vaasal.currency'));
</script>
