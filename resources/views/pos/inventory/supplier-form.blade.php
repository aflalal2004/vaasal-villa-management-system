@extends('layouts.pos')
@section('title', $s->exists ? 'Edit supplier' : 'New supplier')
@section('content')
<x-page-header :title="$s->exists ? $s->name : 'New supplier'" :crumbs="['Suppliers' => route('pos.inventory.suppliers.index')]" />
<form method="post" action="{{ $s->exists ? route('pos.inventory.suppliers.update', $s) : route('pos.inventory.suppliers.store') }}" class="card" style="max-width:800px">
    @csrf @if($s->exists) @method('put') @endif
    <div class="card-body form-grid">
        <x-input name="name" label="Name" :value="$s->name" required col="f-8" /><x-input name="tax_id" label="Tax ID" :value="$s->tax_id" col="f-4" />
        <x-input name="contact_name" label="Contact" :value="$s->contact_name" col="f-4" /><x-input name="phone" label="Phone" :value="$s->phone" col="f-4" /><x-input name="email" type="email" label="Email" :value="$s->email" col="f-4" />
        <x-input name="address" label="Address" :value="$s->address" col="f-12" /><x-textarea name="notes" label="Notes (delivery days, terms)" :value="$s->notes" rows="2" />
        <x-checkbox name="is_active" label="Active" :checked="$s->is_active" col="f-12" />
    </div>
    <div class="card-foot"><a class="btn" href="{{ route('pos.inventory.suppliers.index') }}">Cancel</a><button class="btn btn-primary" type="submit">Save</button></div>
</form>
@endsection
