@extends('layouts.admin')
@section('title', $guest->fullName())
@section('content')
<x-page-header :title="$guest->fullName()" :crumbs="['Guests' => route('admin.guests.index')]" :sub="($guest->country ?? 'Country not set').' · '.$guest->bookings->count().' booking(s) · lifetime '.money($lifetime)">
    @perm('guests.manage')<a class="btn" href="{{ route('admin.guests.edit', $guest) }}"><x-icon name="edit" /> Edit profile</a>@endperm
    @perm('bookings.manage')<a class="btn btn-primary" href="{{ route('admin.bookings.create', ['guest' => $guest->id]) }}"><x-icon name="plus" /> New booking</a>@endperm
</x-page-header>
<div class="grid cols-main">
    <div class="card">
        <div class="card-head"><h2>Stay history</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Reference</th><th>Stay</th><th>Villa</th><th>Channel</th><th>Status</th><th class="num">Total</th></tr></thead>
            <tbody>
            @foreach ($guest->bookings as $b)
                <tr><td><a class="mono" href="{{ route('admin.bookings.show', $b) }}">{{ $b->reference }}</a></td>
                    <td class="nowrap">{{ fmt_date($b->arrival) }} → {{ fmt_date($b->departure) }}</td>
                    <td>{{ $b->activeVillas->pluck('villa.code')->implode(', ') }}</td><td>{{ $b->channel->name }}</td>
                    <td><x-badge :status="$b->status" :label="$b->statusLabel()" /></td><td class="num">{{ money($b->grand_total) }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Profile</h2></div>
        <div class="card-body">
            <dl class="dl">
                <dt>Email</dt><dd>{{ $guest->email ?? '—' }}</dd>
                <dt>Phone</dt><dd>{{ $guest->phone ?? '—' }}</dd>
                <dt>Nationality</dt><dd>{{ $guest->nationality ?? '—' }}</dd>
                <dt>Date of birth</dt><dd>{{ fmt_date($guest->date_of_birth) }}</dd>
                <dt>ID</dt><dd>{{ $guest->id_type ? ucfirst($guest->id_type).' '.$guest->maskedId().' · exp '.fmt_date($guest->id_expiry) : 'Not captured' }}
                    @if ($guest->id_document_path) @perm('guests.manage')<br><a href="{{ route('admin.guests.id-document', $guest) }}" target="_blank">View ID scan</a>@endperm @endif</dd>
                <dt>Address</dt><dd>{{ $guest->address ?? '—' }}</dd>
                <dt>Marketing</dt><dd>{{ $guest->marketing_consent ? 'Opted in' : 'No consent' }}</dd>
                <dt>Preferences</dt><dd>{{ $guest->preferences ?? '—' }}</dd>
                <dt>Notes</dt><dd>{{ $guest->notes ?? '—' }}</dd>
            </dl>
        </div>
    </div>
</div>
@endsection
