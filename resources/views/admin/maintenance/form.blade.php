@extends('layouts.admin')
@section('title', 'Report maintenance issue')
@section('content')
<x-page-header title="Report a maintenance issue" :crumbs="['Maintenance' => route('admin.maintenance.index')]" />
<form method="post" action="{{ route('admin.maintenance.store') }}" enctype="multipart/form-data" class="card" style="max-width:860px">
    @csrf
    <div class="card-body form-grid">
        <x-select name="villa_id" label="Villa" :options="$villas" :value="$villaId" placeholder="Not a villa (public area)" col="f-6" />
        <x-input name="location" label="Location (if not a villa)" col="f-6" placeholder="e.g. Restaurant kitchen, pool pump room" />
        <x-input name="title" label="What is wrong?" required col="f-12" placeholder="e.g. AC not cooling in bedroom" />
        <x-textarea name="description" label="Details" rows="3" />
        <x-select name="category" label="Category" :options="\App\Models\MaintenanceTicket::CATEGORIES" required col="f-4" />
        <x-select name="severity" label="Severity" :options="\App\Models\MaintenanceTicket::SEVERITIES" :value="'medium'" required col="f-4" />
        <x-input name="block_days" type="number" min="1" max="60" label="If blocking: days out of order" value="1" col="f-4" />
        @perm('maintenance.manage')<x-select name="assigned_to" label="Assign technician" :options="$staff" placeholder="Unassigned" col="f-6" />@endperm
        <div class="field f-6"><label for="photo">Photo</label><input type="file" id="photo" name="photo" accept="image/*" capture="environment"></div>
        <div class="f-12 alert alert-warning" style="margin:0"><strong>Blocking</strong> takes the villa out of order immediately: it disappears from availability search and OTAs, and free nights are blocked.</div>
    </div>
    <div class="card-foot"><a class="btn" href="{{ route('admin.maintenance.index') }}">Cancel</a><button class="btn btn-primary" type="submit">Submit ticket</button></div>
</form>
@endsection
