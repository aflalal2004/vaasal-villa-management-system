@extends('layouts.pos')
@section('title', $item->exists ? 'Edit '.$item->name : 'New menu item')
@section('content')
<x-page-header :title="$item->exists ? $item->name : 'New menu item'" :crumbs="['Menu' => route('pos.menu.index')]" />
<form method="post" action="{{ $item->exists ? route('pos.menu.items.update', $item) : route('pos.menu.items.store') }}" enctype="multipart/form-data" class="card" style="max-width:900px">
    @csrf @if($item->exists) @method('put') @endif
    <div class="card-body form-grid">
        <x-input name="name" label="Name" :value="$item->name" required col="f-8" />
        <x-input name="code" label="PLU / code" :value="$item->code" col="f-4" />
        <x-select name="menu_category_id" label="Category" :options="$categories" :value="$item->menu_category_id" required col="f-6" />
        <x-select name="station" label="Station override" :options="['kitchen' => 'Kitchen', 'bar' => 'Bar', 'pastry' => 'Pastry']" :value="$item->station" placeholder="Use category station" col="f-6" />
        <x-input name="price" type="number" step="0.01" min="0" label="Price" :value="$item->price" required col="f-4" />
        <x-input name="cost" type="number" step="0.01" min="0" label="Cost (food cost)" :value="$item->cost" col="f-4" />
        <x-input name="prep_minutes" type="number" min="0" label="Prep minutes" :value="$item->prep_minutes" col="f-4" />
        <x-textarea name="description" label="Description" :value="$item->description" rows="2" />
        <x-input name="allergens" label="Allergens" :value="$item->allergens" col="f-8" placeholder="e.g. shellfish, nuts" />
        <x-input name="sort_order" type="number" label="Sort" :value="$item->sort_order" col="f-4" />
        <div class="field f-12"><span class="label">Modifier groups</span>
            <div class="row">@foreach ($groups as $g)<label class="check"><input type="checkbox" name="groups[]" value="{{ $g->id }}" @checked($item->modifierGroups?->contains($g->id))> {{ $g->name }}</label>@endforeach</div></div>
        <div class="field f-6"><label for="image">Photo</label><input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"></div>
        <x-checkbox name="is_available" label="Available now" :checked="$item->is_available" col="f-3" />
        <x-checkbox name="is_active" label="On the menu" :checked="$item->is_active" col="f-3" />
    </div>
    <div class="card-foot">
        @if ($item->exists)<button class="btn btn-danger" type="submit" form="del-item">Remove</button><span class="spacer"></span>@endif
        <a class="btn" href="{{ route('pos.menu.index') }}">Cancel</a><button class="btn btn-primary" type="submit">Save item</button>
    </div>
</form>
@if ($item->exists)<form id="del-item" method="post" action="{{ route('pos.menu.items.destroy', $item) }}" data-confirm="Remove {{ $item->name }} from the menu?" data-danger>@csrf @method('delete')</form>@endif
@endsection
