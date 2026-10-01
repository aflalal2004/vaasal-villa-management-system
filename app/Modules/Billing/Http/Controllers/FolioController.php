<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChargeItem;
use App\Models\Folio;
use App\Models\FolioLine;
use App\Modules\Billing\Services\FolioService;
use App\Modules\Billing\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FolioController extends Controller
{
    public function __construct(private FolioService $folios, private InvoiceService $invoices) {}

    /** Post a guest service charge (laundry, spa, transport, airport transfer, minibar …). */
    public function postCharge(Request $request, Folio $folio)
    {
        $data = $request->validate([
            'charge_item_id' => ['nullable', 'exists:charge_items,id'],
            'department' => ['required', Rule::in(array_keys(config('vaasal.departments')))],
            'description' => ['required', 'string', 'max:200'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:999'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'apply_service' => ['nullable', 'boolean'],
        ]);
        $item = ! empty($data['charge_item_id']) ? ChargeItem::find($data['charge_item_id']) : null;
        $line = $this->folios->post($folio, $data['department'], $data['description'], (float) $data['quantity'], (float) $data['unit_price'], $item,
            applyTax: $item ? $item->taxable : true, applyService: $request->boolean('apply_service') || ($item?->service_chargeable ?? false));
        return back()->with('success', 'Posted '.money($line->total).' to '.$folio->folio_no.'.');
    }

    public function reverse(Request $request, FolioLine $line)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $this->folios->reverse($line, $data['reason']);
        return back()->with('success', 'Line reversed.');
    }

    public function payment(Request $request, Folio $folio)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(['cash', 'card', 'bank_transfer', 'cheque'])],
            'type' => ['required', Rule::in(['payment', 'deposit'])],
            'notes' => ['nullable', 'string', 'max:200'],
        ]);
        $p = $this->folios->recordPayment($folio, $data['method'], (float) $data['amount'], $data['type'], $data['notes'] ?? null);
        $receipt = $this->invoices->issueReceipt($p);
        return back()->with('success', 'Payment '.$p->reference.' recorded. Receipt '.$receipt->number.' issued.');
    }

    public function refund(Request $request, Folio $folio)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(['cash', 'card', 'bank_transfer'])],
            'reason' => ['required', 'string', 'max:200'],
        ]);
        $p = $this->folios->refund($folio, $data['method'], (float) $data['amount'], $data['reason']);
        return back()->with('success', 'Refund '.$p->reference.' recorded.');
    }

    public function interimInvoice(Folio $folio)
    {
        $inv = $this->invoices->issueFolioInvoice($folio, 'proforma');
        return redirect()->route('admin.invoices.show', $inv);
    }

    public function print(Folio $folio)
    {
        $folio->load(['booking.guest', 'booking.villas.villa', 'lines', 'payments', 'operator']);
        return view('print.folio', ['folio' => $folio, 's' => $this->folios->summary($folio)]);
    }
}
