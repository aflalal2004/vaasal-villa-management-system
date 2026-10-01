@extends('layouts.admin')
@section('title', 'Access log')
@section('content')
<x-page-header title="Access log" :crumbs="['Key cards' => route('admin.keycards.index')]" eyebrow="Access control" sub="Every RFID decision (granted or denied, with the reason), card issue/revoke events and lock audit trails." />
@include('admin.access.partials.tabs')
<form class="filters" method="get">
    <div class="field"><label for="uid">Card UID</label><input id="uid" name="uid" value="{{ request('uid') }}"></div>
    <div class="field"><label for="villa">Door</label><select id="villa" name="villa" data-autosubmit><option value="">All</option>@foreach ($villas as $id => $c)<option value="{{ $id }}" @selected(request('villa') == $id)>{{ $c }}</option>@endforeach</select></div>
    <div class="field"><label for="event">Event</label><select id="event" name="event" data-autosubmit><option value="">All</option>@foreach (['open', 'denied', 'expired_card', 'blocked_card', 'punch', 'issued', 'revoked', 'low_battery', 'door_ajar'] as $e)<option value="{{ $e }}" @selected(request('event') === $e)>{{ label($e) }}</option>@endforeach</select></div>
    <div class="field"><label for="date">Date</label><input id="date" type="date" name="date" value="{{ request('date') }}"></div>
    <button class="btn" type="submit">Filter</button>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Time</th><th>Event</th><th>Door / zone</th><th>Card</th><th>Holder</th><th>Device / source</th><th>Reason</th></tr></thead>
        <tbody>
        @forelse ($logs as $l)
            <tr>
                <td class="nowrap small">{{ fmt_dt($l->occurred_at) }}</td>
                <td><x-badge :tone="in_array($l->event, ['open', 'issued']) ? 'success' : (in_array($l->event, ['denied', 'blocked_card', 'expired_card']) ? 'danger' : 'neutral')" :label="label($l->event)" /></td>
                <td>{{ collect([$l->villa?->code ?? $l->lock_ref, $l->zone])->filter()->implode(' · ') ?: '—' }}</td>
                <td>@if($l->card)<a class="mono" href="{{ route('admin.keycards.show', $l->card) }}">{{ $l->card_uid }}</a>@else<span class="mono">{{ $l->card_uid }}</span>@endif</td>
                <td>{{ $l->details['holder'] ?? $l->employee?->fullName() ?? '—' }}@if(isset($l->details['holder_type']))<div class="small muted">{{ ucfirst($l->details['holder_type']) }}</div>@endif</td>
                <td class="small">{{ $l->device?->name ?? '—' }}<div class="muted">{{ $l->source }}</div></td>
                <td class="small">@if($l->granted !== null)<x-badge :tone="$l->granted ? 'success' : 'danger'" :label="$l->granted ? 'Granted' : 'Denied'" /> @endif<span class="muted">{{ $l->reason ?? $l->details['reason'] ?? ($l->details['message'] ?? '') }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty title="No events" icon="history" /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $logs->links() }}
</div>
@endsection
