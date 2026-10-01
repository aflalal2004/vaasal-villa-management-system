@extends('layouts.admin')
@section('title', 'Linen & towels')
@section('content')
<x-page-header title="Linen & towels" :crumbs="['Housekeeping' => route('admin.housekeeping.index')]" sub="Par levels per villa, store stock and items at the laundry. Housekeepers record fresh-out / soiled-in on every task." />
<form method="post" action="{{ route('admin.housekeeping.linen.save') }}" class="card mb">
    @csrf
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Item</th><th class="num">Par / villa</th><th class="num">Required ({{ $villaCount }} villas × 3 par)</th><th class="num">In store</th><th class="num">At laundry</th><th class="num">Returned from laundry</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($items as $i)
            @php $need = $i->par_per_villa * $villaCount * 3; @endphp
            <tr>
                <td>{{ $i->name }}</td>
                <td class="num"><input type="number" min="0" name="items[{{ $i->id }}][par_per_villa]" value="{{ $i->par_per_villa }}" style="width:80px" aria-label="Par"></td>
                <td class="num">{{ $need }}</td>
                <td class="num"><input type="number" min="0" name="items[{{ $i->id }}][stock_qty]" value="{{ $i->stock_qty }}" style="width:90px" aria-label="Stock"></td>
                <td class="num">{{ $i->in_laundry_qty }}</td>
                <td class="num"><input type="number" min="0" max="{{ $i->in_laundry_qty }}" name="items[{{ $i->id }}][laundry_return]" value="0" style="width:80px" aria-label="Returned"></td>
                <td>@if ($i->stock_qty + $i->in_laundry_qty < $need * 0.6)<x-badge tone="danger" label="Replenish" />@else<x-badge tone="success" label="OK" />@endif</td>
            </tr>
        @endforeach
        <tr><td><input type="text" name="new_name" placeholder="New item…" aria-label="New item name"></td><td class="num"><input type="number" name="new_par" min="0" placeholder="2" style="width:80px" aria-label="New par"></td><td></td>
            <td class="num"><input type="number" name="new_stock" min="0" placeholder="0" style="width:90px" aria-label="New stock"></td><td colspan="3"></td></tr>
        </tbody>
    </table></div>
    <div class="card-foot"><button class="btn btn-primary" type="submit">Save linen stock</button></div>
</form>
<div class="card">
    <div class="card-head"><h2>Recent movements</h2></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>When</th><th>Villa</th><th>Item</th><th class="num">Out</th><th class="num">In</th></tr></thead>
        <tbody>@foreach ($recent as $m)<tr><td class="small">{{ fmt_dt($m->created_at) }}</td><td>{{ $m->task?->villa?->code }}</td><td>{{ $m->item->name }}</td><td class="num">{{ $m->qty_out }}</td><td class="num">{{ $m->qty_in }}</td></tr>@endforeach</tbody>
    </table></div>
</div>
@endsection
