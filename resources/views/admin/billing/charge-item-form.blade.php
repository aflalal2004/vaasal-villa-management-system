@extends('layouts.admin')
@section('title', $item->exists ? 'Edit service' : 'New service')
@section('content')
<x-page-header :title="$item->exists ? 'Edit '.$item->name : 'New service'" :crumbs="['Service charges' => route('admin.charge-items.index')]" />
<div class="card" style="max-width:720px">
    <form method="post" action="{{ $item->exists ? route('admin.charge-items.update', $item) : route('admin.charge-items.store') }}">
        @csrf @if($item->exists) @method('put') @endif
        <div class="card-body form-grid">
            <x-input name="code" label="Code" :value="$item->code" required col="f-4" help="Letters, numbers, dashes" />
            <x-input name="name" label="Name" :value="$item->name" required col="f-8" />
            <x-select name="department" label="Department" :options="config('vaasal.departments')" :value="$item->department" required col="f-6" />
            <x-input name="price" type="number" step="0.01" min="0" label="Price" :value="$item->price" required col="f-6" />
            <x-checkbox name="taxable" label="Taxable" :checked="$item->taxable" col="f-4" />
            <x-checkbox name="service_chargeable" label="Add service charge" :checked="$item->service_chargeable" col="f-4" />
            <x-checkbox name="is_active" label="Active" :checked="$item->is_active" col="f-4" />
        </div>
        <div class="card-foot"><a class="btn" href="{{ route('admin.charge-items.index') }}">Cancel</a><button class="btn btn-primary" type="submit">Save</button></div>
    </form>
</div>
@endsection
