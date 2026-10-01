@extends('layouts.operator')
@section('title', 'Dashboard')
@section('content')
<x-page-header :title="$op->company_name" eyebrow="Partner dashboard" :sub="'Account '.$op->code.' · terms '.$op->payment_terms_days.' days'">
    @if ($op->status === 'active')<a class="btn btn-primary" href="{{ route('operator.bookings.create') }}"><x-icon name="plus" /> New group booking</a>@endif
    <a class="btn" href="{{ route('operator.availability') }}"><x-icon name="calendar" /> Check availability</a>
</x-page-header>
@if ($op->status === 'pending')<div class="alert alert-warning">Your application is being reviewed. We'll email {{ $op->email }} when bookings are enabled.</div>@endif
<div class="stats">
    <x-stat label="Upcoming arrivals" :value="$upcoming->count()" />
    <x-stat label="Guests in-house" :value="$inHouse->count()" />
    <x-stat label="Villa-nights this year" :value="$nightsYtd" />
    <x-stat label="Outstanding balance" :value="money($statement['outstanding'])" :hint="'Credit limit '.money($op->credit_limit)" />
</div>
<div class="grid cols-main">
    <div class="card">
        <div class="card-head"><h2>Upcoming arrivals</h2><a class="small" href="{{ route('operator.bookings') }}">All bookings</a></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Ref</th><th>Group / lead</th><th>Villas</th><th>Arrival</th><th class="num">Nights</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($upcoming as $b)
                <tr><td><a class="mono" href="{{ route('operator.bookings.show', $b) }}">{{ $b->reference }}</a></td><td>{{ $b->group_name ?? $b->guest->fullName() }}</td>
                    <td>{{ $b->activeVillas->pluck('villa.code')->implode(', ') }}</td><td>{{ fmt_date($b->arrival) }}</td><td class="num">{{ $b->nights() }}</td><td><x-badge :status="$b->status" :label="$b->statusLabel()" /></td></tr>
            @empty
                <tr><td colspan="6" class="muted">No upcoming arrivals.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    <div class="stack" style="align-content:start">
        <div class="card">
            <div class="card-head"><h2>Your contract</h2></div>
            <div class="card-body">
                @if ($contract)
                    <p class="small"><strong>{{ $contract->name }}</strong><br>{{ fmt_date($contract->valid_from) }} – {{ fmt_date($contract->valid_to) }}<br>
                        Commission {{ (float) $contract->commission_pct }}% · deposit {{ (float) $contract->deposit_pct }}% · rooming list by {{ $contract->rooming_cutoff_days }} days before arrival</p>
                    @foreach ($contract->rates as $r)<div class="row between small"><span>{{ $r->villaType->name }}</span><strong>{{ money($r->net_rate) }} net</strong></div>@endforeach
                @else
                    <p class="small muted">No contract in force — public rates apply. Contact reservations for a contract.</p>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h2>Open invoices</h2></div>
            <ul class="list">
                @forelse ($invoices as $i)
                    <li><a class="mono small" href="{{ route('operator.invoices.show', $i) }}" target="_blank">{{ $i->number }}</a><span class="spacer"></span>
                        <span class="small" style="color:{{ $i->due_date?->isPast() ? 'var(--crit)' : 'inherit' }}">due {{ fmt_date($i->due_date, 'd M') }}</span> <strong class="small">{{ money($i->balance) }}</strong></li>
                @empty
                    <li class="muted small">Nothing outstanding. Thank you!</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
