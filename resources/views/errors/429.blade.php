@extends('errors.layout')
@section('code', '429 · Too many requests')
@section('title', 'Please slow down a little')
@section('message', 'You have made several requests in a short time. For your security we paused them briefly — wait a minute and try again.')
@section('actions')
    <a class="btn btn-primary" href="{{ url()->previous() }}">Try again</a>
    <a class="btn" href="{{ url('/') }}">Home</a>
@endsection
