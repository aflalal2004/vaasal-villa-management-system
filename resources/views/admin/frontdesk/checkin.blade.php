@extends('layouts.admin')
@section('title', 'Check in '.$b->reference)
@section('content')
<x-page-header :title="'Check in · '.$b->guest->fullName()" :crumbs="['Front desk' => route('admin.frontdesk.index'), $b->reference => route('admin.bookings.show', $b)]"
    :sub="fmt_date($b->arrival, 'd M').' → '.fmt_date($b->departure, 'd M Y').' · '.$b->nights().' night(s) · '.($b->operator?->company_name ?? $b->sourceLabel())" />

@if ($b->special_requests)<div class="alert alert-info"><strong>Special requests:</strong> {{ $b->special_requests }}</div>@endif

<form method="post" action="{{ route('admin.frontdesk.checkin.store', $b) }}" enctype="multipart/form-data" class="grid cols-main">
    @csrf
    <div class="stack">
        <div class="card">
            <div class="card-head"><h2>1 · Verify identity</h2></div>
            <div class="card-body form-grid">
                <x-select name="id_type" label="Document" :options="['passport' => 'Passport', 'nic' => 'National ID', 'driving_licence' => 'Driving licence']" :value="$b->guest->id_type ?? 'passport'" required col="f-4" />
                <x-input name="id_number" label="Document number" :required="! $b->guest->id_number" col="f-4" :placeholder="$b->guest->maskedId() ? 'On file: '.$b->guest->maskedId() : ''" autocomplete="off" />
                <x-input name="id_expiry" type="date" label="Expiry" :value="$b->guest->id_expiry" col="f-4" />
                <x-input name="nationality" label="Nationality" :value="$b->guest->nationality" col="f-4" />
                <x-input name="phone" label="Mobile / WhatsApp" :value="$b->guest->phone" col="f-4" />
                <div class="field f-4"><label for="id_document">Scan / photo of ID</label><input type="file" id="id_document" name="id_document" accept="image/*,application/pdf" capture="environment"><span class="help">Stored privately.</span></div>
                <x-input name="address" label="Home address" :value="$b->guest->address" col="f-12" />
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>2 · Villa & key cards</h2></div>
            @foreach ($b->activeVillas as $bv)
                @php $v = $bv->villa; @endphp
                <div class="card-body form-grid" style="border-bottom:1px solid var(--line-2)">
                    <div class="f-12 row between">
                        <div><strong style="font-family:var(--serif);font-size:18px">{{ $v->code }} · {{ $v->name }}</strong>
                            <div class="small muted">{{ $v->type->name }} · {{ $bv->adults }}A {{ $bv->children }}C · lock {{ $v->lock_ref ?? '—' }}</div></div>
                        <div class="row">
                            <x-badge :status="$v->hk_status" :label="'Housekeeping: '.\App\Models\Villa::HK_LABELS[$v->hk_status]" />
                            @if($v->occupancy_status === 'occupied')<x-badge status="occupied" label="Still occupied" />@endif
                            @if($v->maintenance_status !== 'ok')<x-badge :status="$v->maintenance_status" />@endif
                        </div>
                    </div>
                    @if (! empty($alternatives[$bv->id]))
                        <x-select :name="'villa_assignments['.$bv->id.']'" label="Assign a different villa (optional)" :options="$alternatives[$bv->id]" placeholder="Keep {{ $v->code }}" col="f-6" />
                    @endif
                    <x-input :name="'card_uids['.$bv->id.']'" label="Key card UID" col="f-6" autocomplete="off" help="Tap the blank card on the encoder reader (or type the UID). A new key cancels any earlier card for this villa." />
                    <div class="f-12 small muted">Free cards: @foreach ($freeCards as $c)<button type="button" class="chip" style="border:0;cursor:pointer" onclick="this.closest('.form-grid').querySelector('[name^=card_uids]').value='{{ $c->uid }}'">{{ $c->card_number }} · {{ $c->uid }}</button> @endforeach</div>
                    <details class="f-12"><summary class="small" style="cursor:pointer">Register accompanying guests ({{ $bv->guests->count() }} on file)</summary>
                        <div class="form-grid" style="margin-top:10px">
                            @for ($i = 0; $i < max(1, $bv->adults + $bv->children - 1 - $bv->guests->count()); $i++)
                                <x-input :name="'stay_guests['.$bv->id.']['.$i.'][first_name]'" label="First name" col="f-3" />
                                <x-input :name="'stay_guests['.$bv->id.']['.$i.'][last_name]'" label="Last name" col="f-3" />
                                <x-input :name="'stay_guests['.$bv->id.']['.$i.'][nationality]'" label="Nationality" col="f-3" />
                                <x-input :name="'stay_guests['.$bv->id.']['.$i.'][passport_no]'" label="Passport" col="f-3" />
                            @endfor
                        </div>
                    </details>
                </div>
            @endforeach
            @if ($b->activeVillas->contains(fn ($bv) => $bv->villa->hk_status !== 'ready'))
                <div class="card-body">
                    <div class="alert alert-warning" style="margin:0">A villa is not marked Ready by housekeeping. Assign a ready villa above, or (supervisors only) override:</div>
                    @perm('housekeeping.manage|bookings.override_rate')<label class="check" style="margin-top:8px"><input type="checkbox" name="allow_not_ready" value="1"> Check in anyway — I have confirmed the villa is ready</label>@endperm
                </div>
            @endif
        </div>
    </div>

    <div class="stack" style="align-content:start">
        <div class="card">
            <div class="card-head"><h2>3 · Payment</h2></div>
            <div class="card-body stack" style="gap:10px">
                <div class="totals">
                    <span class="muted">Booking total</span><span>{{ money($b->grand_total) }}</span>
                    <span class="muted">Paid to date</span><span>{{ money($paid) }}</span>
                    <span><strong>Balance</strong></span><span><strong>{{ money(max(0, $b->grand_total - $paid)) }}</strong></span>
                </div>
                @if ($b->operator)<p class="small muted" style="margin:0">Accommodation is billed to {{ $b->operator->company_name }} (city ledger). Take a security deposit for extras if required.</p>@endif
                <div class="form-grid">
                    <x-input name="deposit_amount" type="number" step="0.01" min="0" label="Deposit / payment now" col="f-6" />
                    <x-select name="deposit_method" label="Method" :options="['card' => 'Card', 'cash' => 'Cash', 'bank_transfer' => 'Bank transfer']" col="f-6" />
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body stack" style="gap:10px">
                <button class="btn btn-primary btn-lg btn-block" type="submit"><x-icon name="login" /> Complete check-in</button>
                <a class="btn btn-block" href="{{ route('admin.bookings.registration', $b) }}" target="_blank"><x-icon name="printer" /> Print registration card</a>
                <p class="small muted" style="margin:0">On completion the villa shows Occupied, the guest folio opens for restaurant and service charges, and the card is encoded through the Lock Bridge.</p>
            </div>
        </div>
    </div>
</form>
@endsection
