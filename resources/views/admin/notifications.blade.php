@extends('layouts.admin')
@section('title', 'Notifications')
@section('content')
<x-page-header title="Notifications" sub="Alerts for bookings, payments, arrivals, housekeeping, maintenance, stock and operator accounts.">
    <form method="post" action="{{ route('admin.notifications.read-all') }}">@csrf<button class="btn" type="submit"><x-icon name="check" /> Mark all as read</button></form>
</x-page-header>
<div class="tabs">
    <a href="{{ route('admin.notifications.index') }}" @class(['active' => ! request('filter') && ! request('type')])>All</a>
    <a href="{{ route('admin.notifications.index', ['filter' => 'unread']) }}" @class(['active' => request('filter') === 'unread'])>Unread</a>
    @foreach (['booking' => 'Bookings', 'payment' => 'Payments', 'hk' => 'Housekeeping', 'maintenance' => 'Maintenance', 'inventory' => 'Stock', 'keycard' => 'Key cards', 'operator' => 'Operators', 'leave' => 'Leave'] as $k => $v)
        <a href="{{ route('admin.notifications.index', ['type' => $k]) }}" @class(['active' => request('type') === $k])>{{ $v }}</a>
    @endforeach
</div>
<div class="card">
    <ul class="list">
        @forelse ($items as $n)
            @php $unread = $n->reads->isEmpty(); @endphp
            <li style="{{ $unread ? '' : 'opacity:.65' }}">
                <x-badge :tone="['info' => 'info', 'success' => 'success', 'warning' => 'warning', 'danger' => 'danger'][$n->level] ?? 'neutral'" :label="ucfirst(explode('.', $n->type)[0])" />
                <div style="flex:1;min-width:0">
                    <a href="{{ route('admin.notifications.open', $n) }}" style="font-weight:{{ $unread ? 600 : 400 }};color:var(--ink)">{{ $n->title }}</a>
                    @if ($n->body)<div class="small muted">{{ $n->body }}</div>@endif
                </div>
                <span class="small muted nowrap">{{ $n->created_at->diffForHumans() }}</span>
            </li>
        @empty
            <li><x-empty title="You're all caught up" icon="bell">New alerts will appear here.</x-empty></li>
        @endforelse
    </ul>
    {{ $items->links() }}
</div>
@endsection
