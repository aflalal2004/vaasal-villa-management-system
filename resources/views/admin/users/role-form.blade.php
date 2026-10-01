@extends('layouts.admin')
@section('title', $role->exists ? 'Edit role' : 'New role')
@section('content')
<x-page-header :title="$role->exists ? $role->name : 'New role'" :crumbs="['Roles' => route('admin.roles.index')]" />
@if ($role->slug === 'admin')<div class="alert alert-info">Administrator always has every permission and cannot be edited.</div>@endif
<form method="post" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="card">
    @csrf @if($role->exists) @method('put') @endif
    <div class="card-body form-grid">
        <x-input name="name" label="Role name" :value="$role->name" required col="f-4" />
        <x-input name="description" label="Description" :value="$role->description" col="f-5" />
        <x-select name="home_route" label="Lands on" :value="$role->home_route" col="f-3" :options="['admin.dashboard' => 'Dashboard', 'admin.frontdesk.index' => 'Front desk', 'pos.terminal' => 'POS terminal', 'pos.kds' => 'Kitchen display',
            'admin.housekeeping.index' => 'Housekeeping board', 'admin.housekeeping.my' => 'My HK tasks', 'admin.maintenance.index' => 'Maintenance', 'admin.payments.index' => 'Payments',
            'pos.inventory.items.index' => 'Inventory', 'admin.staff.my-timecard' => 'My time card']" />
        @foreach ($permissions as $module => $perms)
            @continue($module === 'operator_portal')
            <fieldset class="f-4" style="border:1px solid var(--line);border-radius:8px;padding:10px 12px">
                <legend class="label" style="padding:0 6px">{{ label($module) }}</legend>
                @foreach ($perms as $p)
                    <label class="check small" style="display:flex;margin:4px 0"><input type="checkbox" name="permissions[]" value="{{ $p->id }}" @checked($role->permissions?->contains($p->id))> {{ $p->name }}</label>
                @endforeach
            </fieldset>
        @endforeach
    </div>
    <div class="card-foot"><a class="btn" href="{{ route('admin.roles.index') }}">Cancel</a><button class="btn btn-primary" type="submit" @disabled($role->slug === 'admin')>Save role</button></div>
</form>
@endsection
