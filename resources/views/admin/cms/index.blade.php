@extends('layouts.admin')
@section('title', 'Website content')
@section('content')
<x-page-header title="Website content" sub="Text, photos, services and reviews on the public website. Villas, offers and rates are managed in their own modules.">
    <a class="btn" href="{{ route('home') }}" target="_blank"><x-icon name="globe" /> View website</a>
</x-page-header>
<div class="tabs">@foreach (['content' => 'Pages & contact', 'gallery' => 'Gallery', 'services' => 'Services', 'reviews' => 'Testimonials', 'media' => 'Stock media'] as $k => $v)<a href="?tab={{ $k }}" @class(['active' => $tab === $k])>{{ $v }}</a>@endforeach<a href="{{ route('admin.social.index') }}">Social media</a></div>

@if ($tab === 'content')
<form method="post" action="{{ route('admin.cms.content') }}" class="card">
    @csrf @method('put')
    <div class="card-body form-grid">
        <x-input name="site_hero_title" label="Home hero headline" :value="$values['site_hero_title']" col="f-12" />
        <x-textarea name="site_hero_text" label="Hero text" :value="$values['site_hero_text']" rows="2" />
        <x-input name="site_hero_video" type="url" label="Hero background video (MP4 URL, optional)" :value="$values['site_hero_video']" col="f-8" help="Muted, looping; images are used on mobile and when empty." />
        <x-input name="youtube_tour_id" label="YouTube tour video ID" :value="$values['youtube_tour_id']" col="f-4" help="e.g. dQw4w9WgXcQ" />
        <x-input name="site_tagline" label="Tagline" :value="$values['site_tagline']" col="f-12" />
        <x-textarea name="site_about" label="About page text" :value="$values['site_about']" rows="6" />
        <x-input name="stat_guests" label="Counter: guests hosted" :value="$values['stat_guests']" col="f-4" />
        <x-input name="stat_rating" label="Counter: guest rating" :value="$values['stat_rating']" col="f-4" />
        <x-input name="stat_years" label="Counter: years welcoming guests" :value="$values['stat_years']" col="f-4" />
        <x-input name="contact_email" type="email" label="Contact email" :value="$values['contact_email']" col="f-4" />
        <x-input name="contact_phone" label="Contact phone" :value="$values['contact_phone']" col="f-4" />
        <x-input name="whatsapp_number" label="WhatsApp number (international, digits)" :value="$values['whatsapp_number']" col="f-4" />
        <x-input name="contact_address" label="Location / address" :value="$values['contact_address']" col="f-12" help="Shown in the header, footer, contact page, invoices and emails. Social links are managed under Social media." />
        <x-input name="site_hero_image" type="url" label="Hero image URL (optional)" :value="$values['site_hero_image']" col="f-12" help="Pick one from Stock media or paste a URL. Empty = villa photos slideshow." />
        <x-input name="seo_title" label="SEO title" :value="$values['seo_title']" col="f-6" help="Up to 70 characters." />
        <x-input name="seo_keywords" label="SEO keywords" :value="$values['seo_keywords']" col="f-6" />
        <x-textarea name="seo_description" label="SEO description" :value="$values['seo_description']" rows="2" help="Up to 170 characters; used by search engines and link previews." />
    </div>
    <div class="card-foot"><button class="btn btn-primary" type="submit">Save content</button></div>
</form>
@elseif ($tab === 'gallery')
<div class="grid cols-main">
    <div class="card"><div class="card-body grid cols-3" style="gap:10px">
        @foreach ($gallery as $g)
            <figure style="margin:0">
                @if ($g->type === 'image')<img src="{{ $g->thumbUrl() }}" alt="{{ $g->title }}" loading="lazy" style="width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:8px">
                @else<div class="chip" style="display:flex;gap:6px;justify-content:center;padding:24px 10px"><x-icon name="video" /> Video</div>@endif
                <figcaption class="small row between">{{ $g->title }} · {{ $g->category }}
                    <form method="post" action="{{ route('admin.cms.gallery.destroy', $g) }}" data-confirm="Remove from gallery?">@csrf @method('delete')<button class="btn btn-sm btn-ghost" type="submit" aria-label="Remove"><x-icon name="trash" /></button></form></figcaption>
            </figure>
        @endforeach
    </div></div>
    <form method="post" action="{{ route('admin.cms.gallery.store') }}" enctype="multipart/form-data" class="card" style="align-self:start">
        @csrf
        <div class="card-head"><h2>Add to gallery</h2></div>
        <div class="card-body form-grid">
            <x-select name="category" label="Category" :options="['villas' => 'Villas', 'dining' => 'Dining', 'grounds' => 'Grounds', 'spa' => 'Spa', 'experiences' => 'Experiences']" col="f-12" />
            <x-input name="title" label="Caption" col="f-12" />
            <div class="field f-12"><label for="photos">Photos</label><input id="photos" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp"></div>
            <x-input name="video_url" type="url" label="…or YouTube / Vimeo link" col="f-12" />
        </div>
        <div class="card-foot"><button class="btn btn-primary btn-sm" type="submit">Upload</button></div>
    </form>
</div>
@elseif ($tab === 'services')
<div class="stack">
    @foreach ($services->concat([new \App\Models\SiteService(['is_active' => true, 'icon' => 'star'])]) as $s)
        <form method="post" action="{{ $s->exists ? route('admin.cms.services.update', $s) : route('admin.cms.services.store') }}" enctype="multipart/form-data" class="card">
            @csrf @if($s->exists) @method('put') @endif
            <div class="card-head"><h2>{{ $s->exists ? $s->title : 'New service' }}</h2></div>
            <div class="card-body form-grid">
                <x-input name="title" label="Title" :value="$s->title" required col="f-4" :id="'st'.$s->id" />
                <x-input name="summary" label="Summary" :value="$s->summary" col="f-8" :id="'ss'.$s->id" />
                <x-textarea name="description" label="Description" :value="$s->description" rows="2" :id="'sd'.$s->id" />
                <x-select name="icon" label="Icon" :options="['utensils' => 'Dining', 'leaf' => 'Spa', 'car' => 'Transport', 'globe' => 'Excursions', 'sparkles' => 'Laundry', 'star' => 'Special', 'plane' => 'Airport']" :value="$s->icon" col="f-3" :id="'si'.$s->id" />
                <x-select name="charge_item_id" label="Linked service charge" :options="$chargeItems" :value="$s->charge_item_id" placeholder="—" col="f-3" :id="'sc'.$s->id" />
                <x-input name="price_from" type="number" step="0.01" label="Price from" :value="$s->price_from" col="f-3" :id="'sp'.$s->id" />
                <x-input name="sort_order" type="number" label="Sort" :value="$s->sort_order" col="f-3" :id="'so'.$s->id" />
                <x-input name="image" type="url" label="Image URL" :value="str_starts_with((string) $s->image, 'http') ? $s->image : ''" col="f-6" :id="'su'.$s->id" />
                <div class="field f-6"><label>…or upload</label><input type="file" name="image_file" accept="image/jpeg,image/png,image/webp"></div>
                @if($s->exists)<x-checkbox name="is_active" label="Shown on website" :checked="$s->is_active" col="f-12" />@endif
            </div>
            <div class="card-foot"><button class="btn btn-sm btn-primary" type="submit">Save</button></div>
        </form>
    @endforeach
</div>
@elseif ($tab === 'reviews')
<div class="grid cols-main">
    <div class="card"><ul class="list">
        @foreach ($testimonials as $t)
            <li style="align-items:flex-start"><span><strong>{{ $t->guest_name }}</strong> · {{ $t->country }} · <span class="stars-inline" aria-label="{{ $t->rating }} out of 5">@for ($i = 0; $i < $t->rating; $i++)<x-icon name="star-fill" />@endfor</span> <span class="muted small">{{ $t->source }}</span><br><span class="small">{{ $t->content }}</span></span>
                <span class="spacer"></span><form method="post" action="{{ route('admin.cms.testimonials.destroy', $t) }}" data-confirm="Remove this testimonial?">@csrf @method('delete')<button class="btn btn-sm btn-ghost" type="submit" aria-label="Remove"><x-icon name="trash" /></button></form></li>
        @endforeach
    </ul></div>
    <form method="post" action="{{ route('admin.cms.testimonials.store') }}" class="card" style="align-self:start">
        @csrf
        <div class="card-head"><h2>Add testimonial</h2></div>
        <div class="card-body form-grid">
            <x-input name="guest_name" label="Guest name" required col="f-6" /><x-input name="country" label="Country" col="f-6" />
            <x-select name="rating" label="Rating" :options="[5 => '5 stars', 4 => '4 stars', 3 => '3 stars']" col="f-6" /><x-input name="source" label="Source" col="f-6" placeholder="Google, TripAdvisor…" />
            <x-textarea name="content" label="Review" required rows="3" />
        </div>
        <div class="card-foot"><button class="btn btn-sm btn-primary" type="submit">Add</button></div>
    </form>
</div>
@elseif ($tab === 'media')
@php $media = app(\App\Modules\Website\Services\MediaLibraryService::class); $prov = $media->providers(); @endphp
<div class="card mb">
    <div class="card-body">
        <form class="row" id="media-search" style="gap:10px" role="search">
            <input type="search" name="q" value="Jaffna villa pool" aria-label="Search photos and videos" style="flex:1;min-width:220px" maxlength="80">
            <select name="type" aria-label="Type"><option value="photo">Photos</option><option value="video">Videos</option></select>
            <button class="btn btn-primary" type="submit"><x-icon name="search" /> Search</button>
        </form>
        <p class="small muted" style="margin:10px 0 0">
            Sources: <x-badge :tone="$prov['unsplash'] ? 'success' : 'neutral'" :label="'Unsplash '.($prov['unsplash'] ? 'connected' : 'no key')" />
            <x-badge :tone="$prov['pexels'] ? 'success' : 'neutral'" :label="'Pexels '.($prov['pexels'] ? 'connected' : 'no key')" />
            Keys live in <code>.env</code> (<code>UNSPLASH_ACCESS_KEY</code>, <code>PEXELS_API_KEY</code>) and are never sent to the browser. Without keys, your own gallery is shown.
        </p>
    </div>
</div>
<div id="media-status" class="small muted mb" aria-live="polite"></div>
<div id="media-grid" class="media-grid"></div>
@push('scripts')
<script>
(function () {
    const form = document.getElementById('media-search'), grid = document.getElementById('media-grid'), status = document.getElementById('media-status');
    const searchUrl = @json(route('admin.cms.media.search')), useUrl = @json(route('admin.cms.media.use'));
    async function run() {
        const q = form.q.value.trim(), type = form.type.value;
        status.textContent = 'Searching…'; grid.innerHTML = '';
        try {
            const d = await VV.api(searchUrl + '?' + new URLSearchParams({ q, type }));
            status.textContent = d.items.length + ' result(s) from ' + (d.fallback ? 'your gallery (no API key or provider unavailable)' : d.provider) + '.';
            grid.innerHTML = d.items.map((m, i) => `
                <figure class="media-item">
                    <img src="${VV.esc(m.thumb)}" alt="${VV.esc(m.alt || '')}" loading="lazy">
                    ${m.type === 'video' ? '<span class="media-kind">Video' + (m.duration ? ' · ' + m.duration + 's' : '') + '</span>' : ''}
                    <figcaption>
                        <span class="small muted">${m.author ? VV.esc(m.author) : ''}${m.provider !== 'library' ? ' · ' + VV.esc(m.provider) : ''}</span>
                        <div class="row" style="gap:6px">
                            <button type="button" class="btn btn-sm" data-use="gallery" data-i="${i}">Add to gallery</button>
                            ${m.type === 'video' ? `<button type="button" class="btn btn-sm btn-primary" data-use="hero_video" data-i="${i}">Hero video</button>` : `<button type="button" class="btn btn-sm btn-primary" data-use="hero_image" data-i="${i}">Hero image</button>`}
                        </div>
                    </figcaption>
                </figure>`).join('') || '<p class="muted">No results — try another search.</p>';
            grid.onclick = async e => {
                const b = e.target.closest('[data-use]'); if (!b) return;
                const m = d.items[b.dataset.i];
                b.disabled = true;
                try {
                    const r = await VV.api(useUrl, 'POST', { action: b.dataset.use, url: m.url, thumb: m.thumb, video_url: m.video_url, title: m.alt, author: m.author, provider: m.provider, category: 'grounds' });
                    VV.toast(r.message, 'success');
                } catch (err) { VV.toast(err.message, 'error'); } finally { b.disabled = false; }
            };
        } catch (err) { status.textContent = ''; VV.toast(err.message, 'error'); }
    }
    form.addEventListener('submit', e => { e.preventDefault(); run(); });
    run();
})();
</script>
@endpush
@endif
@endsection
