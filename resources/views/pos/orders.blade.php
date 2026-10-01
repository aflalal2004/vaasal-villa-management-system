@extends('layouts.pos')
@section('title', 'Checks')
@section('content')
<x-page-header title="Checks" sub="Open and closed checks for the day. Refunds require POS refund permission and a reason." />
<form class="filters" method="get">
    <div class="field"><label for="date">Date</label><input id="date" type="date" name="date" value="{{ $date }}" data-autosubmit></div>
    <div class="field"><label for="outlet">Outlet</label><select id="outlet" name="outlet" data-autosubmit><option value="">All</option>@foreach ($outlets as $id => $n)<option value="{{ $id }}" @selected(request('outlet') == $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status" data-autosubmit><option value="">All</option>@foreach (\App\Models\PosOrder::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="field"><label for="q">Check / invoice no.</label><input id="q" type="search" name="q" value="{{ request('q') }}"></div>
    <button class="btn" type="submit">Search</button>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Check</th><th>Outlet</th><th>Where</th><th>Opened</th><th>Staff</th><th>Status</th><th class="num">Total</th><th class="num">Paid</th><th></th></tr></thead>
        <tbody>
        @forelse ($orders as $o)
            <tr>
                <td class="mono">{{ $o->order_no }}@if($o->invoice_no)<div class="small muted">{{ $o->invoice_no }}</div>@endif</td>
                <td>{{ $o->outlet->name }}</td>
                <td>{{ $o->table ? 'Table '.$o->table->name : ($o->type === 'room_service' ? 'Villa '.$o->villa?->code : 'Takeaway') }}</td>
                <td class="small">{{ $o->opened_at->format('H:i') }}{{ $o->closed_at ? ' → '.$o->closed_at->format('H:i') : '' }}</td>
                <td class="small">{{ $o->waiter?->name }}{{ $o->cashier ? ' / '.$o->cashier->name : '' }}</td>
                <td><x-badge :status="$o->status" :label="$o->statusLabel()" />@if($o->void_reason)<div class="small muted">{{ $o->void_reason }}</div>@endif</td>
                <td class="num">{{ money($o->total) }}</td>
                <td class="num">{{ money($o->paid_amount - $o->refunded_amount) }}</td>
                <td class="actions">
                    @if ($o->isEditable())<a class="btn btn-sm" href="{{ route('pos.order', $o) }}">Open</a>@endif
                    @if (in_array($o->status, ['paid', 'charged_to_room', 'refunded']))<a class="btn btn-sm btn-ghost" href="{{ route('pos.receipt', $o) }}" target="_blank">Receipt</a>@endif
                    @if (in_array($o->status, ['paid', 'charged_to_room']))
                        @perm('pos.refund')<button class="btn btn-sm" type="button" data-modal-open="#refund-modal" data-action="{{ route('pos.orders.refund', $o) }}" data-amount="{{ $o->paid_amount - $o->refunded_amount }}">Refund</button>@endperm
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9"><x-empty title="No checks" icon="utensils" /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $orders->links() }}
</div>
<x-modal id="refund-modal" title="Refund check">
    <form method="post" action="#">
        @csrf
        <div class="modal-body form-grid">
            <div class="field f-6"><label for="ramt">Amount</label><input id="ramt" type="number" step="0.01" min="0.01" name="amount" data-fill="amount" required></div>
            <x-select name="method" label="Refund via" :options="['cash' => 'Cash (from drawer)', 'card' => 'Card reversal', 'bank_transfer' => 'Bank transfer', 'digital' => 'Digital wallet / QR', 'online' => 'Online', 'room_charge' => 'Credit guest folio']" col="f-6" />
            <x-input name="reason" label="Reason" required col="f-12" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-danger" type="submit">Refund</button></div>
    </form>
</x-modal>
@endsection
