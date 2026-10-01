@extends('layouts.auth')
@section('title', 'Become a partner')
@section('card_class', 'wide')
@section('subtitle', 'Tour Operator Partner Application')
@section('content')
<div>
    <div style="font-size:11px;font-weight:600;letter-spacing:.09em;text-transform:uppercase;color:var(--brass)">Tour operator application</div>
    <h2>Create a partner account</h2>
    <p class="muted small" style="margin-top:6px">We review each application. You can sign in straight away; bookings open once approved.</p>
</div>
<form method="post" action="{{ route('operator.register.store') }}" class="form-grid">
    @csrf
    <x-input name="company_name" label="Company name" required col="f-12" />
    <x-input name="registration_no" label="Business reg. no." required col="f-6" />
    <x-input name="tax_id" label="Tax / VAT ID" col="f-6" />
    <x-input name="contact_name" label="Your name" required col="f-6" />
    <x-input name="phone" label="Phone" required col="f-6" />
    <x-input name="email" type="email" label="Reservations email" required col="f-12" />
    <x-input name="country" label="Country" required col="f-6" />
    <x-input name="website" type="url" label="Website" col="f-6" />
    <x-input name="address" label="Address" col="f-12" />
    <div class="f-12"><hr style="margin:4px 0"></div>
    <x-input name="login_email" type="email" label="Login email" required col="f-12" autocomplete="username" />
    <x-input name="password" type="password" label="Password" required col="f-6" autocomplete="new-password" />
    <x-input name="password_confirmation" type="password" label="Confirm" required col="f-6" autocomplete="new-password" />
    <div style="position:absolute;left:-9999px" aria-hidden="true"><input type="text" name="company_website_url" tabindex="-1" autocomplete="off"></div>
    <label class="check f-12"><input type="checkbox" name="terms" value="1"> I accept the partner terms and data-processing agreement.</label>
    <div class="f-12"><button class="btn btn-primary btn-lg btn-block" type="submit">Submit application</button></div>
</form>
<p class="small">Already a partner? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
