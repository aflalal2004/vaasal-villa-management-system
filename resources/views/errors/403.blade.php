@extends('errors.layout')
@section('code', '403 · Not allowed')
@section('title', 'You don’t have access to this page')
@section('message', $exception->getMessage() ?: 'Your role doesn’t include this area. Ask an administrator if you need access.')
@section('actions')
    @auth<a class="btn btn-primary" href="{{ route(auth()->user()->homeRoute()) }}">Go to my home screen</a>@endauth
    <a class="btn" href="{{ url()->previous() }}">Back</a>
@endsection
