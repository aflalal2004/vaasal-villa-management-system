@extends('layouts.admin')
@section('title', 'Audit log')
@section('content')
<x-page-header title="Audit log" sub="Append-only record of financial, access, configuration and permission changes. Secrets are redacted.">
    <a class="btn" href="{{ route('admin.audit.logins') }}"><x-icon name="login" /> Sign-in log</a>
</x-page-header>
<form class="filters" method="get">
    <div class="field" style="flex:2 1 200px"><label for="q">Search</label><input id="q" type="search" name="q" value="{{ request('q') }}"></div>
    <div class="field"><label for="module">Module</label><select id="module" name="module" data-autosubmit><option value="">All</option>@foreach ($modules as $m)<option @selected(request('module') === $m)>{{ $m }}</option>@endforeach</select></div>
    <div class="field"><label for="user">User</label><select id="user" name="user" data-autosubmit><option value="">All</option>@foreach ($users as $id => $n)<option value="{{ $id }}" @selected(request('user') == $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="field"><label for="date">Date</label><input id="date" type="date" name="date" value="{{ request('date') }}"></div>
    <button class="btn" type="submit">Filter</button>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>When</th><th>User</th><th>Module</th><th>Action</th><th>Record</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        @foreach ($logs as $l)
            <tr>
                <td class="small nowrap">{{ fmt_dt($l->created_at) }}</td>
                <td class="small">{{ $l->user?->name ?? 'System' }}</td>
                <td><span class="chip">{{ $l->module }}</span></td>
                <td>{{ label($l->action) }}</td>
                <td class="small mono">{{ $l->auditable_type }}{{ $l->auditable_id ? '#'.$l->auditable_id : '' }}</td>
                <td class="small">{{ $l->description }}
                    @if ($l->new_values)<details><summary class="muted" style="cursor:pointer">changes</summary><pre class="mono" style="white-space:pre-wrap;font-size:11px;margin:4px 0">@foreach ($l->new_values as $k => $v){{ $k }}: {{ is_array($l->old_values) && array_key_exists($k, $l->old_values) ? json_encode($l->old_values[$k]).' → ' : '' }}{{ json_encode($v) }}
@endforeach</pre></details>@endif</td>
                <td class="small mono">{{ $l->ip_address }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    {{ $logs->links() }}
</div>
@endsection
