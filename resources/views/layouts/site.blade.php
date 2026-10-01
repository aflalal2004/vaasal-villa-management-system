<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $p = property();
        $fx = app(\App\Modules\Core\Services\CurrencyService::class);
        $fxSnap = $fx->snapshot();
        $curCode = $fx->current();
        $socials = \App\Models\SocialLink::for('website');
        $pageTitle = trim($__env->yieldContent('title'));
        $tagline = setting('site_tagline', 'Private pool villas in Jaffna, Sri Lanka');
        $desc = trim($__env->yieldContent('description')) ?: (setting('seo_description') ?: $tagline);
        $fullTitle = $pageTitle ? $pageTitle.' · '.$p->name.' — '.contact('location') : (setting('seo_title') ?: $p->name.' — '.$tagline);
        $heroImg = setting('site_hero_image');
        $ogImage = trim($__env->yieldContent('og_image')) ?: ($heroImg ?: asset('assets/brand/vaasal-logo-640.png'));
        $fxClient = ['base' => $fx->base(), 'current' => $curCode, 'rates' => $fxSnap['rates'], 'currencies' => $fx->currencies(), 'cookie' => config('vaasal.currency_display.cookie')];
    @endphp
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $desc }}">
    @if (setting('seo_keywords'))<meta name="keywords" content="{{ setting('seo_keywords') }}">@endif
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website"><meta property="og:site_name" content="{{ $p->name }}">
    <meta property="og:title" content="{{ $pageTitle ?: $p->name }}"><meta property="og:description" content="{{ $desc }}">
    <meta property="og:image" content="{{ $ogImage }}"><meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="en_LK">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="geo.region" content="LK-4"><meta name="geo.placename" content="{{ contact('location') }}">
    <meta name="geo.position" content="{{ $p->latitude }};{{ $p->longitude }}"><meta name="ICBM" content="{{ $p->latitude }}, {{ $p->longitude }}">
    <meta name="theme-color" content="#0E6B63">
    <link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32"><link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://images.unsplash.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono&family=Source+Serif+4:ital,opsz,wght@0,8..60,500;0,8..60,600;1,8..60,500&display=swap">
    <link rel="stylesheet" href="{{ asset_v('assets/css/site.css') }}">
    <script>
        (function () { try { var t = localStorage.getItem('vv-theme'); if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t); } catch (e) {} })();
        window.VV_FX = @json($fxClient);
    </script>
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org', '@type' => ['Resort', 'LodgingBusiness'], 'name' => $p->name, 'description' => $tagline,
        'url' => url('/'), 'logo' => asset('assets/brand/vaasal-logo-640.png'), 'image' => $ogImage,
        'telephone' => contact('whatsapp_display'), 'email' => contact('email'), 'currenciesAccepted' => 'LKR', 'priceRange' => 'LKR 38,000 – 120,000',
        'address' => ['@type' => 'PostalAddress', 'addressLocality' => contact('city'), 'addressRegion' => 'Northern Province', 'addressCountry' => 'LK'],
        'geo' => ['@type' => 'GeoCoordinates', 'latitude' => (float) $p->latitude, 'longitude' => (float) $p->longitude],
        'checkinTime' => substr($p->check_in_time, 0, 5), 'checkoutTime' => substr($p->check_out_time, 0, 5),
        'sameAs' => $socials->where('platform', '!=', 'whatsapp')->pluck('url')->values()->all(),
        'aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => setting('stat_rating', '4.9'), 'bestRating' => '5', 'reviewCount' => \App\Models\Testimonial::count() ?: 1],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @stack('head')
</head>
<body>
<div class="page-loader" aria-hidden="true"></div>
<a href="#main" class="skip-link">Skip to content</a>

<header class="site-header @yield('header_class')">
    <div class="wrap nav-bar">
        <a class="logo" href="{{ route('home') }}" aria-label="{{ $p->name }} home"><x-logo-mark :size="42" variant="full" class="logo-anim" /><span class="logo-text">Vaasal Villa<small>Boutique villa &amp; restaurant</small></span></a>
        <nav class="nav-links" aria-label="Main">
            @foreach (['home' => ['Home', 'home'], 'site.villas' => ['Rooms & Villas', 'site.villa*'], 'site.services' => ['Dining', 'site.services'], 'site.gallery' => ['Gallery', 'site.gallery'], 'site.contact' => ['Contact', 'site.contact'], 'manage.lookup' => ['My Booking', 'manage.*']] as $r => [$l, $on])
                <a href="{{ route($r) }}" @class(['active' => request()->routeIs($on)])>{{ $l }}</a>
            @endforeach
            <a class="nav-cta" href="{{ route('book.search') }}">Book now</a>
        </nav>
        <div class="nav-tools">
            <div class="cur-select" data-currency-select>
                <button type="button" class="cur-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="Display currency: {{ $curCode }}">
                    <span class="cur-sym" data-cur-sym>{{ $fx->currencies()[$curCode]['symbol'] }}</span><span class="cur-code" data-cur-code>{{ $curCode }}</span><x-icon name="chevron-down" class="ico cur-chev" />
                </button>
                <form class="cur-menu" method="post" action="{{ route('currency.switch') }}" role="listbox" aria-label="Choose currency" hidden>
                    @csrf
                    @foreach ($fx->currencies() as $code => $c)
                        <button type="submit" name="currency" value="{{ $code }}" role="option" aria-selected="{{ $code === $curCode ? 'true' : 'false' }}" @class(['on' => $code === $curCode])>
                            <span class="cur-sym">{{ $c['symbol'] }}</span><span class="cur-name"><strong>{{ $code }}</strong><small>{{ $c['name'] }}</small></span>
                            <x-icon name="check" class="ico cur-tick" />
                        </button>
                    @endforeach
                    <p class="cur-foot">Prices are charged in LKR. Other currencies are approximate{{ $fxSnap['source'] === 'api' ? ' (live rates)' : '' }}.</p>
                </form>
            </div>
            <button class="icon-btn" type="button" data-theme-toggle aria-label="Switch light / dark mode"><x-icon name="theme" /></button>
            <a class="btn btn-primary nav-book" href="{{ route('book.search') }}">Book now</a>
            <button class="icon-btn menu-btn" type="button" aria-label="Menu" aria-expanded="false"><x-icon name="menu" /></button>
        </div>
    </div>
</header>

<main id="main">
    @if (session('success'))<div class="wrap" style="padding-top:160px"><div class="alert alert-success" role="status">{{ session('success') }}</div></div>@endif
    @if (session('error'))<div class="wrap" style="padding-top:160px"><div class="alert alert-danger" role="alert">{{ session('error') }}</div></div>@endif
    @yield('content')
</main>

<footer class="site-footer" id="footer">
    <div class="wrap">
        <div class="foot-grid">
            <div>
                <a class="logo" href="{{ route('home') }}" style="color:#fff"><x-logo-mark :size="64" variant="full" /><span class="logo-text">Vaasal Villa<small>{{ contact('location') }}</small></span></a>
                <p class="small" style="margin-top:16px;color:#A9B7B2">{{ $tagline }}.</p>
                <div class="socials" aria-label="Social media">
                    @foreach ($socials as $s)<a href="{{ $s->url }}" target="_blank" rel="noopener" aria-label="{{ $s->name() }}" title="{{ $s->name() }}"><x-icon :name="$s->icon()" /></a>@endforeach
                </div>
            </div>
            <div><h4>Stay</h4><ul>
                <li><a href="{{ route('site.villas') }}">Villas</a></li><li><a href="{{ route('site.offers') }}">Offers</a></li>
                <li><a href="{{ route('book.search') }}">Check availability</a></li><li><a href="{{ route('manage.lookup') }}">Manage my booking</a></li></ul></div>
            <div><h4>Discover</h4><ul>
                <li><a href="{{ route('site.services') }}">Dining & spa</a></li><li><a href="{{ route('site.gallery') }}">Gallery</a></li>
                <li><a href="{{ route('site.about') }}">Our story</a></li><li><a href="{{ route('operator.register') }}">Tour operator partners</a></li></ul></div>
            <div><h4>Contact</h4><ul class="foot-contact">
                <li><x-icon name="pin" /> <span>{{ contact('address') }}</span></li>
                <li><x-icon name="phone" /> <a href="tel:{{ contact('phone_link') }}">{{ contact('phone') }}</a></li>
                <li><x-icon name="whatsapp" /> <a href="{{ whatsapp_link('Hello Vaasal Villa!') }}" target="_blank" rel="noopener">WhatsApp {{ contact('whatsapp_display') }}</a></li>
                <li><x-icon name="mail" /> <a href="mailto:{{ contact('email') }}">{{ contact('email') }}</a></li>
                <li><x-icon name="login" /> <a href="{{ route('login') }}">Staff & partner sign-in</a> · <a href="{{ route('pos.access') }}">POS</a></li></ul></div>
        </div>
        <div class="foot-bottom"><span>© {{ now()->year }} {{ $p->legal_name ?? $p->name }} · {{ contact('location') }}. All rights reserved.</span>
            <span>Check-in {{ substr($p->check_in_time, 0, 5) }} · Check-out {{ substr($p->check_out_time, 0, 5) }} · Secure payments in LKR</span></div>
    </div>
</footer>

<a class="wa-float" href="{{ whatsapp_link('Hello Vaasal Villa, I would like to ask about a stay.') }}" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp {{ contact('whatsapp_display') }}"><x-icon name="whatsapp-fill" /></a>
<script src="{{ asset_v('assets/js/site.js') }}" defer></script>
@stack('scripts')
</body>
</html>
