@extends('layouts.admin')
@section('title', 'RFID simulator')
@section('content')
<x-page-header title="RFID simulator" eyebrow="Access control" sub="Test access decisions without hardware. Scans go through the same engine as the device API and are written to the access log (source: simulator)." />
@include('admin.access.partials.tabs')

<div class="alert alert-info"><x-icon name="alert" /> Development tool. Physical doors are not opened from here — real readers call <code>POST /api/v1/rfid/scan</code>. Turn it off in production with <code>RFID_SIMULATOR=false</code>.</div>

<div class="grid cols-main">
    <div class="stack">
        <form method="post" action="{{ route('admin.access.simulate') }}" class="card" id="sim-form">
            @csrf
            <div class="card-head"><h2><x-icon name="rfid" /> Scan a card</h2></div>
            <div class="card-body form-grid">
                <x-input name="uid" label="Card / badge UID" required col="f-12" class="mono uid-input" placeholder="04AABBCC1122" autocomplete="off" help="Type or paste the UID — letters and digits, separators are ignored." />
                <x-select name="villa_id" label="Villa door" :options="$villas->mapWithKeys(fn ($v) => [$v->id => $v->code.' · '.$v->name])" placeholder="— none —" col="f-4" />
                <div class="field f-4"><label for="sim-zone">Zone</label><select id="sim-zone" name="zone"><option value="">— none —</option>@foreach ($zones as $z)<option value="{{ $z }}" @selected(old('zone') === $z)>{{ $z }}</option>@endforeach</select></div>
                <x-select name="device_id" label="As device (optional)" :options="$devices->mapWithKeys(fn ($d) => [$d->id => $d->name])" placeholder="— none —" col="f-4" help="A time-clock device also punches attendance." />
            </div>
            <div class="card-foot"><button class="btn btn-primary btn-lg" type="submit"><x-icon name="rfid" /> Scan</button></div>
        </form>

        @if ($result)
            <div @class(['card', 'sim-result', 'granted' => $result['granted'], 'denied' => ! $result['granted']]) role="status" aria-live="polite">
                <div class="sim-icon"><x-icon :name="$result['granted'] ? 'check-circle' : 'x-circle'" /></div>
                <div>
                    <div class="sim-title">{{ $result['granted'] ? 'Access Granted' : 'Access Denied' }}</div>
                    <dl class="sim-dl">
                        @if ($result['holder'])<dt>{{ $result['holder_type'] === 'guest' ? 'Guest' : 'Staff' }}</dt><dd>{{ $result['holder'] }}</dd>@endif
                        <dt>{{ $result['granted'] ? 'Access' : 'Reason' }}</dt><dd>{{ $result['reason'] }}</dd>
                        <dt>Door / zone</dt><dd>{{ $result['target'] }}</dd>
                        <dt>UID</dt><dd class="mono">{{ $result['uid'] }}</dd>
                        @if ($result['attendance'])<dt>Attendance</dt><dd>{{ isset($result['attendance']['error']) ? $result['attendance']['error'] : str_replace('_', ' ', $result['attendance']['action']).' at '.$result['attendance']['time'] }}</dd>@endif
                        <dt>Time</dt><dd>{{ $result['time'] }}</dd>
                    </dl>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-head"><h2>Recent scans</h2><a class="small" href="{{ route('admin.keycards.logs') }}">All access logs</a></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Time</th><th>UID</th><th>Holder</th><th>Door / zone</th><th>Result</th></tr></thead>
                <tbody>@forelse ($recent as $l)
                    <tr><td class="small nowrap">{{ $l->occurred_at->format('d M H:i:s') }}</td><td class="mono small">{{ $l->card_uid }}</td><td>{{ $l->details['holder'] ?? '—' }}</td>
                        <td>{{ $l->details['target'] ?? ($l->villa?->code ?? $l->zone ?? '—') }}</td>
                        <td>@if ($l->granted === null)<x-badge :label="label($l->event)" tone="neutral" />@else<x-badge :tone="$l->granted ? 'success' : 'danger'" :label="$l->granted ? 'Granted' : 'Denied'" /> <span class="small muted">{{ $l->reason }}</span>@endif</td></tr>
                @empty
                    <tr><td colspan="5"><x-empty title="No scans yet" icon="rfid" /></td></tr>
                @endforelse</tbody>
            </table></div>
        </div>
    </div>

    <div class="card" style="align-self:start">
        <div class="card-head"><h2>Sample UIDs</h2></div>
        <div class="card-body stack" style="gap:12px">
            @foreach ($samples as $group => $items)
                <div><div class="small muted" style="margin-bottom:6px">{{ $group }}</div>
                    <div class="chips">@forelse ($items as [$uid, $label])<button type="button" class="chip chip-btn" data-uid="{{ $uid }}" title="{{ $label }}"><span class="mono">{{ $uid }}</span> · {{ $label }}</button>@empty<span class="small muted">None</span>@endforelse</div></div>
            @endforeach
            <p class="small muted" style="margin:0">Guest cards open their own villa plus shared areas ({{ implode(', ', config('vaasal.locks.guest_zones')) }}). Staff badges open staff zones by department; master cards open everything.</p>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.querySelectorAll('[data-uid]').forEach(b => b.addEventListener('click', () => { const i = document.querySelector('#sim-form [name=uid]'); i.value = b.dataset.uid; i.focus(); }));
</script>
@endpush
@endsection
