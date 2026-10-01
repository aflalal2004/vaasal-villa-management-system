@extends('layouts.admin')
@section('title', 'Edit guest')
@section('content')
<x-page-header :title="'Edit '.$guest->fullName()" :crumbs="['Guests' => route('admin.guests.index'), $guest->fullName() => route('admin.guests.show', $guest)]" />
<form method="post" action="{{ route('admin.guests.update', $guest) }}" enctype="multipart/form-data" class="card" style="max-width:960px">
    @csrf @method('put')
    <div class="card-body form-grid">
        <x-select name="title" label="Title" :options="['Mr' => 'Mr', 'Ms' => 'Ms', 'Mrs' => 'Mrs', 'Dr' => 'Dr']" placeholder="—" :value="$guest->title" col="f-2" />
        <x-input name="first_name" label="First name" :value="$guest->first_name" required col="f-4" />
        <x-input name="last_name" label="Last name" :value="$guest->last_name" required col="f-6" />
        <x-input name="email" type="email" label="Email" :value="$guest->email" col="f-6" />
        <x-input name="phone" label="Phone" :value="$guest->phone" col="f-6" />
        <x-input name="country" label="Country of residence" :value="$guest->country" col="f-4" />
        <x-input name="nationality" label="Nationality" :value="$guest->nationality" col="f-4" />
        <x-input name="date_of_birth" type="date" label="Date of birth" :value="$guest->date_of_birth" col="f-4" />
        <x-select name="id_type" label="ID type" :options="['passport' => 'Passport', 'nic' => 'National ID', 'driving_licence' => 'Driving licence']" placeholder="—" :value="$guest->id_type" col="f-4" />
        <x-input name="id_number" label="ID number" col="f-4" :placeholder="$guest->maskedId() ? 'Stored: '.$guest->maskedId().' — leave blank to keep' : ''" help="Stored encrypted." />
        <x-input name="id_expiry" type="date" label="ID expiry" :value="$guest->id_expiry" col="f-4" />
        <div class="field f-6"><label for="id_document">ID / passport scan</label><input type="file" id="id_document" name="id_document" accept="image/jpeg,image/png,image/webp,application/pdf"><span class="help">JPG, PNG, WebP or PDF, max 5 MB. Kept on private storage.</span></div>
        <x-input name="address" label="Address" :value="$guest->address" col="f-6" />
        <x-textarea name="preferences" label="Preferences" :value="$guest->preferences" col="f-6" rows="2" />
        <x-textarea name="notes" label="Internal notes" :value="$guest->notes" col="f-6" rows="2" />
        <x-checkbox name="is_vip" label="VIP" :checked="$guest->is_vip" col="f-4" />
        <x-checkbox name="marketing_consent" label="Marketing consent" :checked="$guest->marketing_consent" col="f-4" />
        <x-checkbox name="is_blacklisted" label="Blacklisted (blocks new bookings)" :checked="$guest->is_blacklisted" col="f-4" />
    </div>
    <div class="card-foot"><a class="btn" href="{{ route('admin.guests.show', $guest) }}">Cancel</a><button class="btn btn-primary" type="submit">Save guest</button></div>
</form>
@endsection
