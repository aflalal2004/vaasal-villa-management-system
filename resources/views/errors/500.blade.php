@extends('errors.layout')
@section('code', '500 · Something went wrong')
@section('title', 'That didn’t work')
@section('message', 'An unexpected error occurred and has been logged for our team. Please try again in a moment, or message us on WhatsApp '.contact('whatsapp_display').' if it keeps happening.')
@section('actions')
    <a class="btn btn-primary" href="{{ url()->previous() }}">Try again</a>
    <a class="btn" href="{{ url('/') }}">Home</a>
@endsection
