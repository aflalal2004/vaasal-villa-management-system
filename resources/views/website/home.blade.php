@extends('layouts.site')
@section('content')
@php
    // Hero media: CMS hero image first, then villa/grounds gallery photos; a local gradient shows if every image fails.
    $heroes = collect([setting('site_hero_image')])->filter()
        ->merge(\App\Models\GalleryItem::where('is_active', true)->where('type', 'image')->whereIn('category', ['villas', 'grounds'])->orderBy('sort_order')->limit(4)->get()->map->url())
        ->unique()->take(4)->values();
    $video = setting('site_hero_video');
    $poster = setting('site_hero_video_poster') ?: $heroes->first();
@endphp
<section class="hero" aria-label="Welcome">
    <div class="hero-slides" aria-hidden="true">
        @foreach ($heroes as $h)<div class="slide" style="background-image:url('{{ $h }}')"></div>@endforeach
    </div>
    @if ($video)<video data-hero-video data-src="{{ $video }}" muted loop playsinline autoplay preload="none" @if($poster) poster="{{ $poster }}" @endif aria-hidden="true" tabindex="-1"></video>@endif
    <div class="wrap hero-inner">
        <span class="eyebrow" style="color:#E9C98A"><x-icon name="pin" /> {{ contact('city') }} · {{ contact('country') }}</span>
        <h1>{{ setting('site_hero_title', 'Private pool villas in Jaffna, Sri Lanka') }}</h1>
        <p>{{ setting('site_hero_text') }}</p>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:28px">
            <a class="btn btn-primary" href="{{ route('site.villas') }}">Explore the villas</a>
            <a class="btn btn-light" href="{{ whatsapp_link('Hello! I would like to plan a stay at Vaasal Villa.') }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Chat on WhatsApp</a>
        </div>
    </div>
    <div class="hero-dots"></div>
</section>

<div class="wrap booking-bar">@include('website.partials.booking-bar')</div>

<section class="block">
    <div class="wrap split">
        <div class="reveal">
            <span class="eyebrow">Welcome to Vaasal</span>
            <h2>A threshold between the garden and the sea.</h2>
            <p class="lead" style="margin-top:18px">{{ \Illuminate\Support\Str::before(setting('site_about', ''), "\n") }}</p>
            <div class="counters">
                <div class="counter"><div class="num" data-count="{{ \App\Models\Villa::where('is_active', true)->count() }}">{{ \App\Models\Villa::where('is_active', true)->count() }}</div><div class="lbl">private villas</div></div>
                <div class="counter"><div class="num" data-count="{{ setting('stat_guests', 4800) }}" data-suffix="+">{{ setting('stat_guests', 4800) }}+</div><div class="lbl">guests welcomed</div></div>
                <div class="counter"><div class="num" data-count="{{ setting('stat_rating', '4.9') }}" data-suffix="/5">{{ setting('stat_rating', '4.9') }}/5</div><div class="lbl">average guest rating</div></div>
                <div class="counter"><div class="num" data-count="{{ setting('stat_years', 9) }}">{{ setting('stat_years', 9) }}</div><div class="lbl">years of hospitality</div></div>
            </div>
            <a class="btn" href="{{ route('site.about') }}" style="margin-top:30px">Our story <x-icon name="arrow-right" /></a>
        </div>
        <div class="stack-img reveal d2">
            <img src="https://images.unsplash.com/photo-1540541338287-41700207dee6?auto=format&fit=crop&w=1000&q=70" alt="Pool villa terrace" loading="lazy" data-fallback="{{ asset('assets/img/placeholder-villa.svg') }}">
            <img src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=600&q=70" alt="Dinner on the terrace" loading="lazy" data-fallback="{{ asset('assets/img/placeholder-villa.svg') }}">
        </div>
    </div>
</section>

<section class="block tint">
    <div class="wrap">
        <div class="section-head">
            <div class="reveal"><span class="eyebrow">The villas</span><h2>Four ways to stay</h2></div>
            <a class="btn reveal" href="{{ route('site.villas') }}">Compare all villas <x-icon name="arrow-right" /></a>
        </div>
        <div class="cards">
            @foreach ($types as $t)@include('website.partials.villa-card', ['t' => $t, 'delay' => 'd'.($loop->index % 3)])@endforeach
        </div>
    </div>
</section>

@if ($offers->isNotEmpty())
<section class="block">
    <div class="wrap">
        <div class="section-head"><div class="reveal"><span class="eyebrow">Offers</span><h2>Reasons to stay longer</h2></div><a class="btn reveal" href="{{ route('site.offers') }}">All offers</a></div>
        <div class="cards">
            @foreach ($offers as $o)
                <a class="offer reveal d{{ $loop->index }}" href="{{ route('book.search', ['promo' => $o->promo_code]) }}">
                    <img src="{{ $o->imageUrl() }}" alt="" loading="lazy" data-fallback="{{ asset('assets/img/placeholder-villa.svg') }}">
                    <div class="body"><span class="badge">{{ $o->label() }}</span><h3>{{ $o->title }}</h3><p class="small" style="color:rgba(255,255,255,.85);margin:8px 0 0">{{ $o->summary }}@if($o->promo_code) · code <span class="code">{{ $o->promo_code }}</span>@endif</p></div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="block tint">
    <div class="wrap">
        <div class="section-head"><div class="reveal"><span class="eyebrow">Beyond the villa</span><h2>Dining, spa & days out</h2></div><a class="btn reveal" href="{{ route('site.services') }}">All services</a></div>
        <div class="cards" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
            @foreach ($services as $s)
                <div class="service reveal d{{ $loop->index % 3 }}"><span class="ic"><x-icon :name="$s->icon" /></span><div><h3 style="font-size:20px">{{ $s->title }}</h3><p class="small muted" style="margin:8px 0 0">{{ $s->summary }}</p></div></div>
            @endforeach
        </div>
    </div>
</section>

@if ($gallery->isNotEmpty())
<section class="block">
    <div class="wrap">
        <div class="section-head"><div class="reveal"><span class="eyebrow">Gallery</span><h2>Days at Vaasal</h2></div><a class="btn reveal" href="{{ route('site.gallery') }}">Full gallery</a></div>
        <div class="masonry">
            @foreach ($gallery as $g)
                <a href="{{ $g->url() }}" data-lightbox="home" data-caption="{{ $g->title }}" class="reveal"><img src="{{ $g->thumbUrl() }}" alt="{{ $g->title }}" loading="lazy" data-fallback="{{ asset('assets/img/placeholder-villa.svg') }}"><span class="cap">{{ $g->title }}</span></a>
            @endforeach
        </div>
    </div>
</section>
@endif

@if ($testimonials->isNotEmpty())
<section class="block tint">
    <div class="wrap split" style="align-items:start">
        <div class="reveal"><span class="eyebrow">Guest words</span><h2>What guests tell us</h2>
            <div class="slider-nav"><button class="icon-btn" type="button" data-q-prev aria-label="Previous review"><x-icon name="arrow-left" /></button><button class="icon-btn" type="button" data-q-next aria-label="Next review"><x-icon name="arrow-right" /></button></div></div>
        <div class="quotes reveal d1"><div class="quote-track">
            @foreach ($testimonials as $t)
                <figure class="quote" style="margin:0"><div class="stars" role="img" aria-label="{{ $t->rating }} out of 5">@for ($i = 0; $i < $t->rating; $i++)<x-icon name="star-fill" />@endfor</div>
                    <blockquote>“{{ $t->content }}”</blockquote><cite>— {{ $t->guest_name }}, {{ $t->country }} · via {{ $t->source }}</cite></figure>
            @endforeach
        </div></div>
    </div>
</section>
@endif

<section class="block">
    <div class="wrap split">
        <div class="reveal">
            <span class="eyebrow">Getting here</span>
            <h2>Thirty minutes from Jaffna International Airport, a world away.</h2>
            <p class="lead" style="margin-top:16px">{{ contact('address') }}. Our drivers meet every flight at Palaly and every train at Jaffna station — add a transfer when you book, or call <a href="tel:{{ contact('phone_link') }}">{{ contact('phone') }}</a>.</p>
            <div class="weather" data-weather="{{ route('site.weather') }}" style="margin-top:24px">
                <div><div class="temp"><span class="skeleton" style="display:inline-block;width:80px;height:40px"></span></div><div class="cond small">Loading today's weather…</div><div class="extra small muted"></div></div>
                <div style="flex:1"><div class="forecast"><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div></div></div>
            </div>
        </div>
        <iframe class="map reveal d2" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map to Vaasal Villa"
            src="https://www.google.com/maps?q={{ urlencode(config('vaasal.site.maps_query')) }}&ll={{ property()->latitude }},{{ property()->longitude }}&z=13&output=embed"></iframe>
    </div>
</section>

<section class="block" style="padding-top:0">
    <div class="wrap">
        <div class="cta reveal" style="background-image:url('https://images.unsplash.com/photo-1519046904884-53103b34b206?auto=format&fit=crop&w=1800&q=60')">
            <span class="eyebrow" style="color:#E9C98A">Direct booking benefits</span>
            <h2 style="max-width:18ch">Book direct for the best rate and a welcome drink on arrival.</h2>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:26px">
                <a class="btn btn-primary" href="{{ route('book.search') }}">Check availability</a>
                <a class="btn btn-light" href="{{ route('site.contact') }}">Ask a question</a>
            </div>
        </div>
    </div>
</section>
@endsection
