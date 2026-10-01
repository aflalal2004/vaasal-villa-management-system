@extends('errors.layout')
@section('code', '503 · Maintenance')
@section('title', 'We’ll be right back')
@section('message', 'The system is being updated and will be available again in a few minutes. For urgent help call '.contact('phone').' or WhatsApp '.contact('whatsapp_display').'.')
@section('actions')
    <a class="btn btn-primary" href="{{ whatsapp_link('Hello Vaasal Villa, I need help while the website is being updated.') }}" target="_blank" rel="noopener">WhatsApp us</a>
    <a class="btn" href="tel:{{ contact('phone_link') }}">Call {{ contact('phone') }}</a>
@endsection
