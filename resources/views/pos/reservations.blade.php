@extends('layouts.pos')
@section('title', 'Reservations')
@section('content')
<x-page-header title="Table reservations" :eyebrow="$date->format('l, d F Y')" sub="Book tables, confirm, seat the party (opens the check at the table) and track no-shows.">
    <form class="row" method="get" style="gap:6px">
        <a class="btn btn-ghost" href="{{ route('pos.reservations.index', ['date' => $date->copy()->subDay()->toDateString()]) }}" aria-label="Previous day"><x-icon name="arrow-left" /></a>
        <input type="date" name="date" value="{{ $date->toDateString() }}" aria-label="Date" data-autosubmit>
        <a class="btn btn-ghost" href="{{ route('pos.reservations.index', ['date' => $date->copy()->addDay()->toDateString()]) }}" aria-label="Next day"><x-icon name="arrow-right" /></a>
    </form>
</x-page-header>

<div class="kpi-grid mb">
    @foreach (['confirmed' => 'calendar', 'pending' => 'clock', 'seated' => 'table', 'completed' => 'check-circle', 'no_show' => 'x-circle'] as $s => $ic)
        <a class="kpi kpi-link" href="{{ route('pos.reservations.index', ['date' => $date->toDateString(), 'status' => $s]) }}">
            <span class="kpi-ic"><x-icon :name="$ic" /></span><strong>{{ $counts[$s]->n ?? 0 }}</strong><small>{{ \App\Models\TableReservation::STATUSES[$s] }} · {{ (int) ($counts[$s]->pax ?? 0) }} guests</small></a>
    @endforeach
</div>

<div class="grid cols-main">
    <div class="card">
        <div class="card-head"><h2 class="serif">Bookings for the day</h2>@if (request('status'))<a class="small" href="{{ route('pos.reservations.index', ['date' => $date->toDateString()]) }}">Show all</a>@endif</div>
        <ul class="res-list res-list-lg">
            @forelse ($list as $r)
                <li>
                    <span class="res-time"><strong>{{ $r->reserved_for->format('g:i') }}</strong><small>{{ $r->reserved_for->format('A') }}</small></span>
                    <span><strong>{{ $r->guest_name }}</strong>
                        <small>{{ $r->party_size }} guests · {{ $r->table ? $r->outlet->name.' · Table '.$r->table->name : 'No table yet' }}{{ $r->phone ? ' · '.$r->phone : '' }}</small>
                        @if ($r->booking)<small class="res-guest"><x-icon name="villa" /> In-house · Villa {{ $r->booking->activeVillas->pluck('villa.code')->implode(', ') ?: '—' }} · can charge to villa</small>@endif
                        @if ($r->notes)<small>{{ $r->notes }}</small>@endif</span>
                    <span class="res-actions">
                        <x-badge :tone="['confirmed' => 'info', 'pending' => 'warning', 'seated' => 'success', 'completed' => 'neutral', 'cancelled' => 'danger', 'no_show' => 'danger'][$r->status]" :label="\App\Models\TableReservation::STATUSES[$r->status]" />
                        @if ($r->status === 'pending')
                            <form method="post" action="{{ route('pos.reservations.status', $r) }}">@csrf<input type="hidden" name="status" value="confirmed"><button class="btn btn-sm" type="submit"><x-icon name="check" /> Confirm</button></form>
                        @endif
                        @if ($r->status === 'confirmed')
                            <form method="post" action="{{ route('pos.reservations.status', $r) }}">@csrf<input type="hidden" name="status" value="seated"><button class="btn btn-sm btn-primary" type="submit" @disabled(! $r->pos_table_id)><x-icon name="table" /> Seat</button></form>
                            <form method="post" action="{{ route('pos.reservations.status', $r) }}" data-confirm="Mark {{ $r->guest_name }} as a no-show?">@csrf<input type="hidden" name="status" value="no_show"><button class="btn btn-sm btn-ghost" type="submit">No-show</button></form>
                        @endif
                        @if ($r->status === 'seated' && $r->order)<a class="btn btn-sm" href="{{ route('pos.order', $r->order) }}"><x-icon name="receipt" /> {{ $r->order->order_no }}</a>@endif
                        @if (in_array($r->status, ['pending', 'confirmed'], true))
                            <a class="btn btn-sm btn-ghost" href="{{ route('pos.reservations.index', ['date' => $date->toDateString(), 'edit' => $r->id]) }}" aria-label="Edit"><x-icon name="edit" /></a>
                            <form method="post" action="{{ route('pos.reservations.status', $r) }}" data-confirm="Cancel the reservation for {{ $r->guest_name }}?" data-danger>@csrf<input type="hidden" name="status" value="cancelled"><button class="btn btn-sm btn-ghost" type="submit" aria-label="Cancel"><x-icon name="x" /></button></form>
                        @endif
                    </span>
                </li>
            @empty
                <li><x-empty title="No reservations" icon="calendar">Nothing booked for this day{{ request('status') ? ' with that status' : '' }}.</x-empty></li>
            @endforelse
        </ul>
    </div>

    <form method="post" action="{{ $edit ? route('pos.reservations.update', $edit) : route('pos.reservations.store') }}" class="card" style="align-self:start">
        @csrf @if ($edit) @method('put') @endif
        <div class="card-head"><h2 class="serif">{{ $edit ? 'Edit reservation' : 'New reservation' }}</h2>@if ($edit)<a class="small" href="{{ route('pos.reservations.index', ['date' => $date->toDateString()]) }}">New instead</a>@endif</div>
        <div class="card-body form-grid">
            <x-input name="guest_name" label="Guest name" :value="$edit?->guest_name" required col="f-12" />
            <x-input name="phone" label="Phone" :value="$edit?->phone" col="f-6" />
            <x-input name="party_size" type="number" min="1" max="40" label="Guests" :value="$edit?->party_size ?? 2" required col="f-6" />
            <x-input name="reserved_for" type="datetime-local" label="Date & time" :value="$edit?->reserved_for ?? $date->copy()->setTime(19, 30)" required col="f-6" />
            <x-select name="duration_minutes" label="Duration" :options="[60 => '1 hour', 90 => '1½ hours', 120 => '2 hours', 180 => '3 hours']" :value="$edit?->duration_minutes ?? 90" col="f-6" />
            <x-select name="outlet_id" label="Outlet" :options="$outlets" :value="$edit?->outlet_id" required col="f-6" />
            <x-select name="pos_table_id" label="Table" :options="$tables" :value="$edit?->pos_table_id" placeholder="— assign later —" col="f-6" />
            <x-select name="booking_id" label="In-house hotel guest (optional)" :options="$inHouse" :value="$edit?->booking_id" placeholder="— not staying with us —" col="f-12"
                help="Links the table to the stay so the bill can be charged to the villa." />
            <x-select name="status" label="Status" :options="['confirmed' => 'Confirmed', 'pending' => 'Pending (awaiting confirmation)']" :value="$edit?->status ?? 'confirmed'" col="f-12" />
            <x-textarea name="notes" label="Notes" :value="$edit?->notes" rows="2" />
        </div>
        <div class="card-foot"><button class="btn btn-primary" type="submit"><x-icon name="check" /> Save reservation</button></div>
    </form>
</div>
@endsection
