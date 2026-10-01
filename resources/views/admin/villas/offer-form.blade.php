@extends('layouts.admin')
@section('title', $offer->exists ? 'Edit offer' : 'New offer')
@section('content')
<x-page-header :title="$offer->exists ? $offer->title : 'New offer'" :crumbs="['Offers' => route('admin.offers.index')]" />
<form method="post" action="{{ $offer->exists ? route('admin.offers.update', $offer) : route('admin.offers.store') }}" enctype="multipart/form-data" class="card" style="max-width:900px">
    @csrf @if($offer->exists) @method('put') @endif
    <div class="card-body form-grid">
        <x-input name="title" label="Title" :value="$offer->title" required col="f-8" />
        <x-input name="promo_code" label="Promo code (optional)" :value="$offer->promo_code" col="f-4" help="Letters and numbers only" />
        <x-input name="summary" label="Summary" :value="$offer->summary" col="f-12" />
        <x-textarea name="description" label="Description & conditions" :value="$offer->description" rows="4" />
        <x-select name="discount_type" label="Discount type" :options="['percent' => 'Percentage', 'fixed' => 'Fixed amount']" :value="$offer->discount_type" required col="f-4" />
        <x-input name="discount_value" type="number" step="0.01" min="0" label="Discount value" :value="$offer->discount_value" required col="f-4" />
        <x-input name="min_nights" type="number" min="1" label="Minimum nights" :value="$offer->min_nights" required col="f-4" />
        <x-input name="valid_from" type="date" label="Bookable from" :value="$offer->valid_from" col="f-4" />
        <x-input name="valid_to" type="date" label="Bookable until" :value="$offer->valid_to" col="f-4" />
        <x-input name="max_uses" type="number" min="1" label="Usage limit" :value="$offer->max_uses" col="f-4" />
        <div class="field f-6"><label for="image_file">Image upload</label><input type="file" id="image_file" name="image_file" accept="image/jpeg,image/png,image/webp"></div>
        <x-input name="image" type="url" label="…or image URL" :value="str_starts_with((string) $offer->image, 'http') ? $offer->image : ''" col="f-6" />
        <x-checkbox name="is_active" label="Active" :checked="$offer->is_active" col="f-6" />
        <x-checkbox name="is_featured" label="Feature on home page" :checked="$offer->is_featured" col="f-6" />
    </div>
    <div class="card-foot"><a class="btn" href="{{ route('admin.offers.index') }}">Cancel</a><button class="btn btn-primary" type="submit">Save offer</button></div>
</form>
@endsection
