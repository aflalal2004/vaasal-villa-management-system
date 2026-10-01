@extends('errors.layout')
@section('code', 'Session expired')
@section('title', 'This page timed out')
@section('message', 'For your security, forms expire after a period of inactivity. Nothing was submitted — go back, refresh the page and try again.')
@section('actions')
    <a class="btn btn-primary" href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}">Go back and refresh</a>
    <a class="btn" href="{{ route('home') }}">Home</a>
@endsection
