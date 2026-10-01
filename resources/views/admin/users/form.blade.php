@extends('layouts.admin')
@section('title', $u->exists ? 'Edit user' : 'New user')
@section('content')
<x-page-header :title="$u->exists ? $u->name : 'New user'" :crumbs="['Users' => route('admin.users.index')]" :sub="$u->exists ? 'Created '.fmt_date($u->created_at).($u->password_changed_at ? ' · password changed '.$u->password_changed_at->diffForHumans() : '') : 'Tip: create staff logins from the employee profile so attendance and access stay linked.'" />
<form method="post" action="{{ $u->exists ? route('admin.users.update', $u) : route('admin.users.store') }}" class="card" style="max-width:900px">
    @csrf @if($u->exists) @method('put') @endif
    <div class="card-body form-grid">
        <x-input name="name" label="Name" :value="$u->name" required col="f-6" />
        <x-input name="email" type="email" label="Email (sign-in)" :value="$u->email" required col="f-6" />
        <x-input name="username" label="Username (sign-in)" :value="$u->username" col="f-6" help="Letters, numbers, dot, dash or underscore. Leave blank to use the part of the email before @." />
        <x-input name="phone" label="Phone" :value="$u->phone" col="f-6" />
        <x-select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive (cannot sign in)']" :value="$u->status" col="f-6" />
        <x-input name="password" type="password" :label="$u->exists ? 'New password (leave blank to keep)' : 'Password'" :required="! $u->exists" col="f-12" autocomplete="new-password"
            help="At least {{ config('vaasal.security.password_min') }} characters, upper and lower case, and a number." />
        @if (! $u->isOperator())
        <div class="field f-12"><span class="label">Roles</span>
            <div class="grid cols-3" style="gap:6px">
                @foreach ($roles as $r)
                    <label class="check" style="align-items:flex-start"><input type="checkbox" name="roles[]" value="{{ $r->id }}" @checked($u->roles?->contains($r->id)) @disabled($r->slug === 'admin' && ! auth()->user()->isSuperAdmin())>
                        <span>{{ $r->name }}<br><span class="small muted">{{ $r->description }}</span></span></label>
                @endforeach
            </div>
            @error('roles')<span class="error">{{ $message }}</span>@enderror
        </div>
        @endif
    </div>
    <div class="card-foot"><a class="btn" href="{{ route('admin.users.index') }}">Cancel</a><button class="btn btn-primary" type="submit">Save user</button></div>
</form>
@endsection
