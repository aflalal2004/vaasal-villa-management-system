@extends('layouts.pos')
@section('title', 'Terminal')
@section('content')
<div class="pos-terminal" id="pos"
     data-outlet="{{ $outlet?->id }}" data-order="{{ $orderId }}"
     data-api="{{ url('pos/api') }}" data-can-void="{{ auth()->user()->hasPermission('pos.void') ? 1 : 0 }}"
     data-can-discount="{{ auth()->user()->hasPermission('pos.discount') ? 1 : 0 }}" data-can-pay="{{ auth()->user()->hasPermission('pos.bill') ? 1 : 0 }}">
    <section style="display:grid;grid-template-rows:auto minmax(0,1fr);gap:10px;min-height:0">
        <div class="row">
            <select id="outlet" aria-label="Outlet" style="width:auto;min-height:40px" onchange="location.href='?outlet='+this.value">
                @foreach ($outlets as $o)<option value="{{ $o->id }}" @selected($o->id === $outlet?->id)>{{ $o->name }}</option>@endforeach
            </select>
            <button class="btn" type="button" id="btn-tables"><x-icon name="table" /> Tables</button>
            <button class="btn" type="button" id="btn-takeaway"><x-icon name="box" /> Takeaway</button>
            <button class="btn" type="button" id="btn-room"><x-icon name="villa" /> Room service</button>
            <span class="spacer"></span>
            <input type="search" id="menu-search" placeholder="Search menu…" aria-label="Search menu" style="max-width:220px" hidden>
        </div>
        <div style="overflow:auto;min-height:0" id="left-pane">
            <div id="tables-view">
                <div id="tables-grid" class="tables-grid"></div>
                <h3 class="small muted" style="margin:16px 0 8px;text-transform:uppercase;letter-spacing:.07em">Open takeaway & room-service checks</h3>
                <div id="other-orders" class="row"></div>
            </div>
            <div id="menu-view" hidden>
                <div class="menu-cats" id="menu-cats" role="tablist"></div>
                <div class="menu-grid" id="menu-grid"></div>
            </div>
        </div>
    </section>

    <aside class="ticket" id="ticket" aria-live="polite">
        <div class="ticket-head">
            <div class="row between"><strong id="t-title">No check selected</strong><span id="t-status"></span></div>
            <div class="small muted" id="t-sub">Choose a table, takeaway or room service to start.</div>
        </div>
        <div class="ticket-lines" id="t-lines"></div>
        <div class="ticket-foot">
            <div class="totals" id="t-totals"></div>
            <div class="pos-actions" id="t-actions" hidden>
                <button class="btn" type="button" data-act="fire"><x-icon name="fire" /> Send</button>
                <button class="btn" type="button" data-act="bill"><x-icon name="printer" /> Bill</button>
                <button class="btn btn-primary" type="button" data-act="pay"><x-icon name="cash" /> Pay</button>
                <button class="btn" type="button" data-act="discount"><x-icon name="percent" /> Discount</button>
                <button class="btn" type="button" data-act="transfer"><x-icon name="transfer" /> Move</button>
                <button class="btn" type="button" data-act="merge"><x-icon name="merge" /> Merge</button>
                <button class="btn" type="button" data-act="split"><x-icon name="split" /> Split</button>
                <button class="btn" type="button" data-act="notes"><x-icon name="edit" /> Notes</button>
                <button class="btn" type="button" data-act="void" style="color:var(--crit)"><x-icon name="trash" /> Void</button>
            </div>
        </div>
    </aside>
</div>

{{-- Dialogs --}}
<x-modal id="m-modifiers" title="Options"><div class="modal-body" id="mod-body"></div>
    <div class="modal-foot"><input type="text" id="mod-notes" placeholder="Kitchen note (optional)" aria-label="Kitchen note" style="flex:1"><button class="btn" data-modal-close type="button">Cancel</button><button class="btn btn-primary" type="button" id="mod-add">Add to check</button></div></x-modal>
<x-modal id="m-covers" title="Open table"><div class="modal-body form-grid">
    <div class="field f-6"><label for="covers">Covers</label><input type="number" id="covers" min="1" max="40" value="2"></div>
    <div class="field f-6"><label for="guest-name">Guest name (optional)</label><input type="text" id="guest-name" maxlength="120"></div></div>
    <div class="modal-foot"><button class="btn" data-modal-close type="button">Cancel</button><button class="btn btn-primary" type="button" id="covers-ok">Open check</button></div></x-modal>
<x-modal id="m-room" title="Room service — choose villa"><div class="modal-body"><div id="room-list" class="stack" style="gap:6px"></div></div></x-modal>
<x-modal id="m-pay" title="Take payment" wide>
    <div class="modal-body grid cols-2">
        <div class="stack" style="gap:10px">
            <div class="totals"><span>Balance due</span><span class="grand" id="pay-due">—</span><span class="muted">Entered</span><span id="pay-entered">—</span><span class="muted">Change</span><span id="pay-change">—</span></div>
            <div id="tenders" class="stack" style="gap:8px"></div>
            <div class="row">
                <button class="btn btn-sm" type="button" data-tender="cash"><x-icon name="cash" /> Cash</button>
                <button class="btn btn-sm" type="button" data-tender="card"><x-icon name="card" /> Card</button>
                <button class="btn btn-sm" type="button" data-tender="bank_transfer"><x-icon name="bank" /> Bank transfer</button>
                <button class="btn btn-sm" type="button" data-tender="digital"><x-icon name="qr" /> Digital / QR</button>
                <button class="btn btn-sm" type="button" data-tender="online"><x-icon name="globe" /> Online</button>
                <button class="btn btn-sm" type="button" data-tender="room_charge"><x-icon name="villa" /> Charge to villa</button>
            </div>
            <p class="small muted" style="margin:0">Split a bill across several tenders. Card payments: record the terminal slip reference only.</p>
        </div>
        <div>
            <div class="keypad" id="keypad">@foreach (['7','8','9','4','5','6','1','2','3','0','00','⌫'] as $k)<button type="button" data-key="{{ $k }}">{{ $k }}</button>@endforeach</div>
            <div class="row" style="margin-top:8px">@foreach ([1000, 5000, 10000, 20000] as $q)<button class="btn btn-sm" type="button" data-quick="{{ $q }}">{{ number_format($q) }}</button>@endforeach<button class="btn btn-sm" type="button" data-quick="exact">Exact</button></div>
        </div>
    </div>
    <div class="modal-foot"><button class="btn" data-modal-close type="button">Cancel</button><button class="btn btn-primary btn-lg" type="button" id="pay-ok">Complete payment</button></div>
</x-modal>
<x-modal id="m-tables" title="Choose table"><div class="modal-body"><div id="pick-tables" class="tables-grid"></div></div></x-modal>
<x-modal id="m-split" title="Split check"><div class="modal-body"><p class="small muted">Choose quantities to move to a new check (same table). Take payment on each check separately.</p><div id="split-lines" class="stack" style="gap:6px"></div></div>
    <div class="modal-foot"><button class="btn" data-modal-close type="button">Cancel</button><button class="btn btn-primary" type="button" id="split-ok">Create new check</button></div></x-modal>
<x-modal id="m-discount" title="Discount"><div class="modal-body form-grid">
    <div class="field f-6"><label for="disc-type">Type</label><select id="disc-type"><option value="percent">Percentage %</option><option value="fixed">Fixed amount</option></select></div>
    <div class="field f-6"><label for="disc-value">Value</label><input type="number" id="disc-value" min="0" step="0.01"></div>
    <div class="field f-12"><label for="disc-reason">Reason</label><input type="text" id="disc-reason" maxlength="200" placeholder="e.g. Returning guest, complaint recovery"></div></div>
    <div class="modal-foot"><button class="btn" type="button" id="disc-clear">Remove discount</button><button class="btn" data-modal-close type="button">Cancel</button><button class="btn btn-primary" type="button" id="disc-ok">Apply</button></div></x-modal>
<x-modal id="m-prompt" title=""><div class="modal-body"><label for="prompt-input" class="label" id="prompt-label"></label><input type="text" id="prompt-input" maxlength="200" style="margin-top:6px"></div>
    <div class="modal-foot"><button class="btn" data-modal-close type="button">Cancel</button><button class="btn btn-primary" type="button" id="prompt-ok">OK</button></div></x-modal>
@endsection
@push('scripts')<script src="{{ asset_v('assets/js/pos.js') }}"></script>@endpush
