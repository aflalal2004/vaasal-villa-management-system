@extends('layouts.site')
@section('title', $t->name)
@section('description', $t->short_description)
@section('og_image', $t->coverUrl())
@section('header_class', 'always')
@section('content')
@php $images = $t->media->where('type', 'image')->values(); $videos = $t->media->where('type', 'video'); @endphp
<div class="wrap" style="padding-top:150px">
    <nav class="small muted" aria-label="Breadcrumb"><a href="{{ route('site.villas') }}">Villas</a> / {{ $t->name }}</nav>
    <div class="section-head" style="margin:14px 0 22px">
        <div><h1 style="font-size:clamp(34px,4.5vw,56px)">{{ $t->name }}</h1>
            <div class="chips" style="margin-top:12px"><span class="chip">{{ $t->max_adults }} adults{{ $t->max_children ? ' + '.$t->max_children.' children' : '' }}</span><span class="chip">{{ $t->bedrooms }} bedroom · {{ $t->bathrooms }} bath</span>
                @if($t->size_sqm)<span class="chip">{{ $t->size_sqm }} m²</span>@endif<span class="chip">{{ $t->bed_configuration }}</span></div></div>
        <div class="row" style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="btn" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}" target="_blank" rel="noopener">Share</a>
            <a class="btn" href="{{ whatsapp_link('I am interested in the '.$t->name.' — '.url()->current()) }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Ask</a>
        </div>
    </div>
    <div class="detail-gallery">
        @foreach ($images->take(5) as $i => $m)
            <a href="{{ $m->url() }}" data-lightbox="villa" data-caption="{{ $m->alt }}" @if($i >= 3) class="hide-sm" @endif><img src="{{ $m->url() }}" alt="{{ $m->alt ?? $t->name }}" @if($i) loading="lazy" @endif data-fallback="{{ asset('assets/img/placeholder-villa.svg') }}"></a>
        @endforeach
        @foreach ($images->slice(5) as $m)<a href="{{ $m->url() }}" data-lightbox="villa" hidden></a>@endforeach
    </div>
</div>

<section class="block" style="padding-top:48px">
    <div class="wrap detail-layout">
        <div>
            <p class="lead">{{ $t->short_description }}</p>
            @foreach (preg_split("/\n\s*\n/", (string) $t->description) as $para)@if(! $loop->first || $para !== $t->short_description)<p>{{ $para }}</p>@endif @endforeach
            <h3 style="margin:34px 0 16px">In the villa</h3>
            <ul class="fac-list">@foreach ($t->facilities as $f)<li><x-icon :name="$f->icon" /> {{ $f->name }}</li>@endforeach</ul>

            @if ($videos->isNotEmpty())
                <h3 style="margin:34px 0 16px">Video tour</h3>
                @foreach ($videos as $v)
                    @php preg_match('/(?:youtu\.be\/|v=)([\w-]{6,})/', $v->path, $mm); @endphp
                    @if (! empty($mm[1]))<div class="video-embed"><button type="button" class="video-poster" data-yt="{{ $mm[1] }}" style="background-image:url('https://i.ytimg.com/vi/{{ $mm[1] }}/hqdefault.jpg')" aria-label="Play video tour"><span><x-icon name="play" /></span></button></div>
                    @else<a class="btn" href="{{ $v->path }}" data-lightbox="video" data-type="video"><x-icon name="play" /> Watch the tour</a>@endif
                @endforeach
            @endif

            <h3 style="margin:34px 0 16px">Rates & policies</h3>
            <div class="form-card" style="padding:18px 22px">
                <div class="summary">
                    <span>Standard nightly rate</span><strong><x-price :amount="$t->base_rate" /></strong>
                    @foreach ($seasonRates as $r)<span>{{ $r->season->name }} <span class="muted small">({{ fmt_date($r->season->start_date, 'd M') }} – {{ fmt_date($r->season->end_date, 'd M y') }}{{ $r->min_stay > 1 ? ', min '.$r->min_stay.' nights' : '' }})</span></span><strong><x-price :amount="$r->amount" /></strong>@endforeach
                    @if ($t->extra_child_rate > 0)<span>Child (per night)</span><strong><x-price :amount="$t->extra_child_rate" /></strong>@endif
                </div>
                <hr style="border:0;border-top:1px solid var(--line);margin:16px 0">
                @foreach ($plans as $p)<p class="small" style="margin:0 0 8px"><strong>{{ $p->name }}</strong> — {{ $p->description }}</p>@endforeach
                <p class="small muted" style="margin:0">Rates exclude {{ (float) property()->service_charge_pct }}% service charge and {{ (float) property()->tax_pct }}% tax, shown in full before payment. Check-in {{ substr(property()->check_in_time, 0, 5) }}, check-out {{ substr(property()->check_out_time, 0, 5) }}.</p>
            </div>
        </div>
        <aside>
            <form class="sticky-book" action="{{ route('book.search') }}" method="get" data-booking>
                <input type="hidden" name="type" value="{{ $t->id }}">
                <div><span class="muted small">from</span> <strong style="font:600 30px var(--serif)"><x-price :amount="$t->base_rate" /></strong> <span class="muted small">/ night</span></div>
                <div class="fgrid">
                    <div class="field"><label for="va">Check-in</label><input id="va" type="date" name="arrival" value="{{ now()->addDays(14)->toDateString() }}" min="{{ now()->toDateString() }}" required></div>
                    <div class="field"><label for="vd">Check-out <span data-nights class="muted small"></span></label><input id="vd" type="date" name="departure" value="{{ now()->addDays(17)->toDateString() }}" required></div>
                    <div class="field"><label for="vad">Adults</label><select id="vad" name="adults">@for ($i = 1; $i <= $t->max_adults; $i++)<option @selected($i === min(2, $t->max_adults))>{{ $i }}</option>@endfor</select></div>
                    <div class="field"><label for="vch">Children</label><select id="vch" name="children">@for ($i = 0; $i <= $t->max_children; $i++)<option>{{ $i }}</option>@endfor</select></div>
                </div>
                <button class="btn btn-primary" type="submit" style="width:100%">Check availability & price</button>
                <p class="small muted center" style="margin:0">{{ $t->villas->count() }} {{ $t->name }}{{ $t->villas->count() > 1 ? 's' : '' }} · instant confirmation · secure payment</p>
                @foreach ($offers as $o)<div class="alert alert-info small" style="margin:0"><strong>{{ $o->label() }}</strong> — {{ $o->title }}@if($o->promo_code) · code {{ $o->promo_code }}@endif</div>@endforeach
            </form>
        </aside>
    </div>
</section>

@if ($others->isNotEmpty())
<section class="block tint"><div class="wrap">
    <div class="section-head"><h2>Other villas</h2></div>
    <div class="cards">@foreach ($others as $o)@include('website.partials.villa-card', ['t' => $o])@endforeach</div>
</div></section>
@endif
@push('head')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'HotelRoom', 'name' => $t->name, 'description' => $t->short_description, 'image' => $images->map->url()->values(),
    'occupancy' => ['@type' => 'QuantitativeValue', 'maxValue' => $t->maxGuests()], 'bed' => $t->bed_configuration, 'floorSize' => ['@type' => 'QuantitativeValue', 'value' => $t->size_sqm, 'unitCode' => 'MTK'],
    'offers' => ['@type' => 'Offer', 'price' => (float) $t->base_rate, 'priceCurrency' => config('vaasal.currency')]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush
@endsection
