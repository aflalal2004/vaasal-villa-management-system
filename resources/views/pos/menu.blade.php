@extends('layouts.pos')
@section('title', 'Menu')
@section('content')
<x-page-header title="Menu, modifiers & tables" sub="Categories route items to the kitchen or bar display. Mark items sold out (86) instantly.">
    <a class="btn" href="{{ route('pos.outlets.index') }}"><x-icon name="table" /> Outlets & tables</a>
    <button class="btn" type="button" data-modal-open="#cat-modal"><x-icon name="plus" /> Category</button>
    <a class="btn btn-primary" href="{{ route('pos.menu.items.create') }}"><x-icon name="plus" /> Menu item</a>
</x-page-header>
<div class="grid cols-main">
    <div class="stack">
        @foreach ($categories as $c)
            <div class="card">
                <div class="card-head" style="border-left:5px solid {{ $c->color }}">
                    <h2>{{ $c->name }} <span class="small muted" style="font-weight:400">· {{ ucfirst($c->station) }} station{{ $c->outlet ? ' · '.$c->outlet->name.' only' : '' }}</span></h2>
                    @if(! $c->is_active)<x-badge status="inactive" />@endif
                    <a class="btn btn-sm btn-ghost" href="{{ route('pos.menu.items.create', ['category' => $c->id]) }}"><x-icon name="plus" /></a>
                </div>
                <div class="table-wrap"><table class="table">
                    <tbody>
                    @forelse ($c->items as $i)
                        <tr @class(['strike' => ! $i->is_active])>
                            <td><strong>{{ $i->name }}</strong> <span class="mono small muted">{{ $i->code }}</span>
                                @if ($i->modifierGroups->isNotEmpty())<div class="small muted">Options: {{ $i->modifierGroups->pluck('name')->implode(', ') }}</div>@endif</td>
                            <td class="num">{{ money($i->price) }}<div class="small muted">cost {{ money($i->cost, null, false) }}</div></td>
                            <td>
                                <form method="post" action="{{ route('pos.menu.items.toggle', $i) }}">@csrf
                                    <button class="btn btn-sm {{ $i->is_available ? '' : 'btn-danger' }}" type="submit">{{ $i->is_available ? 'Available' : 'Sold out (86)' }}</button></form>
                            </td>
                            <td class="actions"><a class="btn btn-sm btn-ghost" href="{{ route('pos.menu.items.edit', $i) }}" aria-label="Edit"><x-icon name="edit" /></a></td>
                        </tr>
                    @empty
                        <tr><td class="muted">No items.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        @endforeach
    </div>
    <div class="stack" style="align-content:start">
        <div class="card">
            <div class="card-head"><h2>Modifier groups</h2></div>
            @foreach ($groups as $g)
                <div class="card-body" style="border-bottom:1px solid var(--line-2)">
                    <strong>{{ $g->name }}</strong> <span class="small muted">choose {{ $g->min_select }}–{{ $g->max_select }}</span>
                    <div class="row" style="margin-top:6px">
                        @foreach ($g->modifiers->where('is_active', true) as $m)
                            <span class="chip">{{ $m->name }}{{ $m->price > 0 ? ' +'.number_format($m->price) : '' }}
                                <form method="post" action="{{ route('pos.menu.modifiers.destroy', $m) }}" style="display:inline" data-confirm="Remove option {{ $m->name }}?">@csrf @method('delete')<button type="submit" style="background:none;border:0;cursor:pointer;color:var(--muted)" aria-label="Remove">×</button></form></span>
                        @endforeach
                    </div>
                    <form method="post" action="{{ route('pos.menu.modifiers.store', $g) }}" class="row" style="margin-top:8px">@csrf
                        <input name="name" placeholder="New option" required maxlength="80" style="flex:2" aria-label="Option name"><input name="price" type="number" step="0.01" min="0" placeholder="+price" style="flex:1" aria-label="Price">
                        <button class="btn btn-sm" type="submit">Add</button></form>
                </div>
            @endforeach
            <form method="post" action="{{ route('pos.menu.groups.store') }}" class="card-body form-grid">
                @csrf
                <x-input name="name" label="New group" required col="f-6" /><x-input name="min_select" type="number" min="0" label="Min" value="0" col="f-3" /><x-input name="max_select" type="number" min="1" label="Max" value="1" col="f-3" />
                <div class="f-12"><button class="btn btn-sm" type="submit">Create group</button></div>
            </form>
        </div>
    </div>
</div>
<x-modal id="cat-modal" title="New menu category">
    <form method="post" action="{{ route('pos.menu.categories.store') }}">
        @csrf
        <div class="modal-body form-grid">
            <x-input name="name" label="Name" required col="f-8" /><x-input name="color" type="color" label="Colour" value="#0E6B63" col="f-4" />
            <x-select name="station" label="Prepared at" :options="['kitchen' => 'Kitchen', 'bar' => 'Bar', 'pastry' => 'Pastry']" col="f-6" />
            <x-select name="outlet_id" label="Outlet" :options="$outlets" placeholder="All outlets" col="f-6" />
            <x-input name="sort_order" type="number" label="Sort" value="0" col="f-4" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Add</button></div>
    </form>
</x-modal>
@endsection
