<nav class="tabs" aria-label="Access control">
    <a href="{{ route('admin.keycards.index') }}" @class(['active' => request()->routeIs('admin.keycards.index') && ! request()->hasAny(['type', 'status'])])><x-icon name="key" /> All cards</a>
    <a href="{{ route('admin.keycards.index', ['type' => 'guest']) }}" @class(['active' => request('type') === 'guest'])><x-icon name="bed" /> Guest cards</a>
    <a href="{{ route('admin.keycards.index', ['type' => 'staff']) }}" @class(['active' => request('type') === 'staff'])><x-icon name="users" /> Staff cards</a>
    <a href="{{ route('admin.keycards.index', ['status' => 'lost']) }}" @class(['active' => request('status') === 'lost'])><x-icon name="alert" /> Lost cards</a>
    @perm('keycards.issue')<a href="{{ route('admin.keycards.index') }}#issue"><x-icon name="plus" /> Assign / revoke</a>@endperm
    <a href="{{ route('admin.keycards.logs') }}" @class(['active' => request()->routeIs('admin.keycards.logs')])><x-icon name="history" /> Access logs</a>
    @perm('keycards.manage')
        <a href="{{ route('admin.access.devices') }}" @class(['active' => request()->routeIs('admin.access.devices')])><x-icon name="device" /> RFID devices</a>
        @if (config('vaasal.locks.rfid_simulator'))<a href="{{ route('admin.access.simulator') }}" @class(['active' => request()->routeIs('admin.access.simulator')])><x-icon name="rfid" /> Simulator</a>@endif
        <a href="{{ route('admin.keycards.bridge') }}" @class(['active' => request()->routeIs('admin.keycards.bridge')])><x-icon name="link" /> Lock bridge</a>
    @endperm
</nav>
