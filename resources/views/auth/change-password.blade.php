@extends(auth()->user()->isOperator() ? 'layouts.operator' : 'layouts.admin')
@section('title', 'Change password')
@section('content')
<x-page-header title="Change password" sub="Changing your password signs out all your other sessions." />
<div class="card" style="max-width:560px">
    <form method="post" action="{{ route('password.change.update') }}">
        @csrf
        <div class="card-body form-grid">
            <x-input name="current_password" type="password" label="Current password" required col="f-12" autocomplete="current-password" />
            <x-input name="password" type="password" label="New password" required col="f-12" autocomplete="new-password"
                help="At least {{ config('vaasal.security.password_min') }} characters, upper and lower case, and a number." />
            <x-input name="password_confirmation" type="password" label="Confirm new password" required col="f-12" autocomplete="new-password" />
        </div>
        <div class="card-foot"><button class="btn btn-primary" type="submit">Update password</button></div>
    </form>
</div>
@endsection
