@extends('layouts.admin')
@section('title', $item->exists ? 'Lost & found '.$item->item_no : 'Log found item')
@section('content')
<x-page-header :title="$item->exists ? $item->item_no.' · '.$item->description : 'Log a found item'" :crumbs="['Lost & found' => route('admin.lost-found.index')]" />
<form method="post" action="{{ $item->exists ? route('admin.lost-found.update', $item) : route('admin.lost-found.store') }}" enctype="multipart/form-data" class="card" style="max-width:860px">
    @csrf @if($item->exists) @method('put') @endif
    <div class="card-body form-grid">
        <x-input name="description" label="Description" :value="$item->description" required col="f-8" placeholder="e.g. Silver bracelet, black phone charger" />
        <x-select name="category" label="Category" :options="['electronics' => 'Electronics', 'jewellery' => 'Jewellery', 'clothing' => 'Clothing', 'documents' => 'Documents / ID', 'other' => 'Other']" :value="$item->category ?? 'other'" col="f-4" />
        <x-select name="villa_id" label="Villa" :options="$villas" :value="$item->villa_id" placeholder="Public area" col="f-4" />
        <x-input name="found_location" label="Exact location" :value="$item->found_location" col="f-4" />
        <x-input name="found_at" type="datetime-local" label="Found at" :value="$item->found_at" required col="f-4" />
        <x-select name="found_by" label="Found by" :options="$staff" :value="$item->found_by" placeholder="—" col="f-6" />
        <x-input name="storage_location" label="Stored at" :value="$item->storage_location" col="f-6" placeholder="e.g. Front office safe, shelf B" />
        <div class="field f-6"><label for="photo">Photo</label><input type="file" id="photo" name="photo" accept="image/*" capture="environment"></div>
        <x-select name="status" label="Status" :options="\App\Models\LostFoundItem::STATUSES" :value="$item->status" required col="f-6" />
        @if ($item->exists)<input type="hidden" name="guest_id" value="{{ $item->guest_id }}">@endif
        <x-input name="returned_to" label="Returned to / how (required when returned)" :value="$item->returned_to" col="f-12" placeholder="e.g. Couriered to guest, DHL 1234; collected by guest" />
        <x-textarea name="notes" label="Notes" :value="$item->notes" rows="2" />
    </div>
    <div class="card-foot"><a class="btn" href="{{ route('admin.lost-found.index') }}">Cancel</a><button class="btn btn-primary" type="submit">Save</button></div>
</form>
@endsection
