@extends('layouts.admin')
@section('title', 'Social media')
@section('content')
<x-page-header title="Social media" :crumbs="['Website content' => route('admin.cms.index')]" sub="Facebook, Instagram, WhatsApp, YouTube, TikTok and more. The website header, footer, contact page and POS receipts use these links automatically.">
    <a class="btn" href="{{ route('home') }}#footer" target="_blank"><x-icon name="globe" /> View website</a>
</x-page-header>
@include('partials.social-manager')
@endsection
