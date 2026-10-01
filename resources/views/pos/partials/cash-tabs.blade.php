<nav class="tabs" aria-label="Cashier">
    <a href="{{ route('pos.today') }}" @class(['active' => request()->routeIs('pos.today')])><x-icon name="chart" /> Today</a>
    <a href="{{ route('pos.shifts.index') }}" @class(['active' => request()->routeIs('pos.shifts.*')])><x-icon name="drawer" /> Shifts</a>
    <a href="{{ route('pos.cash-movements') }}" @class(['active' => request()->routeIs('pos.cash-movements')])><x-icon name="coins" /> Cash movements</a>
    @perm('pos.shift|pos.shift_review')<a href="{{ route('pos.day-end') }}" @class(['active' => request()->routeIs('pos.day-end')])><x-icon name="lock" /> Day-end closing</a>@endperm
</nav>
