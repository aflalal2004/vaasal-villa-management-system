@extends('layouts.site')
@section('title', 'Availability')
@section('header_class', 'always')
@section('content')
<section class="block" style="padding-top:150px">
    <div class="wrap">
        <div class="steps"><span class="on">1 · Choose villa</span><span>2 · Your details</span><span>3 · Secure payment</span><span>4 · Confirmation</span></div>
        @include('website.partials.booking-bar', ['promo' => $promo])
        <form method="get" action="{{ route('book.search') }}" class="row" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:18px 0 30px">
            @foreach (['arrival' => $arrival->toDateString(), 'departure' => $departure->toDateString(), 'adults' => $adults, 'children' => $children] as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <div class="field" style="max-width:220px"><label for="promo">Promo code</label><input id="promo" name="promo" value="{{ $promo }}" placeholder="e.g. STAY4"></div>
            <button class="btn btn-sm" type="submit" style="min-height:48px">Apply code</button>
            @if ($offer)<span class="alert alert-success" style="margin:0"><x-icon name="check-circle" /> {{ $offer->title }} — {{ $offer->label() }} applied where eligible</span>@elseif($promoInvalid)<span class="alert alert-danger" style="margin:0">That code isn't valid.</span>@endif
        </form>

        <h1 style="font-size:clamp(28px,3.4vw,40px);margin-bottom:6px">{{ $nights }} night{{ $nights > 1 ? 's' : '' }} · {{ fmt_date($arrival, 'D d M') }} – {{ fmt_date($departure, 'D d M Y') }}</h1>
        <p class="muted">{{ $adults }} adult{{ $adults > 1 ? 's' : '' }}{{ $children ? ', '.$children.' child'.($children > 1 ? 'ren' : '') : '' }} · prices include service charge and tax</p>

        @foreach ($types as $t)
            <article class="result reveal" style="{{ (! $t->fits || ! $t->available_count) ? 'opacity:.7' : '' }}">
                <a href="{{ route('site.villa', $t) }}"><img src="{{ $t->coverUrl() }}" alt="{{ $t->name }}" loading="lazy" data-fallback="{{ asset('assets/img/placeholder-villa.svg') }}"></a>
                <div class="body">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                        <div><h2 style="font-size:28px">{{ $t->name }}</h2><div class="muted small">Sleeps {{ $t->max_adults }} adults{{ $t->max_children ? ' + '.$t->max_children.' children' : '' }} · {{ $t->size_sqm }} m² · {{ $t->bed_configuration }}</div></div>
                        @if (! $t->available_count)<span class="chip" style="background:#FBE7E5;color:#A2332B">Sold out for these dates</span>
                        @elseif (! $t->fits)<span class="chip">Too many guests for this villa</span>
                        @elseif ($t->available_count <= 1)<span class="chip" style="background:var(--brass-soft);color:var(--brass)">Only 1 left</span>
                        @else<span class="chip" style="background:var(--accent-soft);color:var(--accent)">{{ $t->available_count }} available</span>@endif
                    </div>
                    <div class="chips">@foreach ($t->facilities->take(5) as $f)<span class="chip"><x-icon :name="$f->icon" /> {{ $f->name }}</span>@endforeach</div>
                    @if ($t->fits && $t->available_count)
                        <div class="plans">
                            @foreach ($plans as $p)
                                @php $q = $t->quotes[$p->id]; @endphp
                                <div class="plan">
                                    <div><strong>{{ $p->name }}</strong><div class="small muted">{{ $p->description }}</div>
                                        @if ($q['discount'] > 0)<div class="small" style="color:var(--accent)">You save <x-price :amount="$q['discount']" /></div>@endif
                                        @foreach ($q['errors'] as $e)<div class="small error">{{ $e }}</div>@endforeach</div>
                                    <div class="p"><x-price :amount="$q['grand_total']" /><div class="small muted" style="font:400 13px var(--sans)"><x-price :amount="$q['avg_nightly']" /> avg / night</div></div>
                                    @if ($q['ok'])
                                        <a class="btn btn-primary btn-sm" href="{{ route('book.details', ['type' => $t->id, 'plan' => $p->id, 'arrival' => $arrival->toDateString(), 'departure' => $departure->toDateString(), 'adults' => min($adults, $t->max_adults), 'children' => min($children, $t->max_children), 'promo' => $promo]) }}">Select</a>
                                    @else<span class="small muted">Not available</span>@endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </article>
        @endforeach
        <p class="center muted small">Travelling as a larger group? <a href="{{ route('site.contact') }}">Send us an enquiry</a> or <a href="{{ whatsapp_link('Hello, we are a group looking for villas from '.$arrival->format('d M').' to '.$departure->format('d M')) }}" target="_blank" rel="noopener">message us on WhatsApp</a>.</p>
    </div>
</section>
@endsection
