@extends('layouts.pos')
@section('title', 'Inventory')
@section('content')
<x-page-header title="Inventory" sub="Sales deplete ingredients automatically through recipes. Receive stock, record wastage and run counts here.">
    <a class="btn" href="{{ route('pos.inventory.movements') }}"><x-icon name="history" /> Movements</a>
    @perm('inventory.manage')
        <a class="btn" href="{{ route('pos.inventory.recipes') }}"><x-icon name="utensils" /> Recipes</a>
        <a class="btn" href="{{ route('pos.inventory.suppliers.index') }}"><x-icon name="briefcase" /> Suppliers</a>
        <button class="btn" type="button" data-modal-open="#out-modal">Stock out / wastage</button>
        <button class="btn btn-primary" type="button" data-modal-open="#in-modal"><x-icon name="plus" /> Receive stock</button>
    @endperm
</x-page-header>
<div class="stats">
    <x-stat label="Stock value" :value="money($value)" hint="Weighted average cost" />
    <x-stat label="Low-stock items" :value="$lowCount" :href="route('pos.inventory.items.index', ['filter' => 'low'])" />
    <x-stat label="Wastage this month" :value="money($wasteMonth)" />
    <x-stat label="Items tracked" :value="$items->count()" />
</div>
<form class="filters" method="get">
    <div class="field" style="flex:2 1 200px"><label for="q">Search</label><input id="q" type="search" name="q" value="{{ request('q') }}"></div>
    <div class="field"><label for="category">Category</label><select id="category" name="category" data-autosubmit><option value="">All</option>@foreach ($categories as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach</select></div>
    <div class="field"><label for="filter">Show</label><select id="filter" name="filter" data-autosubmit><option value="">All items</option><option value="low" @selected(request('filter') === 'low')>Low stock only</option></select></div>
</form>
<form method="post" action="{{ route('pos.inventory.count') }}" class="card">
    @csrf
    <div class="table-wrap"><table class="table">
        <thead><tr><th>SKU</th><th>Item</th><th>Category</th><th>Supplier</th><th class="num">On hand</th><th class="num">Reorder at</th><th class="num">Unit cost</th><th class="num">Value</th>@perm('inventory.manage')<th class="num">Counted</th><th></th>@endperm</tr></thead>
        <tbody>
        @foreach ($items as $i)
            <tr>
                <td class="mono small">{{ $i->sku }}</td>
                <td>{{ $i->name }} @if($i->isLow())<x-badge tone="warning" label="Low" />@endif</td>
                <td>{{ $i->category }}</td><td class="small">{{ $i->supplier?->name }}</td>
                <td class="num" style="color:{{ $i->current_qty < 0 ? 'var(--crit)' : 'inherit' }}">{{ rtrim(rtrim(number_format($i->current_qty, 3), '0'), '.') }} {{ $i->unit }}</td>
                <td class="num">{{ rtrim(rtrim(number_format($i->reorder_level, 3), '0'), '.') }}</td>
                <td class="num">{{ money($i->unit_cost, null, false) }}</td>
                <td class="num">{{ money($i->current_qty * $i->unit_cost, null, false) }}</td>
                @perm('inventory.manage')
                <td class="num"><input type="number" step="0.001" min="0" name="counts[{{ $i->id }}]" style="width:90px" aria-label="Counted {{ $i->name }}"></td>
                <td class="actions"><button class="btn btn-sm btn-ghost" type="button" data-modal-open="#item-{{ $i->id }}" aria-label="Edit"><x-icon name="edit" /></button></td>
                @endperm
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @perm('inventory.manage')
    <div class="card-foot"><input type="text" name="reason" placeholder="Count reference (e.g. Month-end count)" aria-label="Count reason" style="max-width:320px"><button class="btn" type="submit">Post stock count</button>
        <button class="btn btn-primary" type="button" data-modal-open="#item-new"><x-icon name="plus" /> New stock item</button></div>
    @endperm
</form>

@perm('inventory.manage')
<x-modal id="in-modal" title="Receive stock (goods received note)" wide>
    <form method="post" action="{{ route('pos.inventory.stock-in') }}">
        @csrf
        <div class="modal-body form-grid">
            <x-select name="supplier_id" label="Supplier" :options="$suppliers" placeholder="—" col="f-6" />
            <x-input name="reference" label="Delivery note / invoice no." col="f-6" />
            @for ($r = 0; $r < 6; $r++)
                <div class="field f-6"><label for="l{{ $r }}">Item</label><select id="l{{ $r }}" name="lines[{{ $r }}][stock_item_id]"><option value="">—</option>@foreach ($items as $i)<option value="{{ $i->id }}">{{ $i->name }} ({{ $i->unit }})</option>@endforeach</select></div>
                <div class="field f-3"><label for="q{{ $r }}">Quantity</label><input id="q{{ $r }}" type="number" step="0.001" min="0" name="lines[{{ $r }}][quantity]"></div>
                <div class="field f-3"><label for="c{{ $r }}">Unit cost</label><input id="c{{ $r }}" type="number" step="0.01" min="0" name="lines[{{ $r }}][unit_cost]"></div>
            @endfor
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Receive</button></div>
    </form>
</x-modal>
<x-modal id="out-modal" title="Stock out / wastage">
    <form method="post" action="{{ route('pos.inventory.stock-out') }}">
        @csrf
        <div class="modal-body form-grid">
            <x-select name="stock_item_id" label="Item" :options="$items->mapWithKeys(fn ($i) => [$i->id => $i->name.' ('.$i->current_qty.' '.$i->unit.')'])" required col="f-12" />
            <x-select name="type" label="Type" :options="['waste' => 'Wastage (spoiled, expired, dropped)', 'out' => 'Issue to department']" col="f-6" />
            <x-input name="quantity" type="number" step="0.001" min="0.001" label="Quantity" required col="f-6" />
            <x-input name="reason" label="Reason" col="f-12" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Record</button></div>
    </form>
</x-modal>
@foreach ($items->concat([new \App\Models\StockItem(['unit' => 'kg', 'category' => 'Dry store'])]) as $i)
<x-modal :id="$i->exists ? 'item-'.$i->id : 'item-new'" :title="$i->exists ? 'Edit '.$i->name : 'New stock item'">
    <form method="post" action="{{ $i->exists ? route('pos.inventory.items.update', $i) : route('pos.inventory.items.store') }}">
        @csrf @if($i->exists) @method('put') @endif
        <div class="modal-body form-grid">
            <x-input name="sku" label="SKU" :value="$i->sku" required col="f-4" :id="'s'.$i->id" />
            <x-input name="name" label="Name" :value="$i->name" required col="f-8" :id="'n'.$i->id" />
            <x-input name="category" label="Category" :value="$i->category" required col="f-6" :id="'c'.$i->id" />
            <x-select name="unit" label="Unit" :options="['kg' => 'kg', 'g' => 'g', 'l' => 'litre', 'ml' => 'ml', 'pcs' => 'pieces', 'btl' => 'bottle', 'pack' => 'pack', 'box' => 'box']" :value="$i->unit" col="f-6" :id="'u'.$i->id" />
            <x-input name="reorder_level" type="number" step="0.001" min="0" label="Reorder level" :value="$i->reorder_level" required col="f-6" :id="'r'.$i->id" />
            <x-input name="unit_cost" type="number" step="0.01" min="0" label="Unit cost" :value="$i->unit_cost" required col="f-6" :id="'uc'.$i->id" />
            <x-select name="supplier_id" label="Main supplier" :options="$suppliers" :value="$i->supplier_id" placeholder="—" col="f-12" :id="'sp'.$i->id" />
            @if ($i->exists)<x-checkbox name="is_active" label="Active" :checked="$i->is_active" col="f-12" />@else<p class="small muted f-12" style="margin:0">Opening quantity is added with “Receive stock”.</p>@endif
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    </form>
</x-modal>
@endforeach
@endperm
@endsection
