@extends('layouts.admin')
@section('title', 'Users')
@section('content')
<x-page-header title="Users & access" sub="Each user signs in with email and password and sees only the modules their roles allow.">
    <a class="btn" href="{{ route('admin.roles.index') }}"><x-icon name="shield" /> Roles & permissions</a>
    <a class="btn" href="{{ route('admin.audit.logins') }}"><x-icon name="history" /> Sign-in log</a>
    <a class="btn btn-primary" href="{{ route('admin.users.create') }}"><x-icon name="plus" /> New user</a>
</x-page-header>
<div class="tabs">@foreach (['staff' => 'Staff', 'operator' => 'Tour operator logins', 'all' => 'All'] as $k => $v)<a href="?type={{ $k }}" @class(['active' => request('type', 'staff') === $k])>{{ $v }}</a>@endforeach</div>
<form class="filters" method="get">
    <input type="hidden" name="type" value="{{ request('type', 'staff') }}">
    <div class="field" style="flex:2 1 200px"><label for="q">Search</label><input id="q" type="search" name="q" value="{{ request('q') }}"></div>
    <div class="field"><label for="role">Role</label><select id="role" name="role" data-autosubmit><option value="">All roles</option>@foreach ($roles as $id => $n)<option value="{{ $id }}" @selected(request('role') == $id)>{{ $n }}</option>@endforeach</select></div>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>User</th><th>Roles</th><th>Linked to</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr>
                <td><strong>{{ $u->name }}</strong><div class="small muted">{{ $u->email }}</div></td>
                <td class="small">{{ $u->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                <td class="small">{{ $u->employee ? $u->employee->employee_no : ($u->tourOperator?->company_name ?? '—') }}</td>
                <td><x-badge :status="$u->status" />@if($u->isLocked())<x-badge tone="danger" label="Locked" />@endif</td>
                <td class="small">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}<br><span class="muted">{{ $u->last_login_ip }}</span></td>
                <td class="actions">
                    @if ($u->isLocked())<form method="post" action="{{ route('admin.users.unlock', $u) }}" style="display:inline">@csrf<button class="btn btn-sm" type="submit">Unlock</button></form>@endif
                    <form method="post" action="{{ route('admin.users.reset-password', $u) }}" style="display:inline" data-confirm="Generate a temporary password for {{ $u->email }}? Their sessions end immediately.">@csrf<button class="btn btn-sm btn-ghost" type="submit" title="Reset password"><x-icon name="lock" /></button></form>
                    <a class="btn btn-sm btn-ghost" href="{{ route('admin.users.edit', $u) }}" aria-label="Edit"><x-icon name="edit" /></a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    {{ $users->links() }}
</div>
@endsection
