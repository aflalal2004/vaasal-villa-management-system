@extends('layouts.site')
@section('title', 'Services')
@section('content')
<section class="page-hero" style="background-image:url('https://images.unsplash.com/photo-1544161515-4ab6ce6db874?auto=format&fit=crop&w=2000&q=70')">
    <div class="wrap"><span class="eyebrow" style="color:#E9C98A">Services</span><h1>Dining, spa, drivers & days out.</h1><p class="lead" style="margin-top:14px">Everything can be arranged on WhatsApp and charged to your villa.</p></div>
</section>
<section class="block">
    <div class="wrap" style="display:grid;gap:clamp(48px,7vw,96px)">
        @foreach ($services as $s)
            <article class="split reveal" id="{{ $s->slug }}" style="{{ $loop->odd ? '' : 'direction:rtl' }}">
                <img src="{{ $s->imageUrl() }}" alt="{{ $s->title }}" loading="lazy" style="border-radius:var(--radius);aspect-ratio:4/3;object-fit:cover;width:100%;direction:ltr" data-fallback="{{ asset('assets/img/placeholder-villa.svg') }}">
                <div style="direction:ltr">
                    <span class="eyebrow"><x-icon :name="$s->icon" style="width:16px;height:16px;vertical-align:-3px" /> {{ $s->title }}</span>
                    <h2 style="font-size:clamp(28px,3vw,40px)">{{ $s->summary }}</h2>
                    <p style="margin-top:16px">{{ $s->description }}</p>
                    @if ($s->price_from)<p><strong>From <x-price :amount="$s->price_from" /></strong></p>@endif
                    <a class="btn" href="{{ whatsapp_link('Hello, I would like to arrange: '.$s->title) }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Arrange on WhatsApp</a>
                </div>
            </article>
        @endforeach
    </div>
</section>
@endsection
