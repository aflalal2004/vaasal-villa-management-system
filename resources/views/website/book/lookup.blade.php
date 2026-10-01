@extends('layouts.site')
@section('title', 'Manage my booking')
@section('header_class', 'always')
@section('content')
<section class="block" style="padding-top:170px">
    <div class="wrap" style="max-width:560px">
        <span class="eyebrow">Manage my booking</span>
        <h1 style="font-size:40px">Find your reservation</h1>
        <form class="form-card" method="post" action="{{ route('manage.find') }}" style="margin-top:22px">
            @csrf
            @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
            <div class="fgrid">
                <div class="field full"><label for="ref">Booking reference</label><input id="ref" name="reference" value="{{ old('reference') }}" placeholder="VV-26-000123" required></div>
                <div class="field full"><label for="em">Email used for the booking</label><input id="em" type="email" name="email" value="{{ old('email') }}" required></div>
                <div class="full"><button class="btn btn-primary" type="submit" style="width:100%">Find booking</button></div>
            </div>
        </form>
    </div>
</section>
@endsection
