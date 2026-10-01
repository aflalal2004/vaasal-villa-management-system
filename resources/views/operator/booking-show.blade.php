@extends('layouts.operator')
@section('title', $b->reference)
@section('content')
<x-page-header :title="$b->reference.' · '.($b->group_name ?? $b->guest->fullName())" :crumbs="['Bookings' => route('operator.bookings')]"
    :sub="fmt_date($b->arrival, 'D d M Y').' → '.fmt_date($b->departure, 'D d M Y').' · '.$b->nights().' nights · '.$b->ratePlan?->name">
    <x-badge :status="$b->status" :label="$b->statusLabel()" />
    <a class="btn" href="{{ route('operator.bookings.voucher', $b) }}" target="_blank"><x-icon name="file" /> Voucher</a>
    @if (in_array($b->status, ['confirmed', 'tentative']))
        <form method="post" action="{{ route('operator.bookings.cancel', $b) }}" data-confirm="Cancel {{ $b->reference }}? Contract cancellation terms apply." data-reason data-danger>@csrf<button class="btn" type="submit">Cancel booking</button></form>
    @endif
</x-page-header>

<div class="grid cols-main">
    <div class="stack">
        <div class="card">
            <div class="card-head"><h2>Rooming list</h2>
                <span class="small {{ $locked ? '' : 'muted' }}" style="{{ $locked ? 'color:var(--crit)' : '' }}">{{ $locked ? 'Locked — contact reservations for changes' : 'Editable until '.fmt_date($b->arrival->copy()->subDays($cutoff)) }}</span></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Villa</th><th>Guest</th><th>Nationality</th><th>Passport</th><th>Flight</th></tr></thead>
                <tbody>
                @foreach ($b->villas->where('status', 'active') as $bv)
                    @forelse ($bv->guests as $g)
                        <tr><td>{{ $bv->villa->code }} · {{ $bv->villa->type->name }}</td><td>{{ $g->fullName() }} @if($g->is_child)<x-badge tone="neutral" label="Child" />@endif</td>
                            <td>{{ $g->nationality }}</td><td class="mono small">{{ $g->passport_no ? '••••'.substr($g->passport_no, -4) : '' }}</td><td class="small">{{ $g->flight_details }}</td></tr>
                    @empty
                        <tr><td>{{ $bv->villa->code }} · {{ $bv->villa->type->name }}</td><td colspan="4" class="muted small">No names yet</td></tr>
                    @endforelse
                @endforeach
                </tbody>
            </table></div>
            @if (! $locked && $b->isLive())
            <form method="post" action="{{ route('operator.bookings.rooming', $b) }}" class="card-body" style="border-top:1px solid var(--line-2)">
                @csrf
                <div id="rl-rows" data-template="rl-tpl" class="stack" style="gap:8px"></div>
                <template id="rl-tpl">
                    <div class="form-grid" data-repeat-row>
                        <div class="field f-3"><label>Villa</label><select name="guests[__i__][booking_villa_id]">@foreach ($b->villas->where('status', 'active') as $bv)<option value="{{ $bv->id }}">{{ $bv->villa->code }}</option>@endforeach</select></div>
                        <div class="field f-3"><label>First name</label><input name="guests[__i__][first_name]" maxlength="80"></div>
                        <div class="field f-3"><label>Last name</label><input name="guests[__i__][last_name]" maxlength="80"></div>
                        <div class="field f-3"><label>Nationality</label><input name="guests[__i__][nationality]" maxlength="80"></div>
                        <div class="field f-4"><label>Passport</label><input name="guests[__i__][passport_no]" maxlength="40"></div>
                        <div class="field f-6"><label>Flight / arrival</label><input name="guests[__i__][flight_details]" maxlength="120"></div>
                        <div class="field f-2" style="align-content:end"><button type="button" class="btn btn-sm btn-ghost" data-repeat-remove>Remove</button></div>
                    </div>
                </template>
                <div class="row" style="margin-top:10px">
                    <button type="button" class="btn btn-sm" data-repeat-add="rl-rows"><x-icon name="plus" /> Add guest</button>
                    <button class="btn btn-sm btn-primary" type="submit">Save rooming list</button>
                </div>
            </form>
            <form method="post" action="{{ route('operator.bookings.rooming.upload', $b) }}" enctype="multipart/form-data" class="card-body row" style="border-top:1px solid var(--line-2)">
                @csrf
                <label class="label" for="rl-file">Or upload CSV</label>
                <input id="rl-file" type="file" name="file" accept=".csv,text/csv" required>
                <button class="btn btn-sm" type="submit"><x-icon name="upload" /> Import</button>
                <a class="small" href="{{ route('operator.rooming.template') }}">Download template</a>
            </form>
            @endif
        </div>
    </div>
    <div class="stack" style="align-content:start">
        <div class="card">
            <div class="card-head"><h2>Charges</h2></div>
            <div class="card-body">
                <div class="totals">
                    <span class="muted">Accommodation</span><span>{{ money($b->room_total) }}</span>
                    <span class="muted">Service & tax</span><span>{{ money($b->service_total + $b->tax_total) }}</span>
                    <span class="grand">Total</span><span class="grand">{{ money($b->grand_total) }}</span>
                    <span class="muted">Deposit due</span><span>{{ money($b->deposit_due) }}</span>
                    <span class="muted">Paid</span><span>{{ money($b->payments->where('status', 'completed')->where('method', '!=', 'city_ledger')->sum('amount')) }}</span>
                    <span class="muted">Commission ({{ (float) $b->commission_pct }}%)</span><span>{{ money($b->commission_amount) }}</span>
                </div>
                <p class="small muted" style="margin-top:10px">Accommodation is invoiced to your company at checkout. Guest extras (restaurant, spa, laundry) are settled by the guests.</p>
            </div>
        </div>
        @if ($b->invoices->isNotEmpty())
        <div class="card"><div class="card-head"><h2>Documents</h2></div>
            <ul class="list">@foreach ($b->invoices as $i)<li><a class="mono small" href="{{ route('operator.invoices.show', $i) }}" target="_blank">{{ $i->number }}</a> <span class="small muted">{{ $i->typeLabel() }}</span><span class="spacer"></span>{{ money($i->grand_total) }}</li>@endforeach</ul>
        </div>
        @endif
    </div>
</div>
@endsection
