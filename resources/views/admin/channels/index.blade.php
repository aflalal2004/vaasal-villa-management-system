@extends('layouts.admin')
@section('title', 'Channel manager')
@section('content')
<x-page-header title="Channel manager & OTAs" sub="Booking.com, Agoda, Airbnb, Expedia and others connect through ONE channel manager (SiteMinder, Channex …). Availability is pushed on every inventory change; reservations arrive by webhook.">
    <form method="post" action="{{ route('admin.channels.retry') }}">@csrf<button class="btn" type="submit"><x-icon name="refresh" /> Retry failed</button></form>
    <form method="post" action="{{ route('admin.channels.full-sync') }}" data-confirm="Push 365 days of availability to the channel manager now?">@csrf<button class="btn" type="submit"><x-icon name="upload" /> Full sync</button></form>
    <button class="btn btn-primary" type="button" data-modal-open="#sim-modal"><x-icon name="plane" /> Simulate OTA booking</button>
</x-page-header>

<div class="stats">
    <x-stat label="Channel manager" :value="$driver === 'null' ? 'Not connected' : ucfirst($driver)" :hint="$enabled ? 'Pushing live' : 'Set CHANNEL_DRIVER and keys in .env'" />
    <x-stat label="Webhook URL" :value="'/webhooks/channel/'.$driver" hint="Give this URL to the channel manager" />
    <x-stat label="Pushes pending / failed" :value="$logs->where('status', 'pending')->count().' / '.$logs->where('status', 'failed')->count()" />
</div>

<div class="grid cols-2 mb">
    <div class="card">
        <div class="card-head"><h2>Channels</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Channel</th><th>Type</th><th class="num">Bookings (month)</th><th>Commission %</th><th></th></tr></thead>
            <tbody>
            @foreach ($channels as $c)
                <tr>
                    <td>{{ $c->name }} <span class="mono small muted">{{ $c->code }}</span></td><td>{{ ucfirst($c->type) }}</td><td class="num">{{ $c->bookings_mtd }}</td>
                    <td><input form="ch-{{ $c->id }}" type="number" step="0.01" min="0" max="50" name="commission_pct" value="{{ $c->commission_pct }}" style="width:90px" aria-label="Commission"></td>
                    <td class="actions"><form id="ch-{{ $c->id }}" method="post" action="{{ route('admin.channels.update', $c) }}" class="row" style="justify-content:flex-end">@csrf @method('put')<label class="check small"><input type="checkbox" name="is_active" value="1" @checked($c->is_active)> Active</label> <button class="btn btn-sm" type="submit">Save</button></form></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Room & rate mappings</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Channel</th><th>Villa type</th><th>External room / rate</th><th></th></tr></thead>
            <tbody>
            @foreach ($mappings as $m)
                <tr><td>{{ $m->channel->name }}</td><td>{{ $m->villaType->name }}</td><td class="mono small">{{ $m->external_room_code }} / {{ $m->external_rate_code ?? '—' }}</td>
                    <td class="actions"><form method="post" action="{{ route('admin.channels.mappings.destroy', $m) }}" data-confirm="Remove this mapping? Reservations for this room code will go to the conflict queue.">@csrf @method('delete')<button class="btn btn-sm btn-ghost" type="submit" aria-label="Remove"><x-icon name="trash" /></button></form></td></tr>
            @endforeach
            </tbody>
        </table></div>
        <form method="post" action="{{ route('admin.channels.mappings.store') }}" class="card-body form-grid" style="border-top:1px solid var(--line-2)">
            @csrf
            <x-select name="channel_id" label="Channel" :options="$channels->where('type', 'ota')->pluck('name', 'id')" col="f-4" />
            <x-select name="villa_type_id" label="Villa type" :options="$types" col="f-4" />
            <x-select name="rate_plan_id" label="Rate plan" :options="$plans" placeholder="Any" col="f-4" />
            <x-input name="external_room_code" label="Room code" required col="f-6" />
            <x-input name="external_rate_code" label="Rate code" col="f-4" />
            <div class="field f-2" style="align-content:end"><button class="btn" type="submit">Add</button></div>
        </form>
    </div>
</div>

<div class="grid cols-main">
    <div class="card">
        <div class="card-head"><h2>Sync log (outbox & inbound)</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>When</th><th>Dir</th><th>Type</th><th>Dates</th><th>Status</th><th>Response</th></tr></thead>
            <tbody>
            @foreach ($logs as $l)
                <tr><td class="small nowrap">{{ fmt_dt($l->created_at) }}</td><td>{{ $l->direction === 'out' ? '→' : '←' }}</td><td>{{ label($l->type) }}</td>
                    <td class="small nowrap">{{ fmt_date($l->date_from, 'd M') }} – {{ fmt_date($l->date_to, 'd M') }}</td><td><x-badge :status="$l->status" /></td>
                    <td class="small muted">{{ \Illuminate\Support\Str::limit($l->response, 90) }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
    <div class="card" style="align-self:start">
        <div class="card-head"><h2>Webhook inbox</h2></div>
        <ul class="list">
            @forelse ($inbox as $w)
                <li><x-badge :status="$w->processed_at ? 'processed' : ($w->error ? 'failed' : 'pending')" :label="$w->processed_at ? 'Processed' : ($w->error ? 'Error' : 'Pending')" />
                    <span class="small">{{ $w->provider }} · {{ $w->event_type }}<br><span class="muted mono">{{ \Illuminate\Support\Str::limit($w->event_id, 28) }}</span>{{ $w->error ? ' · '.$w->error : '' }}</span></li>
            @empty
                <li class="muted small">No webhooks received yet.</li>
            @endforelse
        </ul>
    </div>
</div>

<x-modal id="sim-modal" title="Simulate an OTA reservation">
    <form method="post" action="{{ route('admin.channels.simulate') }}">
        @csrf
        <div class="modal-body form-grid">
            <p class="small muted f-12" style="margin:0">Runs the exact webhook ingest path. Try booking a sold-out type to see the conflict queue.</p>
            <x-select name="channel_code" label="OTA" :options="$otas" col="f-6" />
            <x-select name="action" label="Event" :options="['new' => 'New reservation', 'modified' => 'Modification', 'cancelled' => 'Cancellation']" col="f-6" />
            <x-select name="room_code" label="Room code" :options="$mappings->pluck('external_room_code', 'external_room_code')->unique()" col="f-6" />
            <x-input name="external_ref" label="OTA reference (for modify/cancel)" col="f-6" />
            <x-input name="arrival" type="date" label="Arrival" :value="now()->addDays(7)" required col="f-4" />
            <x-input name="departure" type="date" label="Departure" :value="now()->addDays(10)" required col="f-4" />
            <x-input name="adults" type="number" min="1" label="Adults" value="2" required col="f-4" />
            <x-input name="first_name" label="Guest first name" value="Test" required col="f-4" />
            <x-input name="last_name" label="Last name" value="Traveller" required col="f-4" />
            <x-input name="amount" type="number" step="0.01" label="OTA amount" col="f-4" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Send</button></div>
    </form>
</x-modal>
@endsection
