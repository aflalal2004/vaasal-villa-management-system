@extends('layouts.admin')
@section('title', 'Key cards')
@section('content')
<x-page-header title="RFID key cards" sub="Card stock, who holds each card, and what it opens. Encoding runs through the Lock Bridge on the property network.">
    <a class="btn" href="{{ route('admin.keycards.logs') }}"><x-icon name="history" /> Access log</a>
    <a class="btn" href="{{ route('admin.keycards.bridge') }}"><x-icon name="link" /> Lock Bridge</a>
    @perm('keycards.manage')
        <button class="btn" type="button" data-modal-open="#staff-modal"><x-icon name="user" /> Staff / master card</button>
        <button class="btn btn-primary" type="button" data-modal-open="#register-modal"><x-icon name="plus" /> Register card</button>
    @endperm
</x-page-header>
@include('admin.access.partials.tabs')

<div class="stats">
    @foreach (['available' => 'Available', 'active' => 'Active', 'lost' => 'Lost', 'blocked' => 'Blocked', 'damaged' => 'Damaged'] as $k => $v)
        <x-stat :label="$v" :value="$counts[$k] ?? 0" :href="route('admin.keycards.index', ['status' => $k])" />
    @endforeach
</div>

<form class="filters" method="get">
    <div class="field" style="flex:2 1 200px"><label for="q">UID or card no.</label><input id="q" type="search" name="q" value="{{ request('q') }}"></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status" data-autosubmit><option value="">Any</option>@foreach (['available', 'active', 'lost', 'blocked', 'damaged', 'retired'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="field"><label for="type">Type</label><select id="type" name="type" data-autosubmit><option value="">Any</option>@foreach (['guest', 'staff', 'master', 'maintenance'] as $s)<option value="{{ $s }}" @selected(request('type') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <button class="btn" type="submit">Filter</button>
</form>

<div class="grid cols-main">
    <div class="card">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Card</th><th>Type</th><th>Status</th><th>Holder</th><th>Access</th><th>Valid until</th></tr></thead>
            <tbody>
            @foreach ($cards as $c)
                @php $a = $c->activeAssignment; @endphp
                <tr>
                    <td><a class="mono" href="{{ route('admin.keycards.show', $c) }}">{{ $c->uid }}</a><div class="small muted">{{ $c->card_number }}</div></td>
                    <td>{{ ucfirst($c->type) }}</td>
                    <td><x-badge :status="$c->status" /></td>
                    <td>{{ $a?->holderName() ?? '—' }}</td>
                    <td class="small">{{ $a ? ($a->villa?->code ?? ucfirst($a->access_level).($a->zone ? ' · '.$a->zone : '')) : '—' }}</td>
                    <td class="small nowrap">{{ $a ? fmt_dt($a->valid_to) : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $cards->links() }}
    </div>
    <div class="stack" style="align-content:start">
        <div class="card">
            <div class="card-head"><h2>Recent encoder jobs</h2></div>
            <ul class="list">
                @forelse ($jobs as $j)
                    <li><x-badge :status="$j->status" /> <span class="small">{{ label($j->action) }} · <span class="mono">{{ $j->assignment?->card?->uid }}</span><br><span class="muted">{{ $j->created_at->diffForHumans() }}{{ $j->error ? ' · '.$j->error : '' }}</span></span></li>
                @empty
                    <li class="muted small">No jobs yet.</li>
                @endforelse
            </ul>
        </div>
        @perm('keycards.manage')
        <div class="card">
            <div class="card-head"><h2>Test a door</h2></div>
            <form method="post" action="{{ route('admin.keycards.simulate') }}" class="card-body form-grid" id="issue">
                @csrf
                <x-input name="uid" label="Card UID" required col="f-12" />
                <x-select name="villa_id" label="Door" :options="$villas->mapWithKeys(fn ($v) => [$v->id => $v->code.' ('.$v->lock_ref.')'])" col="f-12" />
                <div class="f-12"><button class="btn btn-sm" type="submit">Present card</button> <span class="small muted">Simulates a lock event; logged as source “simulator”.</span></div>
            </form>
        </div>
        @endperm
    </div>
</div>

<x-modal id="register-modal" title="Register a card">
    <form method="post" action="{{ route('admin.keycards.store') }}">
        @csrf
        <div class="modal-body form-grid">
            <x-input name="uid" label="Card UID" required col="f-12" help="Read on the encoder, or type the hexadecimal UID." autocomplete="off" />
            <x-input name="card_number" label="Printed card number" col="f-6" />
            <x-select name="type" label="Type" :options="['guest' => 'Guest', 'staff' => 'Staff', 'master' => 'Master', 'maintenance' => 'Maintenance']" col="f-6" />
            <x-input name="notes" label="Notes" col="f-12" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Register</button></div>
    </form>
</x-modal>

<x-modal id="staff-modal" title="Issue a staff / master card">
    <form method="post" action="{{ route('admin.keycards.staff-issue') }}">
        @csrf
        <div class="modal-body form-grid">
            <x-select name="employee_id" label="Employee" :options="$employees" required col="f-12" placeholder="Choose…" />
            <x-input name="uid" label="Card UID" required col="f-6" autocomplete="off" />
            <x-select name="access_level" label="Access level" :options="['housekeeping' => 'Housekeeping (selected / all villas)', 'maintenance' => 'Maintenance (all doors)', 'zone' => 'Zone', 'master' => 'Master (all doors)']" col="f-6" />
            <x-select name="zone" label="Zone (for zone cards)" :options="$zones" placeholder="—" col="f-6" />
            <x-input name="valid_to" type="date" label="Valid until" :value="now()->addMonths(3)" required col="f-6" />
            <div class="field f-12"><span class="label">Villas (housekeeping cards; leave empty for all)</span>
                <div class="row">@foreach ($villas as $v)<label class="check"><input type="checkbox" name="villa_ids[]" value="{{ $v->id }}"> {{ $v->code }}</label>@endforeach</div></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit"><x-icon name="key" /> Encode</button></div>
    </form>
</x-modal>
@endsection
