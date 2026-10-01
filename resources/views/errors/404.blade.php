@extends('errors.layout')
@section('code', '404 · Not found')
@section('title', 'We couldn’t find that page')
@section('message', 'The link may be old or mistyped. Try the villas, or check availability for your dates.')
@section('actions')
    <a class="btn btn-primary" href="{{ route('site.villas') }}">See the villas</a>
    <a class="btn" href="{{ route('home') }}">Home</a>
@endsection
