@extends('layouts.admin')
@section('title', $e->exists ? 'Edit employee' : 'New employee')
@section('content')
<x-page-header :title="$e->exists ? $e->fullName() : 'New employee'" :crumbs="['Employees' => route('admin.staff.employees.index')]" />
<form method="post" action="{{ $e->exists ? route('admin.staff.employees.update', $e) : route('admin.staff.employees.store') }}" enctype="multipart/form-data" class="card" style="max-width:1000px">
    @csrf @if($e->exists) @method('put') @endif
    <div class="card-body form-grid">
        <x-input name="first_name" label="First name" :value="$e->first_name" required col="f-4" />
        <x-input name="last_name" label="Last name" :value="$e->last_name" required col="f-4" />
        <x-select name="gender" label="Gender" :options="['female' => 'Female', 'male' => 'Male', 'other' => 'Other']" :value="$e->gender" placeholder="—" col="f-4" />
        <x-select name="department_id" label="Department" :options="$departments" :value="$e->department_id" required placeholder="Choose…" col="f-4" />
        <x-select name="job_role_id" label="Job role" :options="$jobRoles" :value="$e->job_role_id" placeholder="—" col="f-4" />
        <x-select name="employment_type" label="Employment" :options="['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract', 'intern' => 'Intern']" :value="$e->employment_type" required col="f-4" />
        <x-input name="hire_date" type="date" label="Hire date" :value="$e->hire_date" required col="f-4" />
        <x-input name="date_of_birth" type="date" label="Date of birth" :value="$e->date_of_birth" col="f-4" />
        <x-input name="national_id" label="NIC / passport" col="f-4" :placeholder="$e->exists && $e->national_id ? 'On file — leave blank to keep' : ''" help="Stored encrypted" />
        <x-input name="phone" label="Phone" :value="$e->phone" col="f-4" />
        <x-input name="email" type="email" label="Email" :value="$e->email" col="f-4" />
        <x-input name="basic_salary" type="number" step="0.01" min="0" label="Basic salary" :value="$e->basic_salary" col="f-4" />
        <x-input name="address" label="Address" :value="$e->address" col="f-12" />
        <x-input name="emergency_contact_name" label="Emergency contact" :value="$e->emergency_contact_name" col="f-6" />
        <x-input name="emergency_contact_phone" label="Emergency phone" :value="$e->emergency_contact_phone" col="f-6" />
        <div class="f-12"><hr style="margin:4px 0"><strong class="small">Time clock identifiers</strong> <span class="small muted">— for kiosk PIN, RFID badge readers, biometric terminals</span></div>
        <x-input name="attendance_pin" type="password" label="Kiosk PIN (4–6 digits)" col="f-4" autocomplete="new-password" :placeholder="$e->attendance_pin ? 'Set — leave blank to keep' : ''" />
        <x-input name="rfid_uid" label="RFID badge UID" :value="$e->rfid_uid" col="f-4" />
        <x-input name="biometric_ref" label="Biometric enrolment ID" :value="$e->biometric_ref" col="f-4" />
        <div class="field f-6"><label for="photo">Photo</label><input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp"></div>
    </div>
    <div class="card-foot"><a class="btn" href="{{ $e->exists ? route('admin.staff.employees.show', $e) : route('admin.staff.employees.index') }}">Cancel</a><button class="btn btn-primary" type="submit">Save employee</button></div>
</form>
@endsection
