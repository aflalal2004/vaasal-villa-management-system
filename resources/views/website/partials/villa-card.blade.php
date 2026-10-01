<a class="villa-card reveal {{ $delay ?? '' }}" href="{{ route('site.villa', $t) }}">
    <div class="img">
        <img src="{{ $t->coverUrl() }}" alt="{{ $t->name }}" loading="lazy" decoding="async" data-fallback="{{ asset('assets/img/placeholder-villa.svg') }}">
        <span class="tag">{{ $t->villas_count ?? $t->villas->count() }} villa{{ ($t->villas_count ?? $t->villas->count()) === 1 ? '' : 's' }}</span>
    </div>
    <div class="body">
        <h3>{{ $t->name }}</h3>
        <div class="meta"><span>{{ $t->max_adults }} adults{{ $t->max_children ? ' + '.$t->max_children.' child'.($t->max_children > 1 ? 'ren' : '') : '' }}</span><span>{{ $t->bedrooms }} bedroom{{ $t->bedrooms > 1 ? 's' : '' }}</span>@if($t->size_sqm)<span>{{ $t->size_sqm }} m²</span>@endif</div>
        <p class="small muted" style="margin:0">{{ $t->short_description }}</p>
        <div class="chips">@foreach ($t->facilities->take(3) as $f)<span class="chip"><x-icon :name="$f->icon" /> {{ $f->name }}</span>@endforeach</div>
        <div class="price"><span class="small muted">from</span><span><strong><x-price :amount="$t->base_rate" /></strong> <span class="small muted">/ night</span></span></div>
    </div>
</a>
