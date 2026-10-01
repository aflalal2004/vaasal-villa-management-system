@extends('layouts.pos')
@section('title', 'Restaurant social media')
@section('content')

    <x-page-header title="Restaurant social media" sub="Links printed on restaurant receipts and shown for the restaurant. Links marked “Website & restaurant” are shared with the main website." />
    @include('partials.social-manager')

@endsection
