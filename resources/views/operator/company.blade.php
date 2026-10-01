@extends('layouts.operator')
@section('title', 'Company profile')
@section('content')
<x-page-header title="Company profile" sub="Keep your details current — they appear on vouchers and invoices." />
<form method="post" action="{{ route('operator.company.update') }}" enctype="multipart/form-data" class="card" style="max-width:960px">
    @csrf @method('put')
    @include('operator.partials.company-fields', ['op' => $op])
    <div class="card-foot"><button class="btn btn-primary" type="submit">Save</button></div>
</form>
@endsection
