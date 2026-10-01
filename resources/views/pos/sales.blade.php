@extends('layouts.pos')
@section('title', $r['title'])
@section('content')
<x-page-header :title="$r['title']" eyebrow="Restaurant sales" :sub="$r['description']">
    <a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><x-icon name="download" /> CSV</a>
    <button class="btn" type="button" data-print><x-icon name="printer" /> Print</button>
</x-page-header>
@include('admin.reports.partials.body', ['reportRoute' => 'pos.sales'])
@endsection
