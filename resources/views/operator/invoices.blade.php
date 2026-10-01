@extends('layouts.operator')
@section('title', 'Invoices & payments')
@section('content')
<x-page-header title="Invoices & payments">
    <button class="btn btn-primary" type="button" data-modal-open="#advice-modal"><x-icon name="upload" /> Notify a payment</button>
</x-page-header>
<div class="grid cols-main">
    <div class="card">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Number</th><th>Type</th><th>Issued</th><th>Due</th><th>Status</th><th class="num">Total</th><th class="num">Balance</th></tr></thead>
            <tbody>
            @forelse ($invoices as $i)
                <tr><td><a class="mono" href="{{ route('operator.invoices.show', $i) }}" target="_blank">{{ $i->number }}</a></td><td>{{ $i->type === 'operator_invoice' ? 'Invoice' : $i->typeLabel() }}</td>
                    <td>{{ fmt_date($i->issued_at) }}</td><td>{{ fmt_date($i->due_date) }}</td><td><x-badge :status="$i->status" /></td>
                    <td class="num">{{ money($i->grand_total) }}</td><td class="num">{{ money($i->balance) }}</td></tr>
            @empty
                <tr><td colspan="7"><x-empty title="No invoices yet" icon="file" /></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $invoices->links() }}
    </div>
    <div class="card" style="align-self:start">
        <div class="card-head"><h2>Payments received</h2></div>
        <ul class="list">
            @forelse ($payments as $p)<li><span class="small">{{ fmt_date($p->paid_at) }} · {{ $p->methodLabel() }}<br><span class="muted mono">{{ $p->reference }}</span></span><span class="spacer"></span><strong>{{ money($p->amount) }}</strong></li>
            @empty<li class="muted small">No payments yet.</li>@endforelse
        </ul>
    </div>
</div>
<x-modal id="advice-modal" title="Notify a bank transfer">
    <form method="post" action="{{ route('operator.payments.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-body form-grid">
            <x-input name="amount" type="number" step="0.01" min="1" label="Amount" required col="f-6" />
            <x-input name="reference" label="Bank reference" required col="f-6" />
            <x-select name="invoice_id" label="For invoice" :options="$open->mapWithKeys(fn ($i) => [$i->id => $i->number.' · '.money($i->balance)])" placeholder="Oldest open invoices" col="f-12" />
            <div class="field f-12"><label for="proof">Remittance advice (PDF or image)</label><input type="file" id="proof" name="proof" required accept="image/*,application/pdf"></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Send</button></div>
    </form>
</x-modal>
@endsection
