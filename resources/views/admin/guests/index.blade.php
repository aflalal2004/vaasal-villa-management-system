@extends('layouts.admin')
@section('title', 'Guests')
@section('content')
<x-page-header title="Guests" sub="Guest profiles are shared by every booking channel and matched by email." />
<form class="filters" method="get">
    <div class="field" style="flex:2 1 240px"><label for="q">Search</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Name, email or phone"></div>
    <div class="field"><label for="flag">Flag</label><select id="flag" name="flag" data-autosubmit><option value="">All guests</option><option value="vip" @selected(request('flag') === 'vip')>VIP</option><option value="blacklisted" @selected(request('flag') === 'blacklisted')>Blacklisted</option></select></div>
    <button class="btn" type="submit">Search</button>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Guest</th><th>Contact</th><th>Country</th><th class="num">Stays</th><th class="num">Lifetime spend</th><th></th></tr></thead>
        <tbody>
        @forelse ($guests as $g)
            <tr>
                <td><a href="{{ route('admin.guests.show', $g) }}">{{ $g->fullName() }}</a>
                    @if($g->is_vip)<x-badge tone="accent" label="VIP" />@endif @if($g->is_blacklisted)<x-badge tone="danger" label="Blacklisted" />@endif</td>
                <td class="small">{{ $g->email }}<br>{{ $g->phone }}</td>
                <td>{{ $g->country ?? '—' }}</td>
                <td class="num">{{ $g->bookings_count }}</td>
                <td class="num">{{ money($g->spend ?? 0) }}</td>
                <td class="actions">@perm('bookings.manage')<a class="btn btn-sm" href="{{ route('admin.bookings.create', ['guest' => $g->id]) }}">Book</a>@endperm</td>
            </tr>
        @empty
            <tr><td colspan="6"><x-empty title="No guests found" icon="users" /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $guests->links() }}
</div>
@endsection
