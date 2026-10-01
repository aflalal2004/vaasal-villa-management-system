{{-- Guest folio(s): every department posts here; checkout settles and invoices them. Expects $b (booking), $summaries, $chargeItems --}}
@foreach ($b->folios as $folio)
    @php $s = $summaries[$folio->id]; @endphp
    <div class="card">
        <div class="card-head">
            <h2>Folio {{ $folio->folio_no }} <span class="muted small" style="font-weight:400">· {{ $folio->payer_type === 'operator' ? 'Billed to '.$folio->payerName().' (city ledger)' : 'Guest account' }}</span></h2>
            <x-badge :status="$folio->status === 'open' ? 'active' : 'neutral'" :label="ucfirst($folio->status)" />
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Date</th><th>Department</th><th>Description</th><th class="num">Qty</th><th class="num">Net</th><th class="num">Svc</th><th class="num">Tax</th><th class="num">Total</th><th></th></tr></thead>
                <tbody>
                @forelse ($folio->lines as $l)
                    <tr @class(['strike' => $l->is_reversed])>
                        <td class="nowrap">{{ fmt_date($l->business_date, 'd M') }}</td>
                        <td>{{ $l->departmentLabel() }}</td>
                        <td>{{ $l->description }}@if($l->poster)<div class="small muted">by {{ $l->poster->name }}</div>@endif</td>
                        <td class="num">{{ rtrim(rtrim($l->quantity, '0'), '.') }}</td>
                        <td class="num">{{ money($l->amount, null, false) }}</td>
                        <td class="num">{{ money($l->service_amount, null, false) }}</td>
                        <td class="num">{{ money($l->tax_amount, null, false) }}</td>
                        <td class="num"><strong>{{ money($l->total, null, false) }}</strong></td>
                        <td class="actions">
                            @if ($folio->isOpen() && ! $l->is_reversed && ! $l->reverses_id)
                                @perm('folio.adjust')
                                <form method="post" action="{{ route('admin.folios.reverse', $l) }}" data-confirm="Reverse “{{ $l->description }}” ({{ money($l->total) }})? A negative line will be posted." data-reason data-danger>
                                    @csrf<button class="btn btn-sm btn-ghost" type="submit" title="Reverse line"><x-icon name="x" /></button>
                                </form>
                                @endperm
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">No charges posted yet. Room nights post at night audit; restaurant checks charged to the villa appear here automatically.</td></tr>
                @endforelse
                </tbody>
                @if ($folio->payments->isNotEmpty())
                <tbody>
                    <tr><th colspan="9">Payments & deposits</th></tr>
                    @foreach ($folio->payments as $p)
                        <tr>
                            <td class="nowrap">{{ fmt_date($p->paid_at, 'd M') }}</td>
                            <td>{{ ucfirst($p->type) }}</td>
                            <td>{{ $p->methodLabel() }} · <span class="mono small">{{ $p->reference }}</span>@if($p->notes)<div class="small muted">{{ $p->notes }}</div>@endif</td>
                            <td colspan="4"></td>
                            <td class="num" style="color:{{ $p->amount < 0 ? 'var(--crit)' : 'var(--ok)' }}">{{ money(-1 * $p->amount, null, false) }}</td>
                            <td class="actions">@if($p->invoice_id)<a class="btn btn-sm btn-ghost" href="{{ route('admin.invoices.show', $p->invoice_id) }}" title="Receipt"><x-icon name="file" /></a>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
                @endif
                <tfoot>
                    <tr><td colspan="7">Charges {{ money($s['charges']) }} · Paid {{ money($s['paid']) }}</td><td class="num" colspan="2">Balance {{ money($s['balance']) }}</td></tr>
                </tfoot>
            </table>
        </div>
        <div class="card-foot">
            <a class="btn btn-sm" href="{{ route('admin.folios.print', $folio) }}" target="_blank"><x-icon name="printer" /> Print folio</a>
            @foreach ($folio->invoices->where('type', '!=', 'receipt') as $inv)
                <a class="btn btn-sm" href="{{ route('admin.invoices.show', $inv) }}"><x-icon name="file" /> {{ $inv->number }}</a>
            @endforeach
            <span class="spacer"></span>
            @if ($folio->isOpen())
                @perm('folio.post')<button class="btn btn-sm" type="button" data-modal-open="#charge-modal" data-action="{{ route('admin.folios.charge', $folio) }}"><x-icon name="plus" /> Post charge</button>@endperm
                @perm('payments.refund')@if($s['paid'] > 0)<button class="btn btn-sm" type="button" data-modal-open="#refund-modal" data-action="{{ route('admin.folios.refund', $folio) }}" data-max="{{ $s['paid'] }}">Refund</button>@endif @endperm
                @perm('payments.receive')<button class="btn btn-sm btn-primary" type="button" data-modal-open="#payment-modal" data-action="{{ route('admin.folios.payment', $folio) }}" data-amount="{{ max(0, $s['balance']) }}"><x-icon name="cash" /> Take payment</button>@endperm
            @endif
        </div>
    </div>
@endforeach

@once
<x-modal id="charge-modal" title="Post a charge to the folio">
    <form method="post" action="#">
        @csrf
        <div class="modal-body form-grid">
            <div class="field f-12">
                <label for="charge_item_id">Service</label>
                <select name="charge_item_id" id="charge_item_id" onchange="const o=this.selectedOptions[0];document.getElementById('charge_price').value=o.dataset.price||'';document.getElementById('charge_desc').value=o.dataset.name||'';document.getElementById('charge_dept').value=o.dataset.dept||'misc'">
                    <option value="">Custom charge…</option>
                    @foreach ($chargeItems->groupBy('department') as $dept => $items)
                        <optgroup label="{{ config('vaasal.departments.'.$dept, $dept) }}">
                            @foreach ($items as $ci)<option value="{{ $ci->id }}" data-price="{{ $ci->price }}" data-name="{{ $ci->name }}" data-dept="{{ $ci->department }}">{{ $ci->name }} — {{ money($ci->price) }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="field f-6"><label for="charge_dept">Department</label>
                <select name="department" id="charge_dept">@foreach (config('vaasal.departments') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="field f-6"><label for="charge_desc">Description</label><input type="text" name="description" id="charge_desc" required maxlength="200"></div>
            <div class="field f-4"><label for="charge_qty">Quantity</label><input type="number" name="quantity" id="charge_qty" value="1" min="0.01" step="0.01" required></div>
            <div class="field f-4"><label for="charge_price">Unit price</label><input type="number" name="unit_price" id="charge_price" min="0" step="0.01" required></div>
            <div class="field f-4" style="align-content:end"><label class="check"><input type="checkbox" name="apply_service" value="1"> Add service charge</label></div>
            <p class="small muted f-12" style="margin:0">Tax is added automatically for taxable services.</p>
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Post charge</button></div>
    </form>
</x-modal>

<x-modal id="payment-modal" title="Take a payment">
    <form method="post" action="#" enctype="multipart/form-data">
        @csrf
        <div class="modal-body form-grid">
            <div class="field f-6"><label for="pay_amount">Amount</label><input type="number" name="amount" id="pay_amount" data-fill="amount" min="0.01" step="0.01" required></div>
            <div class="field f-6"><label for="pay_method">Method</label>
                <select name="method" id="pay_method">@foreach (\App\Models\Payment::METHODS as $k => $v)@continue(in_array($k, ['city_ledger', 'online']))<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="field f-6"><label for="pay_type">Type</label><select name="type" id="pay_type"><option value="payment">Payment</option><option value="deposit">Deposit</option></select></div>
            <div class="field f-6"><label for="pay_ref">Reference (card slip / transfer ref)</label><input type="text" name="notes" id="pay_ref" maxlength="200"></div>
            <p class="small muted f-12" style="margin:0">Card payments are taken on the bank terminal; record only the slip reference — never the card number.</p>
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Record payment & issue receipt</button></div>
    </form>
</x-modal>

<x-modal id="refund-modal" title="Refund to guest">
    <form method="post" action="#">
        @csrf
        <div class="modal-body form-grid">
            <div class="field f-6"><label for="ref_amount">Amount</label><input type="number" name="amount" id="ref_amount" min="0.01" step="0.01" required></div>
            <div class="field f-6"><label for="ref_method">Method</label><select name="method" id="ref_method"><option value="cash">Cash</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option></select></div>
            <div class="field f-12"><label for="ref_reason">Reason</label><input type="text" name="reason" id="ref_reason" required maxlength="200"></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-danger" type="submit">Record refund</button></div>
    </form>
</x-modal>
@endonce
