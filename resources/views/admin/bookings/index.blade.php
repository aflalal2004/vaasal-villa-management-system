@extends('layouts.admin')
@section('title', 'Bookings')
@section('content')
<x-page-header title="Bookings" sub="All reservations from every channel — website, admin, walk-in, phone, tour operators and OTAs — share one availability ledger.">
    @perm('bookings.manage')
        <a class="btn" href="{{ route('admin.frontdesk.walk-in') }}"><x-icon name="door" /> Walk-in</a>
        <a class="btn btn-primary" href="{{ route('admin.bookings.create') }}"><x-icon name="plus" /> New booking</a>
    @endperm
</x-page-header>

<form class="filters" method="get">
    <div class="field" style="flex:2 1 220px"><label for="q">Search</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Reference, guest, email, phone, OTA ref, group"></div>
    <div class="field"><label for="status">Status</label>
        <select id="status" name="status" data-autosubmit><option value="">Any status</option>
            @foreach (\App\Models\Booking::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
        </select></div>
    <div class="field"><label for="source">Source</label>
        <select id="source" name="source" data-autosubmit><option value="">Any source</option>
            @foreach (\App\Models\Booking::SOURCES as $k => $v)<option value="{{ $k }}" @selected(request('source') === $k)>{{ $v }}</option>@endforeach
        </select></div>
    <div class="field"><label for="operator">Tour operator</label>
        <select id="operator" name="operator" data-autosubmit><option value="">All</option>
            @foreach ($operators as $id => $name)<option value="{{ $id }}" @selected(request('operator') == $id)>{{ $name }}</option>@endforeach
        </select></div>
    <div class="field"><label for="from">Staying from</label><input id="from" type="date" name="from" value="{{ request('from') }}"></div>
    <div class="field"><label for="to">to</label><input id="to" type="date" name="to" value="{{ request('to') }}"></div>
    <div class="field"><label for="sort">Sort</label>
        <select id="sort" name="sort" data-autosubmit>
            <option value="arrival_desc" @selected(request('sort', 'arrival_desc') === 'arrival_desc')>Arrival, newest</option>
            <option value="arrival_asc" @selected(request('sort') === 'arrival_asc')>Arrival, oldest</option>
            <option value="created" @selected(request('sort') === 'created')>Recently created</option>
        </select></div>
    <button class="btn" type="submit"><x-icon name="filter" /> Apply</button>
    @if (request()->query())<a class="btn btn-ghost" href="{{ route('admin.bookings.index') }}">Clear</a>@endif
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Reference</th><th>Guest</th><th>Villa(s)</th><th>Arrival</th><th>Departure</th><th class="num">Nights</th><th>Source</th><th>Status</th><th class="num">Total</th></tr></thead>
            <tbody>
            @forelse ($bookings as $b)
                <tr>
                    <td><a class="mono" href="{{ route('admin.bookings.show', $b) }}">{{ $b->reference }}</a>
                        @if ($b->external_ref)<div class="small muted">{{ $b->external_ref }}</div>@endif</td>
                    <td>{{ $b->guest->fullName() }} @if($b->guest->is_vip)<x-badge tone="accent" label="VIP" />@endif
                        @if ($b->group_name)<div class="small muted">{{ $b->group_name }}</div>@endif</td>
                    <td>@foreach ($b->activeVillas as $bv)<span class="chip">{{ $bv->villa->code }}</span> @endforeach</td>
                    <td class="nowrap">{{ fmt_date($b->arrival) }}</td>
                    <td class="nowrap">{{ fmt_date($b->departure) }}</td>
                    <td class="num">{{ $b->nights() }}</td>
                    <td>{{ $b->operator?->company_name ?? $b->channel->name }}</td>
                    <td><x-badge :status="$b->status" :label="$b->statusLabel()" /></td>
                    <td class="num">{{ money($b->grand_total) }}</td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty title="No bookings match these filters" icon="booking">Try a different search or clear the filters.</x-empty></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $bookings->links() }}
</div>
@endsection
