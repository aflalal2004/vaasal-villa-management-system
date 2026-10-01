@extends('layouts.admin')
@section('title', $villa->exists ? 'Edit villa' : 'New villa')
@section('content')
<x-page-header :title="$villa->exists ? 'Edit '.$villa->code : 'New villa'" :crumbs="['Villas' => route('admin.villas.index')]" />
<form method="post" action="{{ $villa->exists ? route('admin.villas.update', $villa) : route('admin.villas.store') }}" class="card" style="max-width:900px">
    @csrf @if($villa->exists) @method('put') @endif
    <div class="card-body form-grid">
        <x-input name="code" label="Villa code" :value="$villa->code" required col="f-3" help="Short code, e.g. P4" />
        <x-input name="name" label="Name" :value="$villa->name" required col="f-5" />
        <x-select name="villa_type_id" label="Villa type" :options="$types" :value="$villa->villa_type_id" required col="f-4" placeholder="Choose…" />
        <x-input name="zone" label="Zone / area" :value="$villa->zone" col="f-4" />
        <x-input name="rate_override" type="number" step="0.01" min="0" label="Rate override (optional)" :value="$villa->rate_override" col="f-4" help="Leave blank to use type & seasonal rates" />
        <x-input name="lock_ref" label="Door lock ID" :value="$villa->lock_ref" col="f-4" help="As configured in the lock system" />
        <x-textarea name="description" label="Description" :value="$villa->description" rows="4" />
        <x-textarea name="notes" label="Internal notes" :value="$villa->notes" rows="2" col="f-8" />
        <x-input name="sort_order" type="number" label="Sort order" :value="$villa->sort_order" col="f-2" />
        <x-checkbox name="is_active" label="Active (sellable)" :checked="$villa->is_active" col="f-2" />
    </div>
    <div class="card-foot">
        @if ($villa->exists)
            @perm('villas.manage')<button class="btn btn-danger" type="submit" form="archive-form">Archive villa</button>@endperm
            <span class="spacer"></span>
        @endif
        <a class="btn" href="{{ $villa->exists ? route('admin.villas.show', $villa) : route('admin.villas.index') }}">Cancel</a>
        <button class="btn btn-primary" type="submit">Save villa</button>
    </div>
</form>
@if ($villa->exists)
<form id="archive-form" method="post" action="{{ route('admin.villas.destroy', $villa) }}" data-confirm="Archive {{ $villa->code }}? It will no longer be sold; its history is kept." data-danger>@csrf @method('delete')</form>
@endif
@endsection
