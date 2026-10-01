{{-- Large, permission-aware quick access tiles (front desk, dashboard). --}}
@php
    $today = now()->toDateString();
    $actions = [
        ['New booking', 'plus', route('admin.bookings.create'), 'bookings.manage', true],
        ["Today's arrivals", 'door', route('admin.frontdesk.index', ['tab' => 'arrivals']), 'frontdesk.checkin|bookings.view', false],
        ["Today's departures", 'door-open', route('admin.frontdesk.index', ['tab' => 'departures']), 'frontdesk.checkout|bookings.view', false],
        ['Check in', 'key', route('admin.frontdesk.index', ['tab' => 'arrivals']), 'frontdesk.checkin', false],
        ['Check out', 'logout', route('admin.frontdesk.index', ['tab' => 'departures']), 'frontdesk.checkout', false],
        ['Room availability', 'calendar', route('admin.calendar'), 'calendar.view', false],
        ['Guests', 'users', route('admin.guests.index'), 'guests.view', false],
        ['Invoices', 'file', route('admin.invoices.index'), 'invoices.view', false],
        ['POS', 'utensils', route('pos.terminal'), 'pos.order|pos.bill', false],
        ['Cashier today', 'wallet', route('pos.today'), 'pos.shift|pos.reports|pos.shift_review', false],
        ['Housekeeping', 'broom', route('admin.housekeeping.index'), 'housekeeping.view', false],
        ['Key cards', 'rfid', route('admin.keycards.index'), 'keycards.view', false],
        ['Reports', 'chart', route('admin.reports.index'), 'reports.operational|reports.financial', false],
    ];
    $visible = array_values(array_filter($actions, fn ($a) => auth()->user()->hasPermission($a[3])));
@endphp
@if ($visible)
<section class="quick" aria-labelledby="quick-title">
    <h2 id="quick-title" class="section-title"><x-icon name="grid" /> Quick access</h2>
    <div class="quick-grid">
        @foreach ($visible as [$label, $icon, $url, $perm, $primary])
            <a href="{{ $url }}" @class(['quick-tile', 'primary' => $primary])><span class="qt-ic"><x-icon :name="$icon" /></span><span class="qt-label">{{ $label }}</span></a>
        @endforeach
    </div>
</section>
@endif
