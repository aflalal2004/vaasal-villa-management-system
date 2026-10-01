@extends('layouts.admin')
@section('title', $r['title'])
@section('content')
<x-page-header :title="$r['title']" :crumbs="['Reports' => route('admin.reports.index')]" :sub="$r['description']">
    <a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><x-icon name="download" /> CSV</a>
    <a class="btn" href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank"><x-icon name="printer" /> Print</a>
</x-page-header>
@include('admin.reports.partials.body', ['reportRoute' => 'admin.reports.show'])
@endsection
