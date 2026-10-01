@extends('layouts.admin')
@section('title', 'Roles & permissions')
@section('content')
<x-page-header title="Roles & permissions matrix" :crumbs="['Users' => route('admin.users.index')]" sub="✓ = permission granted. Administrator always holds every permission. Routes are protected server-side; menus only mirror these rules.">
    <a class="btn btn-primary" href="{{ route('admin.roles.create') }}"><x-icon name="plus" /> New role</a>
</x-page-header>
<div class="cal-wrap">
    <table class="table" style="font-size:12.5px">
        <thead>
            <tr><th style="position:sticky;left:0;background:var(--surface-2);z-index:2">Permission</th>
                @foreach ($roles as $r)<th style="text-align:center;white-space:normal;min-width:84px"><a href="{{ route('admin.roles.edit', $r) }}">{{ $r->name }}</a><div class="muted" style="text-transform:none;letter-spacing:0">{{ $r->users_count }} user(s)</div></th>@endforeach</tr>
        </thead>
        <tbody>
        @foreach ($permissions as $module => $perms)
            <tr><td colspan="{{ $roles->count() + 1 }}" style="background:var(--sunken);font-weight:600;text-transform:uppercase;letter-spacing:.07em;font-size:11px">{{ label($module) }}</td></tr>
            @foreach ($perms as $p)
                <tr>
                    <td style="position:sticky;left:0;background:var(--surface)">{{ $p->name }}<div class="mono small muted">{{ $p->slug }}</div></td>
                    @foreach ($roles as $r)
                        @php $has = $r->slug === 'admin' ? $p->module !== 'operator_portal' : $r->permissions->contains('id', $p->id); @endphp
                        <td style="text-align:center">@if($has)<span style="color:var(--ok);font-weight:700" aria-label="granted">✓</span>@else<span class="muted" aria-label="not granted">·</span>@endif</td>
                    @endforeach
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
</div>
@endsection
