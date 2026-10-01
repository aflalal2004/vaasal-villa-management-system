@extends('layouts.site')
@section('title', 'Our story')
@section('content')
<section class="page-hero" style="background-image:url('https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=2000&q=70')">
    <div class="wrap"><span class="eyebrow" style="color:#E9C98A">About</span><h1>Vaasal means threshold.</h1><p class="lead" style="margin-top:14px">A small, family-run villa retreat in Jaffna, Sri Lanka.</p></div>
</section>
<section class="block">
    <div class="wrap split">
        <div class="reveal">
            @foreach (preg_split("/\n\s*\n/", setting('site_about', '')) as $para)<p class="{{ $loop->first ? 'lead' : '' }}">{{ $para }}</p>@endforeach
            <div class="counters">
                <div class="counter"><div class="num" data-count="{{ setting('stat_years', 9) }}">{{ setting('stat_years', 9) }}</div><div class="lbl">years</div></div>
                <div class="counter"><div class="num" data-count="{{ \App\Models\Villa::where('is_active', true)->count() }}">{{ \App\Models\Villa::where('is_active', true)->count() }}</div><div class="lbl">villas</div></div>
                <div class="counter"><div class="num" data-count="{{ \App\Models\Employee::where('status', 'active')->count() }}">{{ \App\Models\Employee::where('status', 'active')->count() }}</div><div class="lbl">team members</div></div>
            </div>
        </div>
        <div class="stack-img reveal d2">
            @foreach ($gallery as $g)<img src="{{ $g->url() }}" alt="{{ $g->title }}" loading="lazy" data-fallback="{{ asset('assets/img/placeholder-villa.svg') }}">@endforeach
        </div>
    </div>
</section>
<section class="block tint">
    <div class="wrap">
        <div class="center reveal" style="margin-bottom:40px"><span class="eyebrow">How we host</span><h2>Small details, done every day</h2></div>
        <div class="cards" style="grid-template-columns:repeat(auto-fill,minmax(250px,1fr))">
            @foreach ([['leaf', 'Local first', 'Fish from the Jaffna market, oils from a family Siddha garden, palmyra crafts by Jaffna artisans.'],
                ['broom', 'Cared for twice daily', 'Morning service and evening turndown, with every villa inspected before a guest arrives.'],
                ['key', 'Private & secure', 'Keyless RFID cards for every villa, CCTV at the gate and a night team on site.'],
                ['whatsapp', 'One message away', 'Our reception team answers WhatsApp from 6am to midnight — dinner, drivers or a doctor.']] as $i => [$ic, $t, $d])
                <div class="service reveal d{{ $i % 3 }}"><span class="ic"><x-icon :name="$ic" /></span><div><h3 style="font-size:20px">{{ $t }}</h3><p class="small muted" style="margin:8px 0 0">{{ $d }}</p></div></div>
            @endforeach
        </div>
    </div>
</section>
@if (setting('youtube_tour_id'))
<section class="block"><div class="wrap">
    <div class="center reveal" style="margin-bottom:30px"><span class="eyebrow">Film</span><h2>Walk through the property</h2></div>
    <div class="video-embed reveal"><button type="button" class="video-poster" data-yt="{{ setting('youtube_tour_id') }}" style="background-image:url('https://i.ytimg.com/vi/{{ setting('youtube_tour_id') }}/hqdefault.jpg')" aria-label="Play video"><span><x-icon name="play" /></span></button></div>
</div></section>
@endif
@endsection
