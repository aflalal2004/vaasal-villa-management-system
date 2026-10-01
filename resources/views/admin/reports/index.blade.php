@extends('layouts.admin')
@section('title', 'Reports')
@section('content')
<x-page-header title="Reports & analytics" sub="Every report can be filtered by date, printed or exported to CSV. You see the reports your role allows." />
@php
    $groups = [
        'Rooms & bookings' => ['bookings', 'occupancy', 'guests', 'channels', 'operators'],
        'Finance' => ['revenue', 'payments', 'outstanding'],
        'Restaurant & stock' => ['pos_sales', 'pos_items', 'inventory'],
        'Operations & staff' => ['housekeeping', 'maintenance', 'attendance', 'keycards'],
    ];
    $icons = ['bookings' => 'booking', 'occupancy' => 'bed', 'guests' => 'users', 'channels' => 'link', 'operators' => 'briefcase', 'revenue' => 'chart', 'payments' => 'card',
        'outstanding' => 'alert', 'pos_sales' => 'utensils', 'pos_items' => 'coffee', 'inventory' => 'box', 'housekeeping' => 'broom', 'maintenance' => 'wrench', 'attendance' => 'clock', 'keycards' => 'key'];
@endphp
<div class="stack">
    @foreach ($groups as $g => $types)
        @php $list = collect($types)->filter(fn ($t) => $reports->has($t)); @endphp
        @if ($list->isNotEmpty())
            <div>
                <h2 style="margin-bottom:10px">{{ $g }}</h2>
                <div class="grid cols-3">
                    @foreach ($list as $t)
                        <a class="card" href="{{ route('admin.reports.show', $t) }}" style="color:inherit;text-decoration:none;padding:16px;display:flex;gap:12px;align-items:center">
                            <span class="brand-mark" style="background:var(--accent-soft);color:var(--accent);width:40px;height:40px"><x-icon :name="$icons[$t]" /></span>
                            <span><strong>{{ $reports[$t][0] }}</strong><br><span class="small muted">Open report →</span></span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>
@endsection
