@extends('layouts.pos')
@section('title', 'Restaurant dashboard')
@section('content')
@php
    $u = auth()->user();
    $h = now()->hour;
    $greet = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
    $statusTone = ['open' => 'warning', 'billed' => 'info', 'paid' => 'success', 'charged_to_room' => 'accent', 'void' => 'danger', 'refunded' => 'danger'];
@endphp
<x-page-header :title="$greet.', '.explode(' ', $u->name)[0]" :eyebrow="now()->format('l, d F Y').' · Restaurant POS'" sub="Live figures from orders, kitchen tickets, payments, reservations and stock." />

<section aria-labelledby="qa-title" class="mb">
    <h2 id="qa-title" class="section-heading">Quick Actions</h2>
    <div class="qa-grid">
        @perm('pos.order')
        <a class="qa-card qa-hero" href="{{ route('pos.terminal', ['start' => 'menu']) }}"><span class="qa-ic"><x-icon name="receipt" /></span><strong>New Order</strong><small>Start a fresh POS order</small></a>
        <a class="qa-card" href="{{ route('pos.terminal') }}"><span class="qa-ic"><x-icon name="table" /></span><strong>Open Table</strong><small>Browse the floor plan</small></a>
        <a class="qa-card" href="{{ route('pos.terminal', ['start' => 'takeaway']) }}"><span class="qa-ic"><x-icon name="takeaway" /></span><strong>Take Away</strong><small>Quick take-away order</small></a>
        <a class="qa-card" href="{{ route('pos.terminal', ['start' => 'room']) }}"><span class="qa-ic"><x-icon name="villa" /></span><strong>Room Charge</strong><small>Bill directly to a villa</small></a>
        @endperm
        @perm('pos.reservations')<a class="qa-card" href="{{ route('pos.reservations.index') }}"><span class="qa-ic"><x-icon name="calendar" /></span><strong>Reservation</strong><small>Book or manage a table</small></a>@endperm
        @perm('pos.bill|pos.reports')<a class="qa-card" href="{{ route('pos.orders.index') }}"><span class="qa-ic"><x-icon name="file" /></span><strong>View Orders</strong><small>Today's order history</small></a>@endperm
    </div>
</section>

<div class="kpi-grid mb">
    <div class="kpi"><span class="kpi-ic"><x-icon name="currency" /></span><strong>{{ money($d['sales_today']) }}</strong><small>Today's Sales{{ $d['refunds_today'] > 0 ? ' · refunds '.money($d['refunds_today']) : '' }}</small></div>
    <div class="kpi"><span class="kpi-ic"><x-icon name="receipt" /></span><strong>{{ $d['orders_today'] }}</strong><small>Orders Today · {{ $d['open_checks'] }} open</small></div>
    <div class="kpi"><span class="kpi-ic"><x-icon name="table" /></span><strong>{{ $d['tables_occupied'] }} / {{ $d['tables_total'] }}</strong><small>Occupied Tables</small></div>
    <div class="kpi"><span class="kpi-ic"><x-icon name="fire" /></span><strong>{{ $d['pending_kot'] }}</strong><small>Pending KOT</small></div>
    <div class="kpi"><span class="kpi-ic"><x-icon name="villa" /></span><strong>{{ $d['room_charges_today'] }}</strong><small>Room Charges · {{ money($d['room_charges_amount']) }}</small></div>
</div>

<div class="grid dash-2 mb">
    <div class="card">
        <div class="card-head"><h2 class="serif">Sales — Last 7 Days</h2>@perm('pos.reports')<a class="small" href="{{ route('pos.sales') }}">Sales report</a>@endperm</div>
        <div class="card-body">
            <div class="week-bars" role="img" aria-label="Sales for the last seven days">
                @foreach ($d['week'] as $day)
                    <div class="wb-col" title="{{ $day['date'] }} · {{ money($day['total']) }} · {{ $day['orders'] }} orders">
                        <span class="wb-val">{{ $day['total'] > 0 ? number_format($day['total'] / 1000, 1).'k' : '—' }}</span>
                        <span class="wb-bar {{ $loop->last ? 'today' : '' }}" style="--h: {{ max(3, round($day['total'] / $d['week_max'] * 100)) }}%"></span>
                        <span class="wb-lbl">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2 class="serif">Popular Dishes</h2><span class="small muted">Last 30 days</span></div>
        <div class="card-body">
            <div class="dish-grid">
                @forelse ($d['popular'] as $p)
                    <div class="dish">
                        <div class="dish-img">
                            @if ($p['item']->imageUrl())<img src="{{ $p['item']->imageUrl() }}" alt="{{ $p['item']->name }}" loading="lazy" onerror="this.remove()">@endif
                            <x-icon name="utensils" />
                        </div>
                        <strong>{{ $p['item']->name }}</strong>
                        <small>{{ money($p['item']->price) }} · {{ rtrim(rtrim(number_format($p['qty'], 1), '0'), '.') }} sold</small>
                    </div>
                @empty
                    <x-empty title="No sales in the last 30 days" icon="utensils" />
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="grid dash-2 mb">
    <div class="card">
        <div class="card-head"><h2 class="serif">Recent Orders</h2>@perm('pos.bill|pos.reports')<a class="small" href="{{ route('pos.orders.index') }}">See all</a>@endperm</div>
        <ul class="order-list">
            @forelse ($d['recent'] as $o)
                @php $first = $o->items->first()?->menuItem; @endphp
                <li>
                    <span class="ol-img">@if ($first?->imageUrl())<img src="{{ $first->imageUrl() }}" alt="" loading="lazy" onerror="this.remove()">@endif<x-icon :name="$o->type === 'takeaway' ? 'takeaway' : ($o->type === 'room_service' ? 'villa' : 'utensils')" /></span>
                    <span class="ol-main"><a href="{{ route('pos.order', $o) }}"><strong>{{ $o->order_no }} · {{ $o->guest_name ?: ($o->table ? 'Table '.$o->table->name : 'Walk-in') }}</strong></a>
                        <small>{{ ['dine_in' => 'Dine in', 'takeaway' => 'Take away', 'room_service' => 'Room service · Villa '.$o->villa?->code][$o->type] ?? label($o->type) }} · {{ $o->opened_at->format('g:i A') }}</small></span>
                    <span class="ol-end"><strong>{{ money($o->total) }}</strong><x-badge :tone="$statusTone[$o->status] ?? 'neutral'" :label="$o->statusLabel()" /></span>
                </li>
            @empty
                <li><x-empty title="No orders yet" icon="receipt" /></li>
            @endforelse
        </ul>
    </div>
    <div class="stack">
        <div class="card">
            <div class="card-head"><h2 class="serif">Upcoming Reservations</h2>@perm('pos.reservations')<a class="small" href="{{ route('pos.reservations.index') }}">See all</a>@endperm</div>
            <ul class="res-list">
                @forelse ($d['reservations'] as $r)
                    <li><x-icon name="calendar" /><span><strong>{{ $r->guest_name }}</strong><small>{{ $r->reserved_for->format('g:i A') }} · {{ $r->party_size }} guests{{ $r->table ? ' · Table '.$r->table->name : '' }}</small></span>
                        <x-badge :tone="['confirmed' => 'info', 'pending' => 'warning', 'seated' => 'success'][$r->status] ?? 'neutral'" :label="\App\Models\TableReservation::STATUSES[$r->status]" /></li>
                @empty
                    <li class="muted small">No upcoming reservations.</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-head"><h2 class="serif">Low Stock Alerts</h2>@perm('inventory.view')<a class="small" href="{{ route('pos.inventory.items.index', ['filter' => 'low']) }}">See all ({{ $d['low_stock_count'] }})</a>@endperm</div>
            <ul class="res-list">
                @forelse ($d['low_stock'] as $s)
                    <li><x-icon name="alert" /><span><strong>{{ $s->name }}</strong><small>On hand {{ rtrim(rtrim(number_format($s->current_qty, 2), '0'), '.') }} {{ $s->unit }} · reorder at {{ rtrim(rtrim(number_format($s->reorder_level, 2), '0'), '.') }}</small></span>
                        <x-badge tone="danger" label="Low" /></li>
                @empty
                    <li class="muted small">All stock above reorder level.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
