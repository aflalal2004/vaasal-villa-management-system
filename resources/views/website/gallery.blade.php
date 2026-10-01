@extends('layouts.site')
@section('title', 'Gallery')
@section('header_class', 'always')
@section('content')
<section class="block" style="padding-top:160px">
    <div class="wrap">
        <div class="section-head"><div><span class="eyebrow">Gallery</span><h1 style="font-size:clamp(36px,5vw,60px)">Villas, gardens, table & sea</h1></div></div>
        <div class="filter-tabs" data-filter-group="gallery-grid" role="tablist">
            <button type="button" class="on" data-filter="all">All</button>
            @foreach ($categories as $c)<button type="button" data-filter="{{ $c }}">{{ ucfirst($c) }}</button>@endforeach
        </div>
        <div class="masonry" id="gallery-grid">
            @foreach ($items as $g)
                <a href="{{ $g->url() }}" data-lightbox="gallery" data-caption="{{ $g->title }}" data-cat="{{ $g->category }}" @if($g->type === 'video') data-type="video" @endif class="reveal">
                    @if ($g->type === 'video')
                        <div class="video-tile"><x-icon name="play" /></div>
                    @else
                        <img src="{{ $g->thumbUrl() }}" alt="{{ $g->title }}" loading="lazy" decoding="async" data-fallback="{{ asset('assets/img/placeholder-villa.svg') }}">
                    @endif
                    <span class="cap">{{ $g->title }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endsection
