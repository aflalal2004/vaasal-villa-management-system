@extends('layouts.admin')
@section('title', 'Sign-in log')
@section('content')
<x-page-header title="Sign-in log" :crumbs="['Audit log' => route('admin.audit.index')]" :sub="'Accounts lock for '.config('vaasal.security.login_lock_minutes').' minutes after '.config('vaasal.security.login_max_attempts').' failed attempts; networks are rate-limited.'" />
<div class="stats">
    <x-stat label="Failed sign-ins (24h)" :value="$failed24" />
    <x-stat label="Locked accounts" :value="$locked->count()" :hint="$locked->pluck('email')->implode(', ')" />
</div>
<form class="filters" method="get">
    <div class="field" style="flex:2 1 200px"><label for="q">Email or IP</label><input id="q" type="search" name="q" value="{{ request('q') }}"></div>
    <div class="field"><label for="result">Result</label><select id="result" name="result" data-autosubmit><option value="">All</option><option value="failed" @selected(request('result') === 'failed')>Failed only</option></select></div>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>When</th><th>Email</th><th>Result</th><th>Reason</th><th>IP</th><th>Device</th></tr></thead>
        <tbody>@foreach ($logs as $l)
            <tr><td class="small nowrap">{{ fmt_dt($l->created_at) }}</td><td>{{ $l->email }}</td><td><x-badge :status="$l->success ? 'ok' : 'failed'" :label="$l->success ? 'Success' : 'Failed'" /></td>
                <td class="small">{{ label($l->reason) }}</td><td class="mono small">{{ $l->ip_address }}</td><td class="small muted">{{ \Illuminate\Support\Str::limit($l->user_agent, 60) }}</td></tr>
        @endforeach</tbody>
    </table></div>
    {{ $logs->links() }}
</div>
@endsection
