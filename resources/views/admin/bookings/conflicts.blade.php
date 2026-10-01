@extends('layouts.admin')
@section('title', 'OTA conflict queue')
@section('content')
<x-page-header title="OTA conflict queue" sub="Channel reservations that arrived for dates with no free villa (or with an unmapped room). Nothing is ever dropped silently — resolve each one here." />
<div class="tabs">
    @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'rejected' => 'Rejected / relocated'] as $k => $v)
        <a href="{{ route('admin.conflicts.index', ['status' => $k]) }}" @class(['active' => $status === $k])>{{ $v }}</a>
    @endforeach
</div>
<div class="stack">
@forelse ($conflicts as $c)
    <div class="card" style="border-left:4px solid {{ $c->status === 'open' ? 'var(--crit)' : 'var(--line)' }}">
        <div class="card-head">
            <h2>{{ $c->channel->name }} · <span class="mono">{{ $c->external_ref }}</span></h2>
            <x-badge :status="$c->status === 'open' ? 'failed' : ($c->status === 'resolved' ? 'resolved' : 'cancelled')" :label="ucfirst($c->status)" />
        </div>
        <div class="card-body grid cols-2">
            <dl class="dl">
                <dt>Guest</dt><dd>{{ $c->guest_name }} {{ $c->guest_email ? '· '.$c->guest_email : '' }}</dd>
                <dt>Stay</dt><dd>{{ fmt_date($c->arrival) }} → {{ fmt_date($c->departure) }} ({{ $c->arrival->diffInDays($c->departure) }} nights)</dd>
                <dt>Requested</dt><dd>{{ $c->villaType?->name ?? 'Unmapped room code '.($c->payload['room_code'] ?? '?') }}</dd>
                <dt>OTA amount</dt><dd>{{ money($c->amount) }}</dd>
                <dt>Reason</dt><dd style="color:var(--crit)">{{ $c->reason }}</dd>
                <dt>Received</dt><dd>{{ fmt_dt($c->created_at) }}</dd>
                @if ($c->status !== 'open')<dt>Resolution</dt><dd>{{ $c->resolution_notes }} — {{ $c->resolver?->name }} {{ fmt_dt($c->resolved_at) }}
                    @if($c->booking)<br><a href="{{ route('admin.bookings.show', $c->booking) }}">{{ $c->booking->reference }}</a>@endif</dd>@endif
            </dl>
            @if ($c->status === 'open')
                <div class="stack">
                    <form method="post" action="{{ route('admin.conflicts.assign', $c) }}" class="form-grid">
                        @csrf
                        @if (empty($options[$c->id]))
                            <div class="f-12 alert alert-warning" style="margin:0">No villa of any type is free for these dates. Free a villa (move another booking) or relocate the guest to a partner property and reject below.</div>
                        @else
                            <x-select name="villa_id" label="Assign an available villa (upgrade allowed)" :options="$options[$c->id]" required col="f-12" :id="'villa_'.$c->id" />
                            <x-input name="notes" label="Notes" col="f-12" :id="'notes_'.$c->id" placeholder="e.g. Upgraded to Pool Villa at no charge" />
                            <div class="f-12"><button class="btn btn-primary" type="submit"><x-icon name="check" /> Create booking in this villa</button></div>
                        @endif
                    </form>
                    <form method="post" action="{{ route('admin.conflicts.reject', $c) }}" class="row" data-confirm="Close this conflict without creating a booking? Make sure the guest has been relocated or the OTA reservation cancelled.">
                        @csrf
                        <input type="text" name="notes" required placeholder="Relocation / rejection notes" aria-label="Rejection notes" style="flex:1">
                        <button class="btn" type="submit">Reject / relocated</button>
                    </form>
                    <a class="small" href="{{ route('admin.calendar', ['from' => $c->arrival->copy()->subDays(3)->toDateString()]) }}">Open calendar for these dates →</a>
                </div>
            @endif
        </div>
    </div>
@empty
    <div class="card"><x-empty title="No {{ $status }} conflicts" icon="check">Channel reservations are flowing in cleanly.</x-empty></div>
@endforelse
</div>
{{ $conflicts->links() }}
@endsection
