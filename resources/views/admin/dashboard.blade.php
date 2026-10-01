@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
@php
    $hour = now()->hour;
    $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp
<x-page-header :title="$greet.', '.explode(' ', auth()->user()->name)[0]" :eyebrow="now()->format('l, d F Y')"
    sub="Live figures across rooms, restaurant, housekeeping, staff and distribution.">
    @perm('bookings.manage')<a class="btn btn-primary" href="{{ route('admin.bookings.create') }}"><x-icon name="plus" /> New booking</a>@endperm
    @perm('bookings.manage')<a class="btn" href="{{ route('admin.frontdesk.walk-in') }}"><x-icon name="door" /> Walk-in</a>@endperm
    @perm('calendar.view')<a class="btn" href="{{ route('admin.calendar') }}"><x-icon name="calendar" /> Calendar</a>@endperm
</x-page-header>

@if ($d['conflicts_open'] > 0)
    <div class="alert alert-danger"><strong>{{ $d['conflicts_open'] }} OTA booking conflict(s) need action.</strong>
        A channel booking arrived for dates with no free villa. <a href="{{ route('admin.conflicts.index') }}">Open the conflict queue <x-icon name="arrow-right" /></a></div>
@endif

<div class="stats">
    <x-stat label="Today's arrivals" :value="$d['arrivals']->count()" hint="Still to check in" :href="route('admin.frontdesk.index', ['tab' => 'arrivals'])" />
    <x-stat label="Today's departures" :value="$d['departures']->count()" hint="Still to check out" :href="route('admin.frontdesk.index', ['tab' => 'departures'])" />
    <x-stat label="Occupied villas" :value="$d['occupied_villas'].' / '.$d['villa_count']" :pct="$d['villa_count'] ? round($d['occupied_villas'] / $d['villa_count'] * 100) : 0" />
    <x-stat label="Available villas" :value="$d['available_tonight']" hint="Sellable tonight" :href="route('admin.calendar')" />
    <x-stat label="Needs cleaning" :value="$d['needs_cleaning']" :hint="$d['hk_inspections'].' awaiting inspection'" :href="route('admin.housekeeping.index')" />
    <x-stat label="Today's revenue" :value="money($d['revenue_today'])" hint="Net, all departments" class="accent" />
    @perm('pos.reports')<x-stat label="POS sales today" :value="money($d['pos_sales_today'])" :hint="$d['pos_open_checks'].' open check(s)'" :href="route('pos.dashboard')" />@endperm
    <x-stat label="Pending bookings" :value="$d['pending_bookings']" hint="Hold, tentative or pending" :href="route('admin.bookings.index')" />
</div>

@include('admin.partials.quick-actions')

<div class="card mb">
    <div class="card-head"><h2><x-icon name="villa" /> Villa status board</h2><span class="row small"><a href="{{ route('admin.calendar') }}"><x-icon name="calendar" /> Booking calendar</a></span></div>
    <div class="card-body">@include('admin.partials.villa-board', ['board' => $d['board']])</div>
</div>

<div class="grid cols-2 mb">
    <div class="card">
        <div class="card-head"><h2><x-icon name="card" /> Recent payments</h2>@perm('payments.receive|invoices.view')<a class="small" href="{{ route('admin.payments.index') }}">All payments</a>@endperm</div>
        <div class="table-wrap"><table class="table"><tbody>
            @forelse ($d['recent_payments'] as $p)
                <tr><td class="small nowrap">{{ fmt_dt($p->paid_at) }}</td><td>{{ $p->booking?->guest?->fullName() ?? '—' }}<div class="small muted">{{ $p->booking?->reference }}</div></td><td class="small">{{ $p->methodLabel() }}</td><td class="num">{{ money($p->amount) }}</td></tr>
            @empty
                <tr><td class="muted small">No payments yet.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>
    @perm('pos.shift|pos.reports|pos.shift_review')
    <div class="card">
        <div class="card-head"><h2><x-icon name="drawer" /> POS cashier status</h2>@perm('pos.shift|pos.reports|pos.shift_review')<a class="small" href="{{ route('pos.today') }}">Cashier today</a>@endperm</div>
        <div class="table-wrap"><table class="table"><tbody>
            @forelse ($d['cashiers'] as $x)
                <tr><td>{{ $x['shift']->user->name }}<div class="small muted">{{ $x['shift']->outlet->name }} · since {{ $x['shift']->opened_at->format('H:i') }}</div></td><td><x-badge status="active" label="Drawer open" /></td><td class="num">{{ money($x['expected']) }}<div class="small muted">expected cash</div></td></tr>
            @empty
                <tr><td class="muted small">No cashier drawers open.</td></tr>
            @endforelse
        </tbody></table></div>
        @if ($d['shifts_to_review'])<div class="card-body small"><x-icon name="eye" /> {{ $d['shifts_to_review'] }} closed shift(s) awaiting manager review. @perm('pos.shift_review')<a href="{{ route('pos.shifts.index', ['review' => 'pending']) }}">Review</a>@endperm</div>@endif
    </div>
    @endperm
</div>

<div class="stats">
    <x-stat label="Occupancy tonight" :value="$d['occupancy_today'].'%'" :pct="$d['occupancy_today']" :hint="($d['villa_count'] - $d['available_tonight']).' of '.$d['villa_count'].' villas'" class="accent" />
        <x-stat label="In-house" :value="$d['in_house']" :hint="$d['checked_in_today'].' checked in · '.$d['checked_out_today'].' out today'" />
            <x-stat label="Revenue month to date" :value="money($d['revenue_mtd'])" :hint="now()->format('F')" />
    <x-stat label="Payments today" :value="money($d['payments_today'])" hint="Front office, online & POS" />
    <x-stat label="Outstanding" :value="money($d['outstanding_guest'] + $d['outstanding_city'])" :hint="'Guests '.money($d['outstanding_guest']).' · Operators '.money($d['outstanding_city'])" />
</div>

<div class="grid cols-main mb">
    <div class="card">
        <div class="card-head"><h2>Occupancy — last 7 days & next 14</h2><span class="muted small">% of villas sold</span></div>
        <div class="card-body"><x-bar-chart :data="$d['trend']" suffix="%" :height="230" /></div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Alerts</h2><a class="small" href="{{ route('admin.notifications.index') }}">All notifications</a></div>
        <ul class="list">
            @forelse ($alerts as $n)
                <li>
                    <x-badge :tone="['danger' => 'danger', 'warning' => 'warning', 'success' => 'success'][$n->level] ?? 'info'" :label="ucfirst(explode('.', $n->type)[0])" />
                    <a href="{{ route('admin.notifications.open', $n) }}" style="color:var(--ink);flex:1;min-width:0" class="small">{{ $n->title }}</a>
                </li>
            @empty
                <li class="muted small">No unread alerts.</li>
            @endforelse
        </ul>
    </div>
</div>

<div class="grid cols-2 mb">
    <div class="card">
        <div class="card-head"><h2>Arrivals today</h2><a class="small" href="{{ route('admin.frontdesk.index') }}">Front desk</a></div>
        <div class="table-wrap">
            <table class="table">
                <tbody>
                @forelse ($d['arrivals'] as $b)
                    <tr>
                        <td><a href="{{ route('admin.bookings.show', $b) }}">{{ $b->guest->fullName() }}</a><div class="small muted">{{ $b->reference }} · {{ $b->sourceLabel() }}</div></td>
                        <td>@foreach ($b->activeVillas as $bv)<span class="chip">{{ $bv->villa->code }}</span> <x-badge :status="$bv->villa->hk_status" />@endforeach</td>
                        <td class="actions">@perm('frontdesk.checkin')<a class="btn btn-sm btn-primary" href="{{ route('admin.frontdesk.checkin', $b) }}">Check in</a>@endperm</td>
                    </tr>
                @empty
                    <tr><td class="muted">No more arrivals today.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Departures today</h2></div>
        <div class="table-wrap">
            <table class="table">
                <tbody>
                @forelse ($d['departures'] as $b)
                    <tr>
                        <td><a href="{{ route('admin.bookings.show', $b) }}">{{ $b->guest->fullName() }}</a><div class="small muted">{{ $b->reference }}</div></td>
                        <td>@foreach ($b->activeVillas as $bv)<span class="chip">{{ $bv->villa->code }}</span>@endforeach</td>
                        <td class="actions">@perm('frontdesk.checkout')<a class="btn btn-sm" href="{{ route('admin.frontdesk.checkout', $b) }}">Check out</a>@endperm</td>
                    </tr>
                @empty
                    <tr><td class="muted">No pending departures.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid cols-4 mb">
    <div class="card">
        <div class="card-head"><h2>Housekeeping</h2></div>
        <div class="card-body stack" style="gap:10px">
            <div class="row">
                @foreach (\App\Models\Villa::HK_LABELS as $k => $lbl)
                    <x-badge :status="$k" :label="$lbl.' '.($d['hk'][$k] ?? 0)" />
                @endforeach
            </div>
            <div class="small muted">{{ $d['hk_open_tasks'] }} open tasks · {{ $d['hk_inspections'] }} awaiting inspection</div>
            <div class="small">{{ $d['open_tickets'] }} open maintenance ticket(s) · <strong>{{ $d['out_of_order'] }}</strong> out of order</div>
            <a class="small" href="{{ route('admin.housekeeping.index') }}">Housekeeping board <x-icon name="arrow-right" /></a>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Staff today</h2></div>
        <div class="card-body stack" style="gap:6px">
            <div class="row between"><span class="muted">On duty now</span><strong>{{ $d['staff_on_duty'] }}</strong></div>
            <div class="row between"><span class="muted">Present</span><strong>{{ $d['staff_present'] }} / {{ $d['staff_active'] }}</strong></div>
            <div class="row between"><span class="muted">Late arrivals</span><strong>{{ $d['staff_late'] }}</strong></div>
            @perm('attendance.manage')<a class="small" href="{{ route('admin.staff.attendance.index') }}">Attendance <x-icon name="arrow-right" /></a>@endperm
        </div>
    </div>
    @perm('pos.reports|inventory.view')
    <div class="card">
        <div class="card-head"><h2>Restaurant & stock</h2></div>
        <div class="card-body stack" style="gap:6px">
            <div class="row between"><span class="muted">POS sales today</span><strong>{{ money($d['pos_sales_today']) }}</strong></div>
            <div class="row between"><span class="muted">Open checks</span><strong>{{ $d['pos_open_checks'] }}</strong></div>
            <div class="row between"><span class="muted">Low-stock items</span><strong style="{{ $d['low_stock_count'] ? 'color:var(--crit)' : '' }}">{{ $d['low_stock_count'] }}</strong></div>
            @foreach ($d['low_stock'] as $s)<span class="small"><x-badge tone="warning" label="Low" /> {{ $s->name }} — {{ $s->current_qty }} {{ $s->unit }}</span>@endforeach
        </div>
    </div>
    @endperm
    <div class="card">
        <div class="card-head"><h2>Distribution & access</h2></div>
        <div class="card-body stack" style="gap:6px">
            <div class="row between"><span class="muted">OTA bookings (month)</span><strong>{{ $d['ota_bookings_mtd'] }}</strong></div>
            <div class="row between"><span class="muted">Tour operator bookings</span><strong>{{ $d['operator_bookings_mtd'] }}</strong></div>
            <div class="row between"><span class="muted">Active key cards</span><strong>{{ $d['cards_active'] }}</strong></div>
            <div class="row between"><span class="muted">Cards issued / revoked today</span><strong>{{ $d['cards_today']['issued'] ?? 0 }} / {{ $d['cards_today']['revoked'] ?? 0 }}</strong></div>
        </div>
    </div>
</div>

<div class="grid cols-main">
    <div class="card">
        <div class="card-head"><h2>Recent bookings</h2><a class="small" href="{{ route('admin.bookings.index') }}">All bookings</a></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Reference</th><th>Guest</th><th>Stay</th><th>Channel</th><th>Status</th><th class="num">Total</th></tr></thead>
                <tbody>
                @foreach ($d['recent_bookings'] as $b)
                    <tr>
                        <td><a href="{{ route('admin.bookings.show', $b) }}" class="mono">{{ $b->reference }}</a></td>
                        <td>{{ $b->guest->fullName() }}</td>
                        <td class="nowrap">{{ fmt_date($b->arrival, 'd M') }} → {{ fmt_date($b->departure, 'd M') }}</td>
                        <td>{{ $b->channel->name }}</td>
                        <td><x-badge :status="$b->status" :label="$b->statusLabel()" /></td>
                        <td class="num">{{ money($b->grand_total) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Revenue by department</h2><span class="small muted">{{ now()->format('F') }}, net</span></div>
        <div class="card-body"><x-hbars :data="$d['revenue_by_dept']" money /></div>
    </div>
</div>
@endsection
