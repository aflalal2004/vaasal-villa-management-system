@extends('layouts.pos')
@section('title', 'Suppliers')
@section('content')
<x-page-header title="Suppliers" :crumbs="['Inventory' => route('pos.inventory.items.index')]">
    <a class="btn btn-primary" href="{{ route('pos.inventory.suppliers.create') }}"><x-icon name="plus" /> New supplier</a>
</x-page-header>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Supplier</th><th>Contact</th><th class="num">Items supplied</th><th class="num">Purchases (90 days)</th><th>Status</th><th></th></tr></thead>
        <tbody>@foreach ($suppliers as $s)
            <tr><td><strong>{{ $s->name }}</strong>@if($s->tax_id)<div class="small muted">{{ $s->tax_id }}</div>@endif</td>
                <td class="small">{{ $s->contact_name }}<br>{{ $s->phone }} {{ $s->email }}</td><td class="num">{{ $s->stock_items_count }}</td>
                <td class="num">{{ money($spend[$s->id] ?? 0) }}</td><td><x-badge :status="$s->is_active ? 'active' : 'inactive'" /></td>
                <td class="actions"><a class="btn btn-sm btn-ghost" href="{{ route('pos.inventory.suppliers.edit', $s) }}" aria-label="Edit"><x-icon name="edit" /></a></td></tr>
        @endforeach</tbody>
    </table></div>
</div>
@endsection
