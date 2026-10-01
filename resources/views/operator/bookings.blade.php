@extends('layouts.operator')
@section('title', 'Bookings')
@section('content')
<x-page-header title="Booking history">
    <a class="btn btn-primary" href="{{ route('operator.bookings.create') }}"><x-icon name="plus" /> New booking</a>
</x-page-header>
<form class="filters" method="get">
    <div class="field" style="flex:2 1 200px"><label for="q">Reference / group</label><input id="q" type="search" name="q" value="{{ request('q') }}"></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status" data-autosubmit><option value="">All</option>@foreach (\App\Models\Booking::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Ref</th><th>Group / lead guest</th><th>Villas</th><th>Stay</th><th>Status</th><th class="num">Total</th><th></th></tr></thead>
        <tbody>
        @forelse ($bookings as $b)
            <tr><td><a class="mono" href="{{ route('operator.bookings.show', $b) }}">{{ $b->reference }}</a></td><td>{{ $b->group_name ?? $b->guest->fullName() }}</td>
                <td>{{ $b->activeVillas->pluck('villa.code')->implode(', ') }}</td><td class="nowrap small">{{ fmt_date($b->arrival) }} → {{ fmt_date($b->departure) }}</td>
                <td><x-badge :status="$b->status" :label="$b->statusLabel()" /></td><td class="num">{{ money($b->grand_total) }}</td>
                <td class="actions"><a class="btn btn-sm btn-ghost" href="{{ route('operator.bookings.voucher', $b) }}" target="_blank">Voucher</a></td></tr>
        @empty
            <tr><td colspan="7"><x-empty title="No bookings yet" icon="booking" /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $bookings->links() }}
</div>
@endsection
