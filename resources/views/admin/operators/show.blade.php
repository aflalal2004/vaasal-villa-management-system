@extends('layouts.admin')
@section('title', $op->company_name)
@section('content')
@php $contract = $op->activeContract(); @endphp
<x-page-header :title="$op->company_name" :crumbs="['Tour operators' => route('admin.operators.index')]" :sub="$op->code.' · '.$op->contact_name.' · '.$op->email.' · '.$op->country">
    <x-badge :status="$op->status" />
    @perm('operators.manage')
        @if ($op->status === 'pending')<form method="post" action="{{ route('admin.operators.approve', $op) }}">@csrf<button class="btn btn-primary" type="submit"><x-icon name="check" /> Approve</button></form>@endif
        @if ($op->status !== 'pending')<form method="post" action="{{ route('admin.operators.suspend', $op) }}" data-confirm="{{ $op->status === 'suspended' ? 'Reactivate' : 'Suspend' }} {{ $op->company_name }}?">@csrf<button class="btn" type="submit">{{ $op->status === 'suspended' ? 'Reactivate' : 'Suspend' }}</button></form>@endif
        <a class="btn" href="{{ route('admin.operators.edit', $op) }}"><x-icon name="edit" /> Edit</a>
    @endperm
    @perm('bookings.manage')<a class="btn" href="{{ route('admin.bookings.create', ['source' => 'tour_operator']) }}"><x-icon name="plus" /> Group booking</a>@endperm
    <a class="btn" href="{{ route('admin.operators.statement', $op) }}" target="_blank"><x-icon name="file" /> Statement</a>
</x-page-header>

<div class="stats">
    <x-stat label="Outstanding" :value="money($statement['outstanding'])" :hint="'Credit limit '.money($op->credit_limit)" :pct="$op->credit_limit > 0 ? $statement['outstanding'] / $op->credit_limit * 100 : 0" />
    @foreach ($statement['ageing'] as $bucket => $amt)<x-stat :label="$bucket.' days overdue'" :value="money($amt)" />@endforeach
    <x-stat label="Commission accrued" :value="money($commissions->where('status', 'accrued')->sum('amount'))" />
</div>

<div class="grid cols-main">
    <div class="stack">
        <div class="card">
            <div class="card-head"><h2>Bookings</h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Ref</th><th>Group / guest</th><th>Villas</th><th>Stay</th><th>Status</th><th class="num">Total</th></tr></thead>
                <tbody>
                @forelse ($bookings as $b)
                    <tr><td><a class="mono" href="{{ route('admin.bookings.show', $b) }}">{{ $b->reference }}</a></td><td>{{ $b->group_name ?? $b->guest->fullName() }}</td>
                        <td>{{ $b->activeVillas->pluck('villa.code')->implode(', ') }}</td><td class="nowrap small">{{ fmt_date($b->arrival, 'd M') }} → {{ fmt_date($b->departure, 'd M y') }}</td>
                        <td><x-badge :status="$b->status" :label="$b->statusLabel()" /></td><td class="num">{{ money($b->grand_total) }}</td></tr>
                @empty
                    <tr><td colspan="6" class="muted">No bookings yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Invoices (city ledger)</h2>@perm('operators.finance')<button class="btn btn-sm btn-primary" type="button" data-modal-open="#op-pay-modal"><x-icon name="cash" /> Record payment</button>@endperm</div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Invoice</th><th>Issued</th><th>Due</th><th>Status</th><th class="num">Total</th><th class="num">Balance</th></tr></thead>
                <tbody>
                @forelse ($invoices as $i)
                    <tr><td><a class="mono" href="{{ route('admin.invoices.show', $i) }}" target="_blank">{{ $i->number }}</a></td><td>{{ fmt_date($i->issued_at) }}</td>
                        <td style="color:{{ $i->due_date?->isPast() && $i->balance > 0 ? 'var(--crit)' : 'inherit' }}">{{ fmt_date($i->due_date) }}</td>
                        <td><x-badge :status="$i->status" /></td><td class="num">{{ money($i->grand_total) }}</td><td class="num">{{ money($i->balance) }}</td></tr>
                @empty
                    <tr><td colspan="6" class="muted">No invoices yet — they are issued when operator guests check out.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Commission</h2></div>
            <form method="post" action="{{ route('admin.operators.commissions.settle', $op) }}">
                @csrf
                <div class="table-wrap"><table class="table">
                    <thead><tr><th></th><th>Booking</th><th class="num">Basis</th><th class="num">%</th><th class="num">Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($commissions as $c)
                        <tr><td>@if($c->status === 'accrued')<input type="checkbox" name="commission_ids[]" value="{{ $c->id }}" aria-label="Select">@endif</td>
                            <td class="mono">{{ $c->booking->reference }}</td><td class="num">{{ money($c->basis_amount) }}</td><td class="num">{{ (float) $c->pct }}</td>
                            <td class="num">{{ money($c->amount) }}</td><td><x-badge :status="$c->status === 'settled' ? 'paid' : 'pending'" :label="ucfirst($c->status)" /></td></tr>
                    @empty
                        <tr><td colspan="6" class="muted">Commission accrues at checkout.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                @perm('operators.finance')<div class="card-foot"><button class="btn btn-sm" type="submit">Mark selected as settled</button></div>@endperm
            </form>
        </div>
    </div>

    <div class="stack" style="align-content:start">
        <div class="card">
            <div class="card-head"><h2>Contracts</h2>@perm('operators.manage')<button class="btn btn-sm" type="button" data-modal-open="#contract-new"><x-icon name="plus" /> New</button>@endperm</div>
            <ul class="list">
                @forelse ($op->contracts as $c)
                    <li style="display:block">
                        <div class="row between"><strong>{{ $c->name }}</strong>
                            <span>@if($contract && $contract->id === $c->id)<x-badge status="active" label="In force" />@endif @perm('operators.manage')<button class="btn btn-sm btn-ghost" type="button" data-modal-open="#contract-{{ $c->id }}">Edit</button>@endperm</span></div>
                        <div class="small muted">{{ fmt_date($c->valid_from) }} – {{ fmt_date($c->valid_to) }} · commission {{ (float) $c->commission_pct }}% · discount {{ (float) $c->discount_pct }}% · deposit {{ (float) $c->deposit_pct }}% · release {{ $c->release_days }}d · rooming list cut-off {{ $c->rooming_cutoff_days }}d</div>
                        @if ($c->rates->isNotEmpty())<div class="small">Net rates: @foreach ($c->rates as $r){{ $r->villaType->name }} {{ money($r->net_rate) }}@if(! $loop->last) · @endif @endforeach</div>@endif
                    </li>
                @empty
                    <li class="muted small">No contract — public rates apply.</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-head"><h2>Portal logins</h2></div>
            <ul class="list">
                @foreach ($op->users as $u)<li><x-icon name="user" /> <span class="small">{{ $u->name }}<br><span class="muted">{{ $u->email }} · last login {{ $u->last_login_at?->diffForHumans() ?? 'never' }}</span></span></li>@endforeach
            </ul>
            @perm('operators.manage')
            <form method="post" action="{{ route('admin.operators.users', $op) }}" class="card-body form-grid" style="border-top:1px solid var(--line-2)">
                @csrf
                <x-input name="name" label="Name" required col="f-12" />
                <x-input name="email" type="email" label="Email" required col="f-12" />
                <x-input name="password" type="password" label="Initial password" required col="f-12" autocomplete="new-password" />
                <div class="f-12"><button class="btn btn-sm" type="submit">Create login</button></div>
            </form>
            @endperm
        </div>
        <div class="card">
            <div class="card-head"><h2>Company</h2></div>
            <div class="card-body"><dl class="dl">
                <dt>Legal name</dt><dd>{{ $op->legal_name ?? '—' }}</dd><dt>Reg. no.</dt><dd>{{ $op->registration_no ?? '—' }}</dd><dt>Tax ID</dt><dd>{{ $op->tax_id ?? '—' }}</dd>
                <dt>Phone</dt><dd>{{ $op->phone }}</dd><dt>Address</dt><dd>{{ $op->address }}</dd><dt>Terms</dt><dd>{{ $op->payment_terms_days }} days</dd>
                <dt>Approved</dt><dd>{{ $op->approved_at ? fmt_date($op->approved_at).' by '.($op->approver?->name ?? 'system') : 'Not yet' }}</dd>
            </dl></div>
        </div>
    </div>
</div>

<x-modal id="op-pay-modal" title="Record operator payment">
    <form method="post" action="{{ route('admin.operators.payment', $op) }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-body form-grid">
            <x-input name="amount" type="number" step="0.01" min="0.01" label="Amount" required col="f-6" />
            <x-select name="method" label="Method" :options="['bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque', 'card' => 'Card', 'cash' => 'Cash']" col="f-6" />
            <x-select name="invoice_id" label="Apply to invoice" :options="$invoices->whereIn('status', ['issued', 'partially_paid'])->mapWithKeys(fn ($i) => [$i->id => $i->number.' · '.money($i->balance)])" placeholder="Oldest first (automatic)" col="f-12" />
            <x-input name="reference" label="Bank reference" col="f-6" />
            <div class="field f-6"><label for="proof">Remittance / slip</label><input type="file" id="proof" name="proof" accept="image/*,application/pdf"></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Record payment</button></div>
    </form>
</x-modal>

@foreach ($op->contracts->concat([new \App\Models\OperatorContract(['valid_from' => now()->startOfYear(), 'valid_to' => now()->endOfYear(), 'commission_pct' => 10, 'discount_pct' => 0, 'deposit_pct' => 25, 'release_days' => 14, 'rooming_cutoff_days' => 3, 'is_active' => true])]) as $c)
<x-modal :id="$c->exists ? 'contract-'.$c->id : 'contract-new'" :title="$c->exists ? 'Edit '.$c->name : 'New contract'" wide>
    <form method="post" action="{{ $c->exists ? route('admin.operators.contracts.update', $c) : route('admin.operators.contracts.store', $op) }}">
        @csrf @if($c->exists) @method('put') @endif
        <div class="modal-body form-grid">
            <x-input name="name" label="Name" :value="$c->name" required col="f-6" :id="'cn'.$c->id" />
            <x-input name="valid_from" type="date" label="From" :value="$c->valid_from" required col="f-3" :id="'cf'.$c->id" />
            <x-input name="valid_to" type="date" label="To" :value="$c->valid_to" required col="f-3" :id="'ct'.$c->id" />
            <x-input name="commission_pct" type="number" step="0.01" label="Commission %" :value="$c->commission_pct" required col="f-3" :id="'cc'.$c->id" />
            <x-input name="discount_pct" type="number" step="0.01" label="Discount % (no net rate)" :value="$c->discount_pct" required col="f-3" :id="'cd'.$c->id" />
            <x-input name="deposit_pct" type="number" step="0.01" label="Deposit %" :value="$c->deposit_pct" required col="f-2" :id="'cp'.$c->id" />
            <x-input name="release_days" type="number" label="Release days" :value="$c->release_days" required col="f-2" :id="'cr'.$c->id" />
            <x-input name="rooming_cutoff_days" type="number" label="Rooming cut-off" :value="$c->rooming_cutoff_days" required col="f-2" :id="'cx'.$c->id" />
            <div class="field f-12"><span class="label">Net rates per night (optional; overrides public rate)</span>
                <div class="form-grid">@foreach ($types as $t)
                    <x-input :name="'rates['.$t->id.']'" type="number" step="0.01" min="0" :label="$t->name" :value="$c->exists ? $c->netRateFor($t->id) : null" col="f-3" :id="'rt'.$c->id.'_'.$t->id" />
                @endforeach</div></div>
            <x-textarea name="notes" label="Notes" :value="$c->notes" rows="2" :id="'cno'.$c->id" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Save contract</button></div>
    </form>
</x-modal>
@endforeach
@endsection
