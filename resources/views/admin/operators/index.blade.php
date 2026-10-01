@extends('layouts.admin')
@section('title', 'Tour operators')
@section('content')
<x-page-header title="Tour operators" sub="Partner companies with contract rates, credit terms, group bookings and a self-service portal.">
    @perm('operators.manage')<a class="btn btn-primary" href="{{ route('admin.operators.create') }}"><x-icon name="plus" /> New operator</a>@endperm
</x-page-header>
<div class="tabs">
    @foreach (['' => 'All', 'active' => 'Active', 'pending' => 'Pending approval', 'suspended' => 'Suspended'] as $k => $v)
        <a href="{{ route('admin.operators.index', $k ? ['status' => $k] : []) }}" @class(['active' => request('status', '') === $k])>{{ $v }}</a>
    @endforeach
</div>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Company</th><th>Contact</th><th>Country</th><th>Status</th><th class="num">Live bookings</th><th class="num">Credit limit</th><th class="num">Outstanding</th></tr></thead>
        <tbody>
        @forelse ($operators as $o)
            @php $out = $o->outstanding(); @endphp
            <tr>
                <td><a href="{{ route('admin.operators.show', $o) }}"><strong>{{ $o->company_name }}</strong></a><div class="small muted mono">{{ $o->code }}</div></td>
                <td>{{ $o->contact_name }}<div class="small muted">{{ $o->email }}</div></td>
                <td>{{ $o->country }}</td>
                <td><x-badge :status="$o->status" /></td>
                <td class="num">{{ $o->active_bookings }}</td>
                <td class="num">{{ money($o->credit_limit) }}</td>
                <td class="num" style="color:{{ $out > $o->credit_limit && $o->credit_limit > 0 ? 'var(--crit)' : 'inherit' }}">{{ money($out) }}</td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty title="No tour operators" icon="briefcase" /></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
