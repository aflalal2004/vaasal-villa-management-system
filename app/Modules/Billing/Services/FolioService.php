<?php

namespace App\Modules\Billing\Services;

use App\Models\Booking;
use App\Models\ChargeItem;
use App\Models\Folio;
use App\Models\FolioLine;
use App\Models\Payment;
use App\Models\Property;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\DocumentNumberService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The guest stay account. Every department (room, restaurant, bar, laundry, spa, transport,
 * airport transfer, minibar, …) posts through this one service, so checkout produces a
 * single combined invoice. Lines and payments are insert-only; corrections are reversals.
 */
class FolioService
{
    public function open(Booking $booking, string $payerType = 'guest'): Folio
    {
        $existing = $booking->folios()->where('payer_type', $payerType)->where('status', 'open')->first();
        if ($existing) return $existing;

        return Folio::create([
            'booking_id' => $booking->id,
            'folio_no' => DocumentNumberService::next('folio'),
            'payer_type' => $payerType,
            'guest_id' => $payerType === 'guest' ? $booking->guest_id : null,
            'tour_operator_id' => $payerType === 'operator' ? $booking->tour_operator_id : null,
            'currency' => $booking->currency,
        ]);
    }

    /** Folio that receives charges of a given department (room → operator folio for operator bookings). */
    public function folioFor(Booking $booking, string $department = 'misc'): Folio
    {
        if ($department === 'room' && $booking->tour_operator_id) {
            return $this->open($booking, 'operator');
        }
        return $this->open($booking, 'guest');
    }

    /** Post a charge; tax and service are calculated from property settings. */
    public function post(Folio $folio, string $department, string $description, float $quantity, float $unitPrice,
        ?ChargeItem $item = null, ?string $sourceType = null, ?int $sourceId = null, bool $applyTax = true,
        bool $applyService = false, Carbon|string|null $businessDate = null): FolioLine
    {
        $property = Property::current();
        $amount = round($quantity * $unitPrice, 2);
        $service = $applyService ? round($amount * (float) $property->service_charge_pct / 100, 2) : 0.0;
        $tax = $applyTax ? round(($amount + $service) * (float) $property->tax_pct / 100, 2) : 0.0;

        return $this->postComputed($folio, $department, $description, $quantity, $unitPrice, $amount, $tax, $service,
            $item, $sourceType, $sourceId, $businessDate);
    }

    /** Post with amounts already calculated by the source module (e.g. POS invoice totals). */
    public function postComputed(Folio $folio, string $department, string $description, float $quantity, float $unitPrice,
        float $amount, float $tax, float $service, ?ChargeItem $item = null, ?string $sourceType = null, ?int $sourceId = null,
        Carbon|string|null $businessDate = null): FolioLine
    {
        $folio = Folio::whereKey($folio->id)->lockForUpdate()->first();
        if (! $folio->isOpen()) {
            throw new BusinessRuleException("Folio {$folio->folio_no} is closed. Charges can no longer be posted.");
        }
        if (! array_key_exists($department, config('vaasal.departments'))) {
            throw new BusinessRuleException("Unknown department \"{$department}\".");
        }

        $line = FolioLine::create([
            'folio_id' => $folio->id,
            'business_date' => $businessDate ? Carbon::parse($businessDate)->toDateString() : now()->toDateString(),
            'department' => $department,
            'charge_item_id' => $item?->id,
            'description' => mb_substr($description, 0, 255),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => round($amount, 2),
            'tax_amount' => round($tax, 2),
            'service_amount' => round($service, 2),
            'total' => round($amount + $tax + $service, 2),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'posted_by' => auth()->id(),
        ]);
        AuditService::log('folio', 'charge_posted', $line, "{$folio->folio_no}: {$description} ".money($line->total));
        return $line;
    }

    /** Reverse a line by posting its exact negative; the original is flagged, never edited. */
    public function reverse(FolioLine $line, string $reason): FolioLine
    {
        if ($line->is_reversed || $line->reverses_id) {
            throw new BusinessRuleException('This line has already been reversed or is itself a reversal.');
        }
        return DB::transaction(function () use ($line, $reason) {
            $rev = $this->postComputed($line->folio, $line->department, 'Reversal: '.$line->description.' — '.$reason,
                -1 * (float) $line->quantity, (float) $line->unit_price, -1 * (float) $line->amount, -1 * (float) $line->tax_amount,
                -1 * (float) $line->service_amount, null, $line->source_type, $line->source_id);
            $rev->update(['reverses_id' => $line->id]);
            $line->update(['is_reversed' => true]);
            AuditService::log('folio', 'charge_reversed', $line, $reason);
            return $rev;
        });
    }

    public function recordPayment(Folio $folio, string $method, float $amount, string $type = 'payment', ?string $notes = null,
        ?string $gateway = null, ?string $gatewayRef = null, ?string $proofPath = null): Payment
    {
        if ($amount <= 0) {
            throw new BusinessRuleException('Payment amount must be greater than zero.');
        }
        if (! $folio->isOpen() && $type !== 'refund') {
            throw new BusinessRuleException("Folio {$folio->folio_no} is closed.");
        }
        $payment = Payment::create([
            'reference' => DocumentNumberService::next('payment'),
            'folio_id' => $folio->id,
            'booking_id' => $folio->booking_id,
            'tour_operator_id' => $folio->tour_operator_id,
            'type' => $type,
            'method' => $method,
            'amount' => round($amount, 2),
            'currency' => $folio->currency,
            'gateway' => $gateway,
            'gateway_ref' => $gatewayRef,
            'status' => 'completed',
            'proof_path' => $proofPath,
            'notes' => $notes,
            'received_by' => auth()->id(),
            'paid_at' => now(),
        ]);
        AuditService::log('payments', 'payment_recorded', $payment, "{$payment->reference} {$method} ".money($amount)." on {$folio->folio_no}");
        return $payment;
    }

    public function refund(Folio $folio, string $method, float $amount, string $reason): Payment
    {
        if ($amount <= 0) {
            throw new BusinessRuleException('Refund amount must be greater than zero.');
        }
        if ($amount > $folio->paymentsTotal() + 0.001) {
            throw new BusinessRuleException('Refund cannot exceed the total paid on this folio ('.money($folio->paymentsTotal()).').');
        }
        $payment = Payment::create([
            'reference' => DocumentNumberService::next('payment'),
            'folio_id' => $folio->id,
            'booking_id' => $folio->booking_id,
            'tour_operator_id' => $folio->tour_operator_id,
            'type' => 'refund',
            'method' => $method,
            'amount' => -1 * round($amount, 2),
            'currency' => $folio->currency,
            'status' => 'completed',
            'notes' => $reason,
            'received_by' => auth()->id(),
            'paid_at' => now(),
        ]);
        AuditService::log('payments', 'refund', $payment, $reason);
        return $payment;
    }

    /** Totals by department for the combined invoice. */
    public function summary(Folio $folio): array
    {
        $lines = $folio->lines()->get();
        $byDept = $lines->groupBy('department')->map(fn ($g) => round($g->sum('total'), 2));
        $charges = round($lines->sum('total'), 2);
        $paid = $folio->paymentsTotal();
        return [
            'by_department' => $byDept,
            'net' => round($lines->sum('amount'), 2),
            'tax' => round($lines->sum('tax_amount'), 2),
            'service' => round($lines->sum('service_amount'), 2),
            'charges' => $charges,
            'paid' => $paid,
            'balance' => round($charges - $paid, 2),
        ];
    }

    public function close(Folio $folio): void
    {
        $folio->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => auth()->id()]);
    }
}
