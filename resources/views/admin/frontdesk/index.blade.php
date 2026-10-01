@extends('layouts.admin')
@section('title', 'Front desk')
@section('content')
<x-page-header title="Front desk" :eyebrow="now()->format('l, d F Y')" sub="Arrivals, in-house guests and departures. Check-in requires a Ready villa; checkout revokes cards and sends the villa to housekeeping.">
    @perm('bookings.manage')<a class="btn btn-primary" href="{{ route('admin.frontdesk.walk-in') }}"><x-icon name="door" /> Walk-in</a>@endperm
    @perm('frontdesk.night_audit')
        <form method="post" action="{{ route('admin.frontdesk.night-audit') }}" data-confirm="Run night audit now? Tonight's room charges post to every in-house folio, no-shows are flagged and expired holds/cards are released.">
            @csrf<button class="btn" type="submit"><x-icon name="moon" /> Run night audit</button>
        </form>
    @endperm
</x-page-header>
@if ($lastAudit)
    <p class="small muted" style="margin-top:-10px">Last night audit: {{ $lastAudit['run_at'] ?? '' }} · {{ $lastAudit['room_nights'] ?? 0 }} room nights posted.</p>
@endif

<div class="stats">
    <x-stat label="Arrivals pending" :value="$arrivals->count()" />
    <x-stat label="In-house" :value="$inHouse->count()" />
    <x-stat label="Departures pending" :value="$departures->count()" />
    <x-stat label="Villas ready" :value="$villas->where('hk_status', 'ready')->where('occupancy_status', 'vacant')->count().' / '.$villas->count()" hint="Vacant & ready to sell" />
</div>

@include('admin.partials.quick-actions')

<div class="tabs">
    <a href="?tab=arrivals" @class(['active' => $tab === 'arrivals'])>Arrivals ({{ $arrivals->count() }})</a>
    <a href="?tab=inhouse" @class(['active' => $tab === 'inhouse'])>In-house ({{ $inHouse->count() }})</a>
    <a href="?tab=departures" @class(['active' => $tab === 'departures'])>Departures ({{ $departures->count() }})</a>
    <a href="?tab=upcoming" @class(['active' => $tab === 'upcoming'])>Next 3 days ({{ $upcoming->count() }})</a>
    <a href="?tab=villas" @class(['active' => $tab === 'villas'])>Villa status</a>
</div>

@if ($tab === 'villas')
    <div class="board">
        @foreach ($villas as $v)
            <div class="tile hk-{{ $v->hk_status }}">
                <div class="row between"><span class="code">{{ $v->code }}</span><x-badge :status="$v->occupancy_status" /></div>
                <div class="small muted">{{ $v->name }}</div>
                <div class="row"><x-badge :status="$v->hk_status" :label="\App\Models\Villa::HK_LABELS[$v->hk_status]" />
                    @if ($v->maintenance_status !== 'ok')<x-badge :status="$v->maintenance_status" />@endif</div>
            </div>
        @endforeach
    </div>
@else
    @php
        $list = ['arrivals' => $arrivals, 'inhouse' => $inHouse, 'departures' => $departures, 'upcoming' => $upcoming][$tab] ?? $arrivals;
    @endphp
    <div class="card">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Guest</th><th>Booking</th><th>Villa</th><th>Stay</th><th>Status</th>@if($tab === 'inhouse')<th class="num">Folio balance</th>@endif<th></th></tr></thead>
            <tbody>
            @forelse ($list as $b)
                <tr>
                    <td><strong>{{ $b->guest->fullName() }}</strong>@if($b->arrival_time)<div class="small muted">ETA {{ $b->arrival_time }}</div>@endif
                        @if($b->special_requests)<div class="small" style="color:var(--brass)"><x-icon name="star-fill" /> {{ \Illuminate\Support\Str::limit($b->special_requests, 60) }}</div>@endif</td>
                    <td><a class="mono" href="{{ route('admin.bookings.show', $b) }}">{{ $b->reference }}</a><div class="small muted">{{ $b->operator?->company_name ?? $b->channel?->name }}</div></td>
                    <td>@foreach ($b->activeVillas as $bv)<div><span class="chip">{{ $bv->villa->code }}</span> <x-badge :status="$bv->villa->hk_status" :label="\App\Models\Villa::HK_LABELS[$bv->villa->hk_status]" /></div>@endforeach</td>
                    <td class="nowrap small">{{ fmt_date($b->arrival, 'd M') }} → {{ fmt_date($b->departure, 'd M') }}<br>{{ $b->nights() }} night(s)</td>
                    <td><x-badge :status="$b->status" :label="$b->statusLabel()" />@if($b->arrival->lt(now()->startOfDay()) && in_array($b->status, ['confirmed', 'tentative']))<div class="small" style="color:var(--crit)">Overdue arrival</div>@endif</td>
                    @if ($tab === 'inhouse')<td class="num">{{ money($b->folios->sum(fn ($f) => $f->balance())) }}</td>@endif
                    <td class="actions">
                        @if (in_array($b->status, ['confirmed', 'tentative']) && ! $b->arrival->isFuture())
                            @perm('frontdesk.checkin')<a class="btn btn-sm btn-primary" href="{{ route('admin.frontdesk.checkin', $b) }}">Check in</a>@endperm
                        @elseif ($b->status === 'checked_in')
                            @perm('frontdesk.checkout')<a class="btn btn-sm {{ $b->departure->lte(now()) ? 'btn-primary' : '' }}" href="{{ route('admin.frontdesk.checkout', $b) }}">Check out</a>@endperm
                        @else
                            <a class="btn btn-sm" href="{{ route('admin.bookings.show', $b) }}">View</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty title="Nothing to process" icon="check" /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
@endif
@endsection
