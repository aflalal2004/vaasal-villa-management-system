@extends('layouts.pos')
@section('title', 'Stock movements')
@section('content')
<x-page-header title="Stock movements" :crumbs="['Inventory' => route('pos.inventory.items.index')]" sub="Every quantity change with the running balance." />
<form class="filters" method="get">
    <div class="field"><label for="type">Type</label><select id="type" name="type" data-autosubmit><option value="">All</option>@foreach (['in' => 'Received', 'sale' => 'Sale (recipe)', 'out' => 'Issued', 'waste' => 'Wastage', 'adjust' => 'Count adjustment'] as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="field"><label for="item">Item</label><select id="item" name="item" data-autosubmit><option value="">All</option>@foreach ($items as $id => $n)<option value="{{ $id }}" @selected(request('item') == $id)>{{ $n }}</option>@endforeach</select></div>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>When</th><th>Item</th><th>Type</th><th class="num">Qty</th><th class="num">Balance</th><th>Reference</th><th>Reason</th><th>By</th></tr></thead>
        <tbody>@foreach ($moves as $m)
            <tr><td class="small nowrap">{{ fmt_dt($m->created_at) }}</td><td>{{ $m->item->name }}</td>
                <td><x-badge :tone="['in' => 'success', 'sale' => 'info', 'out' => 'neutral', 'waste' => 'danger', 'adjust' => 'warning'][$m->type]" :label="ucfirst($m->type)" /></td>
                <td class="num">{{ $m->quantity > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($m->quantity, 3), '0'), '.') }} {{ $m->item->unit }}</td>
                <td class="num">{{ rtrim(rtrim(number_format($m->balance_after, 3), '0'), '.') }}</td>
                <td class="small">{{ $m->reference }}{{ $m->supplier ? ' · '.$m->supplier->name : '' }}</td><td class="small">{{ $m->reason }}</td><td class="small">{{ $m->user?->name ?? 'POS' }}</td></tr>
        @endforeach</tbody>
    </table></div>
    {{ $moves->links() }}
</div>
@endsection
