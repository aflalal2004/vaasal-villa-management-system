@extends('layouts.admin')
@section('title', 'Villa types')
@section('content')
<x-page-header title="Villa types" sub="Types carry capacity, base rate, facilities and photos. Individual villas inherit them.">
    <button class="btn" type="button" data-modal-open="#facility-modal"><x-icon name="plus" /> Facility</button>
    <a class="btn btn-primary" href="{{ route('admin.villa-types.create') }}"><x-icon name="plus" /> New type</a>
</x-page-header>
<div class="grid cols-2">
    @foreach ($types as $t)
        <div class="card" style="overflow:hidden">
            <img src="{{ $t->coverUrl() }}" alt="{{ $t->name }}" loading="lazy" style="width:100%;height:180px;object-fit:cover;display:block">
            <div class="card-body stack" style="gap:8px">
                <div class="row between"><h2>{{ $t->name }}</h2>@if(! $t->is_active)<x-badge status="inactive" />@endif</div>
                <div class="small muted">{{ $t->villas_count }} villa(s) · {{ $t->max_adults }} adults / {{ $t->max_children }} children · {{ $t->bedrooms }} bed · {{ $t->size_sqm }} m² · from {{ money($t->base_rate) }}</div>
                <p class="small" style="margin:0">{{ $t->short_description }}</p>
                <div class="row">@foreach ($t->facilities->take(6) as $f)<span class="chip">{{ $f->name }}</span>@endforeach</div>
                <div class="row"><a class="btn btn-sm" href="{{ route('admin.villa-types.edit', $t) }}"><x-icon name="edit" /> Edit & media</a>
                    <a class="btn btn-sm btn-ghost" href="{{ route('site.villa', $t) }}" target="_blank">View on website</a></div>
            </div>
        </div>
    @endforeach
</div>
<x-modal id="facility-modal" title="Add a facility">
    <form method="post" action="{{ route('admin.facilities.store') }}">
        @csrf
        <div class="modal-body form-grid">
            <x-input name="name" label="Name" required col="f-12" />
            <x-select name="category" label="Category" :options="['outdoor' => 'Outdoor', 'comfort' => 'Comfort', 'bath' => 'Bathroom', 'service' => 'Service', 'general' => 'General']" col="f-6" />
            <x-select name="icon" label="Icon" :options="['check' => 'Check', 'waves' => 'Pool', 'wifi' => 'Wi-Fi', 'snow' => 'AC', 'tv' => 'TV', 'coffee' => 'Coffee', 'leaf' => 'Garden', 'car' => 'Car', 'utensils' => 'Dining', 'lock' => 'Safe', 'sparkles' => 'Other']" col="f-6" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Add</button></div>
    </form>
</x-modal>
@endsection
