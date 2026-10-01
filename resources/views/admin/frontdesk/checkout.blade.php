@extends('layouts.admin')
@section('title', 'Check out '.$b->reference)
@section('content')
<x-page-header :title="'Check out · '.$b->guest->fullName()" :crumbs="['Front desk' => route('admin.frontdesk.index'), $b->reference => route('admin.bookings.show', $b)]"
    :sub="'Villa '.$b->activeVillas->pluck('villa.code')->implode(', ').' · '.fmt_date($b->arrival, 'd M').' → '.fmt_date($b->departure, 'd M Y')" />

@if ($openChecks->isNotEmpty())
    <div class="alert alert-danger"><strong>{{ $openChecks->count() }} restaurant check(s) still open for this stay.</strong>
        <ul style="margin:6px 0">@foreach ($openChecks as $o)<li>{{ $o->outlet->name }} {{ $o->table ? '· table '.$o->table->name : '' }} · {{ $o->order_no }} · {{ money($o->total) }} · {{ $o->statusLabel() }}</li>@endforeach</ul>
        The restaurant must pay them or charge them to the villa (they then appear on the folio below).
        <form method="post" action="{{ route('admin.frontdesk.restaurant-settlement', $b) }}" style="margin-top:8px">@csrf<button class="btn btn-sm" type="submit"><x-icon name="bell-ring" /> Request restaurant settlement</button></form></div>
@endif
@if ($unposted > 0)
    <div class="alert alert-info">{{ $unposted }} outstanding room night(s) were just posted to the folio so the balance below is final.</div>
@endif

<form method="post" action="{{ route('admin.frontdesk.checkout.store', $b) }}" id="checkout-form">@csrf</form>

<div class="grid cols-main">
    <div class="stack">
        @include('admin.partials.folios')
    </div>
    <div class="stack" style="align-content:start">
        <div class="card" style="position:sticky;top:76px">
            <div class="card-head"><h2>Settle & check out</h2></div>
            <div class="card-body stack" style="gap:12px">
                @foreach ($b->folios->where('status', 'open') as $f)
                    @php $s = $summaries[$f->id]; @endphp
                    <div>
                        <div class="row between"><strong>{{ $f->folio_no }}</strong><span class="small muted">{{ $f->payer_type === 'operator' ? 'City ledger' : 'Guest' }}</span></div>
                        <div class="totals" style="margin-top:6px">
                            @foreach ($s['by_department'] as $dept => $amt)<span class="muted">{{ config('vaasal.departments.'.$dept, $dept) }}</span><span>{{ money($amt) }}</span>@endforeach
                            <span class="muted">Paid</span><span>− {{ money($s['paid']) }}</span>
                            <span class="grand">Balance</span><span class="grand">{{ money($s['balance']) }}</span>
                        </div>
                        @if ($f->payer_type === 'operator')
                            <p class="small muted" style="margin:6px 0 0">Remaining balance transfers to {{ $f->payerName() }} on an operator invoice (payment terms {{ $b->operator?->payment_terms_days }} days).</p>
                        @else
                            <div class="form-grid" style="margin-top:8px">
                                <div class="field f-6"><label for="pm{{ $f->id }}">Settle with</label>
                                    <select id="pm{{ $f->id }}" name="payments[{{ $f->id }}][method]" form="checkout-form"><option value="card">Card</option><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option></select></div>
                                <div class="field f-6"><label for="pa{{ $f->id }}">Amount</label>
                                    <input id="pa{{ $f->id }}" type="number" step="0.01" min="0" name="payments[{{ $f->id }}][amount]" value="{{ max(0, $s['balance']) }}" form="checkout-form"></div>
                            </div>
                            
                        @endif
                    </div>
                    <hr style="margin:0">
                @endforeach

                <div class="small">
                    <strong>On checkout the system will:</strong>
                    <ul style="margin:6px 0 0;padding-left:18px">
                        <li>issue the final combined invoice and close the folio</li>
                        <li>revoke {{ $b->cardAssignments->where('status', 'active')->count() }} active key card(s)</li>
                        <li>set {{ $b->activeVillas->pluck('villa.code')->implode(', ') }} to Vacant + Dirty and create a housekeeping task</li>
                    </ul>
                </div>
                @if ($early)
                    <label class="check" style="color:var(--warn)"><input type="checkbox" name="confirm_early" value="1" form="checkout-form"> Confirm early departure (release nights after today)</label>
                @endif
                <button class="btn btn-primary btn-lg btn-block" type="submit" form="checkout-form" @disabled($openChecks->isNotEmpty())><x-icon name="logout" /> Complete checkout</button>
            </div>
        </div>
    </div>
</div>
@endsection
