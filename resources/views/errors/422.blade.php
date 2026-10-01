@extends('errors.layout')
@section('code', '422 · Please check the details')
@section('title', 'Some details need another look')
@section('message', 'The information sent could not be processed. Go back, check the highlighted fields and try again.')
@section('actions')
    <a class="btn btn-primary" href="{{ url()->previous() }}">Go back</a>
    <a class="btn" href="{{ url('/') }}">Home</a>
@endsection
