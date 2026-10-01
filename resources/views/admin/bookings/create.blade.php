@extends('layouts.admin')
@section('title', $source === 'walk_in' ? 'Walk-in booking' : 'New booking')
@section('content')
<x-page-header :title="$source === 'walk_in' ? 'Walk-in booking' : 'New booking'" :crumbs="['Bookings' => route('admin.bookings.index')]"
    sub="Pick dates to see villas that are free on every night. The same ledger feeds the website and OTAs, so what you see is bookable." />

<form method="post" action="{{ route('admin.bookings.store') }}" id="booking-form" class="grid cols-main">
    @csrf
    <div class="stack">
        <div class="card">
            <div class="card-head"><h2>1 · Stay</h2></div>
            <div class="card-body form-grid">
                <x-select name="source" label="Booking source" required col="f-4" :value="old('source', $source)"
                    :options="['phone' => 'Phone', 'email' => 'Email', 'walk_in' => 'Walk-in', 'admin' => 'Admin / manual', 'tour_operator' => 'Tour operator']" />
                <x-input name="arrival" type="date" label="Arrival" required col="f-4" :value="$arrival" />
                <x-input name="departure" type="date" label="Departure" required col="f-4" :value="$departure" />
                <x-select name="rate_plan_id" label="Rate plan" col="f-4" :options="$plans->pluck('name', 'id')" :value="old('rate_plan_id', $plans->first()?->id)" />
                <x-input name="promo_code" label="Promo code" col="f-4" placeholder="e.g. STAY4" />
                <x-select name="status" label="Status" col="f-4" :options="['confirmed' => 'Confirmed', 'tentative' => 'Tentative (hold without guarantee)']" required />
                <div class="field f-6" id="operator-field" @if(old('source', $source) !== 'tour_operator') hidden @endif>
                    <label for="f_tour_operator_id">Tour operator <span class="req">*</span></label>
                    <select name="tour_operator_id" id="f_tour_operator_id"><option value="">Choose operator…</option>
                        @foreach ($operators as $id => $n)<option value="{{ $id }}" @selected(old('tour_operator_id') == $id)>{{ $n }}</option>@endforeach
                    </select>
                    <span class="help" id="contract-info"></span>
                </div>
                <x-input name="group_name" label="Group name (optional)" col="f-6" />
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>2 · Villas available for these dates</h2><span class="small muted" id="avail-status">Loading…</span></div>
            <div class="table-wrap">
                <table class="table" id="villa-table">
                    <thead><tr><th></th><th>Villa</th><th>Housekeeping</th><th class="num">Adults</th><th class="num">Children</th><th class="num">Avg / night</th><th class="num">Stay total</th></tr></thead>
                    <tbody><tr><td colspan="7" class="muted">Choose dates to load availability.</td></tr></tbody>
                </table>
            </div>
            <div class="card-body small muted" style="border-top:1px solid var(--line-2)">Totals include service charge and tax. A villa that is booked on any night of the stay is not listed.</div>
        </div>

        <div class="card">
            <div class="card-head"><h2>3 · Guest</h2></div>
            <div class="card-body form-grid">
                @if ($guest)
                    <input type="hidden" name="guest[id]" value="{{ $guest->id }}">
                    <div class="f-12 alert alert-info" style="margin:0">Booking for existing guest <strong>{{ $guest->fullName() }}</strong> ({{ $guest->email ?? $guest->phone }}).</div>
                @else
                    <x-input name="guest[first_name]" label="First name" required col="f-4" />
                    <x-input name="guest[last_name]" label="Last name" required col="f-4" />
                    <x-input name="guest[country]" label="Country" col="f-4" />
                    <x-input name="guest[email]" type="email" label="Email" col="f-6" help="Returning guests are matched by email." />
                    <x-input name="guest[phone]" label="Phone / WhatsApp" col="f-6" />
                @endif
                <x-input name="arrival_time" label="Expected arrival time" col="f-4" placeholder="e.g. 15:00" />
                <x-textarea name="special_requests" label="Special requests" col="f-8" rows="2" />
            </div>
        </div>
    </div>

    <div class="stack" style="align-content:start">
        <div class="card" style="position:sticky;top:76px">
            <div class="card-head"><h2>Summary</h2></div>
            <div class="card-body stack" style="gap:10px">
                <div class="totals">
                    <span class="muted">Villas selected</span><span id="sum-count">0</span>
                    <span class="muted">Nights</span><span id="sum-nights">—</span>
                    <span class="muted">Promo</span><span id="sum-promo">—</span>
                    <span class="grand">Total</span><span class="grand" id="sum-total">—</span>
                </div>
                @perm('bookings.override_rate')
                    <details>
                        <summary class="small" style="cursor:pointer">Override nightly rate</summary>
                        <div class="form-grid" style="margin-top:10px">
                            <x-input name="rate_override" type="number" step="0.01" min="0" label="Nightly rate" col="f-12" />
                            <x-input name="override_reason" label="Reason (required)" col="f-12" />
                        </div>
                    </details>
                @endperm
                <hr style="margin:4px 0">
                <div class="form-grid">
                    <x-input name="deposit_amount" type="number" step="0.01" min="0" label="Deposit taken now" col="f-6" />
                    <x-select name="deposit_method" label="Method" col="f-6" :options="['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank transfer']" />
                </div>
                @if ($source === 'walk_in')<label class="check"><input type="checkbox" name="go_checkin" value="1" checked> Continue to check-in</label>@endif
                <button class="btn btn-primary btn-lg btn-block" type="submit" id="submit-btn" disabled><x-icon name="check" /> Create booking</button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('booking-form');
    const tbody = document.querySelector('#villa-table tbody');
    const status = document.getElementById('avail-status');
    const preselect = @json($preselect);
    const oldSel = @json(collect(old('villas', []))->pluck('villa_id')->all());
    let timer;

    function selected() { return [...tbody.querySelectorAll('input[type=checkbox]:checked')]; }
    function refreshSummary(data) {
        const sel = selected();
        let total = 0;
        sel.forEach(cb => total += Number(cb.dataset.total || 0));
        document.getElementById('sum-count').textContent = sel.length;
        document.getElementById('sum-total').textContent = sel.length ? VV.money(total) : '—';
        document.getElementById('submit-btn').disabled = sel.length === 0;
        if (data) {
            document.getElementById('sum-nights').textContent = data.villas[0]?.nights ?? '—';
            document.getElementById('sum-promo').textContent = data.promo ? data.promo.label : (data.promo_invalid ? 'Invalid code' : '—');
            document.getElementById('contract-info').textContent = data.contract || '';
        }
    }
    async function load() {
        const fd = new FormData(form);
        const body = { arrival: fd.get('arrival'), departure: fd.get('departure'), rate_plan_id: fd.get('rate_plan_id'),
            promo_code: fd.get('promo_code'), tour_operator_id: fd.get('source') === 'tour_operator' ? fd.get('tour_operator_id') : null };
        if (!body.arrival || !body.departure || body.departure <= body.arrival) { status.textContent = 'Departure must be after arrival.'; return; }
        status.textContent = 'Checking availability…';
        try {
            const data = await VV.api(@json(route('admin.bookings.quote')), 'POST', body);
            tbody.innerHTML = '';
            if (!data.villas.length) { tbody.innerHTML = '<tr><td colspan="7" class="muted">No villa is free for every night of this stay. Try other dates or check the calendar.</td></tr>'; }
            data.villas.forEach(v => {
                const pre = String(v.id) === String(preselect) || oldSel.map(String).includes(String(v.id));
                const warn = v.errors.length ? '<div class="small" style="color:var(--warn)">' + VV.esc(v.errors.join(' ')) + '</div>' : '';
                tbody.insertAdjacentHTML('beforeend', `<tr>
                    <td><input type="checkbox" name="villas[${v.id}][selected]" value="1" data-total="${v.total}" ${pre ? 'checked' : ''} aria-label="Select ${VV.esc(v.code)}">
                        <input type="hidden" name="villas[${v.id}][villa_id]" value="${v.id}"></td>
                    <td><strong>${VV.esc(v.code)}</strong> ${VV.esc(v.name)}<div class="small muted">${VV.esc(v.type)} · up to ${v.max_adults} adults, ${v.max_children} children</div>${warn}</td>
                    <td><span class="badge tone-${v.hk_status === 'ready' ? 'success' : (v.hk_status === 'dirty' ? 'danger' : 'warning')}">${VV.esc(v.hk_status)}</span>
                        ${v.maintenance !== 'ok' ? '<span class="badge tone-warning">maintenance</span>' : ''}</td>
                    <td class="num"><input type="number" name="villas[${v.id}][adults]" min="1" max="${v.max_adults}" value="${Math.min(2, v.max_adults)}" style="width:70px"></td>
                    <td class="num"><input type="number" name="villas[${v.id}][children]" min="0" max="${v.max_children}" value="0" style="width:70px"></td>
                    <td class="num">${VV.money(v.avg)}</td>
                    <td class="num"><strong>${VV.money(v.total)}</strong>${v.discount > 0 ? '<div class="small muted">incl. discount ' + VV.money(v.discount) + '</div>' : ''}</td></tr>`);
            });
            status.textContent = data.villas.length + ' villa(s) free';
            refreshSummary(data);
        } catch (e) { status.textContent = e.message; }
    }
    form.addEventListener('change', e => {
        if (e.target.name === 'source') document.getElementById('operator-field').hidden = e.target.value !== 'tour_operator';
        if (['arrival', 'departure', 'rate_plan_id', 'source', 'tour_operator_id'].includes(e.target.name)) load();
        if (e.target.type === 'checkbox') refreshSummary();
    });
    form.querySelector('[name=promo_code]').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 500); });
    load();
})();
</script>
@endpush
