@extends('layouts.site')
@section('title', 'Offers')
@section('content')
<section class="page-hero" style="background-image:url('https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=2000&q=70')">
    <div class="wrap"><span class="eyebrow" style="color:#E9C98A">Offers</span><h1>Stay longer, pay less.</h1><p class="lead" style="margin-top:14px">Promo codes apply automatically in the booking page.</p></div>
</section>
<section class="block">
    <div class="wrap">
        @forelse ($offers as $o)
            <article class="result reveal">
                <img src="{{ $o->imageUrl() }}" alt="{{ $o->title }}" loading="lazy" data-fallback="{{ asset('assets/img/placeholder-villa.svg') }}">
                <div class="body">
                    <span class="eyebrow">{{ $o->label() }}</span>
                    <h2 style="font-size:32px">{{ $o->title }}</h2>
                    <p>{{ $o->description ?: $o->summary }}</p>
                    <p class="small muted">Minimum {{ $o->min_nights }} night{{ $o->min_nights > 1 ? 's' : '' }}{{ $o->valid_to ? ' · book by '.fmt_date($o->valid_to) : '' }}@if($o->promo_code) · code <strong style="color:var(--ink)">{{ $o->promo_code }}</strong>@endif</p>
                    <div><a class="btn btn-primary" href="{{ route('book.search', ['promo' => $o->promo_code]) }}">Book with this offer</a></div>
                </div>
            </article>
        @empty
            <p class="lead center">No offers at the moment — book direct for our best available rate.</p>
        @endforelse
    </div>
</section>
@endsection
