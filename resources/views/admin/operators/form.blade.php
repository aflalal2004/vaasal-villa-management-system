@extends('layouts.admin')
@section('title', $op->exists ? 'Edit operator' : 'New operator')
@section('content')
<x-page-header :title="$op->exists ? $op->company_name : 'New tour operator'" :crumbs="['Tour operators' => route('admin.operators.index')]" />
<form method="post" action="{{ $op->exists ? route('admin.operators.update', $op) : route('admin.operators.store') }}" enctype="multipart/form-data" class="card" style="max-width:960px">
    @csrf @if($op->exists) @method('put') @endif
    @include('operator.partials.company-fields', ['op' => $op, 'admin' => true])
    <div class="card-foot"><a class="btn" href="{{ $op->exists ? route('admin.operators.show', $op) : route('admin.operators.index') }}">Cancel</a><button class="btn btn-primary" type="submit">Save</button></div>
</form>
@endsection
