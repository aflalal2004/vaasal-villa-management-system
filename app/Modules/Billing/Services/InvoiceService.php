<?php

namespace App\Modules\Billing\Services;

use App\Models\Booking;
use App\Models\Folio;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TourOperator;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\DocumentNumberService;

/**
 * Issues numbered, immutable financial documents. Each invoice stores a JSON snapshot of
 * its lines plus a SHA-256 hash, so a reprint always matches what the guest received.
 */
class InvoiceService
{
    public function __construct(private FolioService $folios) {}

    /** Final combined invoice for a folio (room + restaurant + laundry + spa + transport + …). */
    public function issueFolioInvoice(Folio $folio, string $type = 'invoice'): Invoice
    {
        $folio->loadMissing(['booking.guest', 'operator']);
        $lines = $folio->lines()->get();
        $sum = $this->folios->summary($folio);

        $billTo = $folio->payer_type === 'operator'
            ? [$folio->operator->company_name, trim(($folio->operator->address ?? '')."\n".($folio->operator->tax_id ? 'Tax ID: '.$folio->operator->tax_id : ''))]
            : [$folio->booking->guest->fullName(), trim(($folio->booking->guest->email ?? '')."\n".($folio->booking->guest->address ?? ''))];

        $snapshot = $lines->map(fn ($l) => [
            'date' => $l->business_date->toDateString(), 'department' => $l->departmentLabel(), 'description' => $l->description,
            'qty' => (float) $l->quantity, 'unit_price' => (float) $l->unit_price, 'amount' => (float) $l->amount,
            'tax' => (float) $l->tax_amount, 'service' => (float) $l->service_amount, 'total' => (float) $l->total,
        ])->values()->all();

        return $this->store([
            'type' => $type,
            'folio_id' => $folio->id,
            'booking_id' => $folio->booking_id,
            'tour_operator_id' => $folio->tour_operator_id,
            'bill_to_name' => $billTo[0],
            'bill_to_details' => $billTo[1],
            'subtotal' => $sum['net'],
            'tax_total' => $sum['tax'],
            'service_total' => $sum['service'],
            'grand_total' => $sum['charges'],
            'paid_total' => $sum['paid'],
            'balance' => $sum['balance'],
            'status' => $sum['balance'] <= 0.009 ? 'paid' : ($sum['paid'] > 0 ? 'partially_paid' : 'issued'),
            'due_date' => $folio->payer_type === 'operator' ? now()->addDays($folio->operator->payment_terms_days ?? 30) : now(),
            'lines' => $snapshot,
            'currency' => $folio->currency,
        ]);
    }

    /** City-ledger invoice to a tour operator for the folio balance transferred at checkout. */
    public function issueOperatorInvoice(Folio $folio): Invoice
    {
        $invoice = $this->issueFolioInvoice($folio, 'operator_invoice');
        return $invoice;
    }

    public function issueReceipt(Payment $payment): Invoice
    {
        $payment->loadMissing(['booking.guest', 'operator']);
        $name = $payment->operator?->company_name ?? $payment->booking?->guest?->fullName() ?? 'Guest';
        $invoice = $this->store([
            'type' => 'receipt',
            'folio_id' => $payment->folio_id,
            'booking_id' => $payment->booking_id,
            'tour_operator_id' => $payment->tour_operator_id,
            'bill_to_name' => $name,
            'bill_to_details' => $payment->booking ? 'Booking '.$payment->booking->reference : null,
            'subtotal' => (float) $payment->amount,
            'grand_total' => (float) $payment->amount,
            'paid_total' => (float) $payment->amount,
            'balance' => 0,
            'status' => 'paid',
            'lines' => [[
                'date' => $payment->paid_at->toDateString(), 'department' => 'Payment',
                'description' => $payment->methodLabel().' payment '.$payment->reference.($payment->gateway_ref ? ' ('.$payment->gateway_ref.')' : ''),
                'qty' => 1, 'unit_price' => (float) $payment->amount, 'amount' => (float) $payment->amount, 'tax' => 0, 'service' => 0, 'total' => (float) $payment->amount,
            ]],
            'currency' => $payment->currency,
        ]);
        $payment->update(['invoice_id' => $payment->invoice_id ?? $invoice->id]);
        return $invoice;
    }

    /** Pro-forma for a booking before arrival (e.g. operator deposit request). */
    public function issueProforma(Booking $booking): Invoice
    {
        $booking->loadMissing(['guest', 'villas.villa', 'operator']);
        $lines = [];
        foreach ($booking->villas->where('status', 'active') as $bv) {
            $lines[] = ['date' => $bv->arrival->toDateString(), 'department' => 'Room',
                'description' => "{$bv->villa->name} ({$bv->villa->code}) · ".count($bv->nightly_rates).' nights',
                'qty' => count($bv->nightly_rates), 'unit_price' => round((float) $bv->total / max(1, count($bv->nightly_rates)), 2),
                'amount' => (float) $bv->total, 'tax' => 0, 'service' => 0, 'total' => (float) $bv->total];
        }
        if ((float) $booking->discount_total > 0) {
            $lines[] = ['date' => $booking->arrival->toDateString(), 'department' => 'Discount', 'description' => 'Offer discount',
                'qty' => 1, 'unit_price' => -1 * (float) $booking->discount_total, 'amount' => -1 * (float) $booking->discount_total, 'tax' => 0, 'service' => 0, 'total' => -1 * (float) $booking->discount_total];
        }
        $paid = $booking->paidTotal();
        return $this->store([
            'type' => 'proforma',
            'booking_id' => $booking->id,
            'tour_operator_id' => $booking->tour_operator_id,
            'bill_to_name' => $booking->operator?->company_name ?? $booking->guest->fullName(),
            'bill_to_details' => 'Booking '.$booking->reference,
            'subtotal' => (float) $booking->room_total - (float) $booking->discount_total,
            'discount_total' => (float) $booking->discount_total,
            'service_total' => (float) $booking->service_total,
            'tax_total' => (float) $booking->tax_total,
            'grand_total' => (float) $booking->grand_total,
            'paid_total' => $paid,
            'balance' => round((float) $booking->grand_total - $paid, 2),
            'status' => 'issued',
            'due_date' => $booking->arrival->copy()->subDays(7)->max(now()),
            'lines' => $lines,
            'currency' => $booking->currency,
        ]);
    }

    /** Apply a payment to an operator/city-ledger invoice and update its balance/status. */
    public function applyPayment(Invoice $invoice, Payment $payment): Invoice
    {
        $payment->update(['invoice_id' => $invoice->id]);
        $paid = round((float) $invoice->payments()->where('status', 'completed')->sum('amount'), 2);
        $balance = round((float) $invoice->grand_total - $paid, 2);
        $invoice->update(['paid_total' => $paid, 'balance' => max(0, $balance), 'status' => $balance <= 0.009 ? 'paid' : 'partially_paid']);
        AuditService::log('payments', 'invoice_payment', $invoice, "{$payment->reference} applied to {$invoice->number}");
        return $invoice;
    }

    private function store(array $data): Invoice
    {
        $numberType = match ($data['type']) {
            'operator_invoice' => 'operator_invoice',
            'receipt' => 'receipt',
            'proforma' => 'proforma',
            'credit_note' => 'credit_note',
            default => 'invoice',
        };
        $data['number'] = DocumentNumberService::next($numberType);
        $data['issued_at'] = now();
        $data['issued_by'] = auth()->id();
        $data['currency'] ??= config('vaasal.currency');
        $data['hash'] = hash('sha256', json_encode([$data['number'], $data['grand_total'], $data['lines']]));
        $invoice = Invoice::create($data);
        AuditService::log('invoices', 'issued', $invoice, "{$invoice->typeLabel()} {$invoice->number} ".money($invoice->grand_total));
        return $invoice;
    }
}
