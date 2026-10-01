@extends('layouts.pos')
@section('title', 'Outlets & tables')
@section('content')
<x-page-header title="Outlets & tables" :crumbs="['Menu' => route('pos.menu.index')]" sub="Each outlet has its own tax, service charge, receipt text and folio department for charge-to-villa postings." />
<div class="stack">
@foreach ($outlets->concat([new \App\Models\Outlet(['type' => 'restaurant', 'folio_department' => 'restaurant', 'tax_pct' => 8, 'service_charge_pct' => 10, 'is_active' => true])]) as $o)
    <div class="card">
        <div class="card-head"><h2>{{ $o->exists ? $o->name : 'New outlet' }}</h2></div>
        <div class="card-body grid cols-2">
            <form method="post" action="{{ $o->exists ? route('pos.outlets.update', $o) : route('pos.outlets.store') }}" class="form-grid">
                @csrf @if($o->exists) @method('put') @endif
                <x-input name="code" label="Code" :value="$o->code" required col="f-4" :id="'oc'.$o->id" />
                <x-input name="name" label="Name" :value="$o->name" required col="f-8" :id="'on'.$o->id" />
                <x-select name="type" label="Type" :options="['restaurant' => 'Restaurant', 'bar' => 'Bar', 'pool_bar' => 'Pool bar', 'room_service' => 'In-villa dining']" :value="$o->type" col="f-6" :id="'ot'.$o->id" />
                <x-select name="folio_department" label="Folio department" :options="config('vaasal.departments')" :value="$o->folio_department" col="f-6" :id="'of'.$o->id" />
                <x-input name="tax_pct" type="number" step="0.01" label="Tax %" :value="$o->tax_pct" required col="f-6" :id="'ox'.$o->id" />
                <x-input name="service_charge_pct" type="number" step="0.01" label="Service %" :value="$o->service_charge_pct" required col="f-6" :id="'os'.$o->id" />
                <x-input name="receipt_header" label="Receipt header" :value="$o->receipt_header" col="f-12" :id="'oh'.$o->id" />
                <x-input name="receipt_footer" label="Receipt footer" :value="$o->receipt_footer" col="f-12" :id="'oo'.$o->id" />
                @if($o->exists)<x-checkbox name="is_active" label="Active" :checked="$o->is_active" col="f-6" />@endif
                <div class="f-12"><button class="btn btn-primary btn-sm" type="submit">Save outlet</button></div>
            </form>
            @if ($o->exists)
            <div>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Table</th><th>Area</th><th>Seats</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($o->tables as $t)
                        <tr>
                            <td><input form="tb{{ $t->id }}" name="name" value="{{ $t->name }}" style="width:90px" aria-label="Name"></td>
                            <td><input form="tb{{ $t->id }}" name="area" value="{{ $t->area }}" style="width:120px" aria-label="Area"></td>
                            <td><input form="tb{{ $t->id }}" name="seats" type="number" min="1" value="{{ $t->seats }}" style="width:70px" aria-label="Seats"></td>
                            <td class="actions"><form id="tb{{ $t->id }}" method="post" action="{{ route('pos.tables.update', $t) }}" class="row" style="justify-content:flex-end">@csrf @method('put')
                                <label class="check small"><input type="checkbox" name="is_active" value="1" @checked($t->is_active)> Active</label><button class="btn btn-sm" type="submit">Save</button></form></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <form method="post" action="{{ route('pos.tables.store', $o) }}" class="row" style="margin-top:10px">@csrf
                    <input name="name" placeholder="Table name" required style="width:120px" aria-label="New table name"><input name="area" placeholder="Area" style="width:120px" aria-label="Area">
                    <input name="seats" type="number" min="1" value="4" style="width:80px" aria-label="Seats"><button class="btn btn-sm" type="submit">Add table</button></form>
            </div>
            @endif
        </div>
    </div>
@endforeach
</div>
@endsection
