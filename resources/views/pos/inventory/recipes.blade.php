@extends('layouts.pos')
@section('title', 'Recipes')
@section('content')
<x-page-header title="Recipes" :crumbs="['Inventory' => route('pos.inventory.items.index')]" sub="Ingredient quantity per portion. When a check is paid, these quantities are deducted from stock." />
<div class="grid cols-2">
    @foreach ($items as $mi)
        <div class="card">
            <div class="card-head"><h2>{{ $mi->name }}</h2><span class="small muted">{{ $mi->category->name }} · {{ money($mi->price) }}</span></div>
            <ul class="list">
                @forelse ($mi->recipe as $r)
                    <li class="small">{{ $r->stockItem->name }} <span class="spacer"></span> {{ rtrim(rtrim(number_format($r->quantity, 3), '0'), '.') }} {{ $r->stockItem->unit }}
                        <form method="post" action="{{ route('pos.inventory.recipes.destroy', $r) }}">@csrf @method('delete')<button class="btn btn-sm btn-ghost" type="submit" aria-label="Remove">×</button></form></li>
                @empty
                    <li class="muted small">No recipe — sales don't deplete stock.</li>
                @endforelse
            </ul>
            <form method="post" action="{{ route('pos.inventory.recipes.save') }}" class="card-body row" style="border-top:1px solid var(--line-2)">
                @csrf <input type="hidden" name="menu_item_id" value="{{ $mi->id }}">
                <select name="stock_item_id" aria-label="Ingredient" style="flex:2">@foreach ($stock as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->unit }})</option>@endforeach</select>
                <input type="number" step="0.001" min="0.001" name="quantity" placeholder="Qty" required aria-label="Quantity" style="flex:1">
                <button class="btn btn-sm" type="submit">Add</button>
            </form>
        </div>
    @endforeach
</div>
@endsection
