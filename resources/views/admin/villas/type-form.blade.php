@extends('layouts.admin')
@section('title', $type->exists ? 'Edit villa type' : 'New villa type')
@section('content')
<x-page-header :title="$type->exists ? $type->name : 'New villa type'" :crumbs="['Villa types' => route('admin.villa-types.index')]" />
<div class="grid cols-main">
    <form method="post" action="{{ $type->exists ? route('admin.villa-types.update', $type) : route('admin.villa-types.store') }}" class="card">
        @csrf @if($type->exists) @method('put') @endif
        <div class="card-body form-grid">
            <x-input name="name" label="Name" :value="$type->name" required col="f-8" />
            <x-input name="sort_order" type="number" label="Sort order" :value="$type->sort_order" col="f-4" />
            <x-input name="short_description" label="Short description (cards & search results)" :value="$type->short_description" col="f-12" />
            <x-textarea name="description" label="Full description" :value="$type->description" rows="5" />
            <x-input name="max_adults" type="number" min="1" label="Max adults" :value="$type->max_adults" required col="f-3" />
            <x-input name="max_children" type="number" min="0" label="Max children" :value="$type->max_children ?? 0" required col="f-3" />
            <x-input name="base_occupancy" type="number" min="1" label="Adults included in rate" :value="$type->base_occupancy" required col="f-3" />
            <x-input name="size_sqm" type="number" min="0" label="Size (m²)" :value="$type->size_sqm" col="f-3" />
            <x-input name="bedrooms" type="number" min="0" label="Bedrooms" :value="$type->bedrooms" required col="f-3" />
            <x-input name="bathrooms" type="number" min="0" label="Bathrooms" :value="$type->bathrooms" required col="f-3" />
            <x-input name="bed_configuration" label="Beds" :value="$type->bed_configuration" col="f-6" />
            <x-input name="base_rate" type="number" step="0.01" min="0" label="Base rate / night" :value="$type->base_rate" required col="f-4" help="Used outside defined seasons" />
            <x-input name="extra_adult_rate" type="number" step="0.01" min="0" label="Extra adult / night" :value="$type->extra_adult_rate" col="f-4" />
            <x-input name="extra_child_rate" type="number" step="0.01" min="0" label="Child / night" :value="$type->extra_child_rate" col="f-4" />
            <div class="field f-12">
                <span class="label">Facilities</span>
                <div class="row" style="gap:6px 16px">
                    @foreach ($facilities as $f)
                        <label class="check"><input type="checkbox" name="facilities[]" value="{{ $f->id }}" @checked($type->facilities?->contains($f->id))> {{ $f->name }}</label>
                    @endforeach
                </div>
            </div>
            <x-checkbox name="is_active" label="Active on website & booking engine" :checked="$type->is_active" col="f-12" />
        </div>
        <div class="card-foot"><a class="btn" href="{{ route('admin.villa-types.index') }}">Cancel</a><button class="btn btn-primary" type="submit">Save type</button></div>
    </form>

    @if ($type->exists)
    <div class="card">
        <div class="card-head"><h2>Photos & video</h2></div>
        <div class="card-body stack">
            <div class="grid cols-2" style="gap:10px">
                @foreach ($type->media as $m)
                    <div style="position:relative">
                        @if ($m->type === 'image')
                            <img src="{{ $m->url() }}" alt="{{ $m->alt }}" loading="lazy" style="width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:8px;{{ $m->is_cover ? 'outline:3px solid var(--accent)' : '' }}">
                        @else
                            <div class="chip" style="display:block;padding:14px">▶ {{ \Illuminate\Support\Str::limit($m->path, 34) }}</div>
                        @endif
                        <div class="row" style="margin-top:4px">
                            @if ($m->type === 'image' && ! $m->is_cover)<form method="post" action="{{ route('admin.media.cover', $m) }}">@csrf<button class="btn btn-sm" type="submit">Make cover</button></form>@endif
                            <form method="post" action="{{ route('admin.media.destroy', $m) }}" data-confirm="Remove this media item?">@csrf @method('delete')<button class="btn btn-sm btn-ghost" type="submit" aria-label="Remove"><x-icon name="trash" /></button></form>
                        </div>
                    </div>
                @endforeach
            </div>
            <form method="post" action="{{ route('admin.villa-types.media', $type) }}" enctype="multipart/form-data" class="stack" style="gap:8px">
                @csrf
                <label class="label" for="photos">Upload photos (JPG/PNG/WebP, max 5 MB each)</label>
                <input id="photos" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp">
                <label class="label" for="video_url">Video tour link (YouTube or Vimeo)</label>
                <input id="video_url" type="url" name="video_url" placeholder="https://www.youtube.com/watch?v=…">
                <button class="btn btn-sm" type="submit"><x-icon name="upload" /> Add media</button>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
