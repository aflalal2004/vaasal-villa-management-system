<?php

namespace App\Modules\POS\Services;

use App\Models\Booking;
use App\Models\CashMovement;
use App\Models\FolioLine;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Modules\Billing\Services\FolioService;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * Cashier billing: bill print, split tender (cash / card / online / charge to villa),
 * refunds. "Charge to villa" posts the check to the in-house guest's folio so it appears
 * on the single combined checkout invoice.
 */
class PosBillingService
{
    public function __construct(
        private FolioService $folios,
        private PosShiftService $shifts,
        private InventoryService $inventory,
        private PosOrderService $orders,
    ) {}

    public function printBill(PosOrder $order): PosOrder
    {
        if (! $order->isEditable()) throw new BusinessRuleException('This check is already closed.');
        if ((float) $order->total <= 0) throw new BusinessRuleException('The check is empty.');
        $order->update(['status' => 'billed', 'billed_at' => now()]);
        return $order;
    }

    /**
     * @param array $tenders [ ['method'=>cash|card|bank_transfer|digital|online|room_charge, 'amount'=>, 'tendered'=>?, 'reference'=>?, 'booking_id'=>?] ]
     */
    public function pay(PosOrder $order, array $tenders): PosOrder
    {
        $closed = false;
        DB::transaction(function () use ($order, $tenders, &$closed) {
            $order = PosOrder::whereKey($order->id)->lockForUpdate()->first();
            if (! $order->isEditable()) throw new BusinessRuleException('This check is already closed.');
            $this->orders->recalc($order);
            if ($order->items()->where('status', 'pending')->exists()) {
                throw new BusinessRuleException('Send or remove unsent items before taking payment.');
            }
            if ((float) $order->total <= 0 || ! $order->liveItems()->exists()) {
                throw new BusinessRuleException('This check is empty. Add items before taking payment.');
            }
            $shift = $this->shifts->forPayment(auth()->user(), $order->outlet_id);
            $balance = $order->balance();
            $sum = round(array_sum(array_map(fn ($t) => (float) $t['amount'], $tenders)), 2);
            if ($sum <= 0) throw new BusinessRuleException('Enter a payment amount.');
            if ($sum - $balance > 0.009) throw new BusinessRuleException('Payments ('.money($sum).') exceed the balance due ('.money($balance).').');

            foreach ($tenders as $t) {
                $method = $t['method'];
                $amount = round((float) $t['amount'], 2);
                if ($amount <= 0) continue;
                if (! array_key_exists($method, PosPayment::METHODS)) throw new BusinessRuleException('Unknown payment method.');
                if (in_array($method, PosPayment::SHIFT_METHODS, true) && ! $shift) {
                    throw new BusinessRuleException('You have no open cashier shift. Open one under Shifts & drawer before taking '.strtolower(PosPayment::label($method)).' payments.');
                }

                $folioLineId = null;
                if ($method === 'room_charge') {
                    $folioLineId = $this->chargeToVilla($order, $amount, (int) ($t['booking_id'] ?? $order->booking_id))->id;
                }
                $tendered = $method === 'cash' ? max($amount, (float) ($t['tendered'] ?? $amount)) : null;
                PosPayment::create([
                    'pos_order_id' => $order->id, 'pos_shift_id' => $shift?->id, 'type' => 'payment', 'method' => $method, 'amount' => $amount,
                    'tendered' => $tendered, 'change_due' => $tendered ? round($tendered - $amount, 2) : null,
                    'reference' => $t['reference'] ?? null, 'folio_line_id' => $folioLineId, 'received_by' => auth()->id(),
                ]);
                if ($method === 'cash') {
                    CashMovement::create(['pos_shift_id' => $shift->id, 'type' => 'sale', 'amount' => $amount, 'reason' => $order->order_no,
                        'pos_order_id' => $order->id, 'user_id' => auth()->id()]);
                }
            }

            $paid = round((float) $order->payments()->sum('amount'), 2);
            $updates = ['paid_amount' => $paid, 'cashier_id' => auth()->id(), 'pos_shift_id' => $shift?->id ?? $order->pos_shift_id];
            if ($paid >= (float) $order->total - 0.009) {
                $onlyRoom = $order->payments()->where('method', '!=', 'room_charge')->doesntExist();
                $updates += ['status' => $onlyRoom ? 'charged_to_room' : 'paid', 'closed_at' => now(), 'invoice_no' => DocumentNumberService::next('pos_invoice')];
                $closed = true;
            } else {
                $updates['status'] = 'billed';
            }
            $order->update($updates);
            AuditService::log('pos', 'payment', $order, $order->order_no.' paid '.money($sum));
        });

        $order = $order->fresh();
        if ($closed) {
            $order->kots()->whereIn('status', ['ready'])->update(['status' => 'served', 'served_at' => now()]);
            $this->inventory->depleteForOrder($order);
        }
        return $order;
    }

    public function refund(PosOrder $order, float $amount, string $method, string $reason): PosOrder
    {
        if (! in_array($order->status, ['paid', 'charged_to_room'], true)) throw new BusinessRuleException('Only closed checks can be refunded.');
        $refundable = round((float) $order->paid_amount - (float) $order->refunded_amount, 2);
        if ($amount <= 0 || $amount - $refundable > 0.009) throw new BusinessRuleException('Refund must be between 0 and '.money($refundable).'.');

        DB::transaction(function () use ($order, $amount, $method, $reason) {
            $shift = $this->shifts->forPayment(auth()->user(), $order->outlet_id);
            $folioLineId = null;
            if ($method === 'room_charge') {
                $line = FolioLine::whereIn('id', $order->payments()->whereNotNull('folio_line_id')->pluck('folio_line_id'))->first();
                if (! $line) throw new BusinessRuleException('This check was not charged to a villa.');
                if (! $line->folio->isOpen()) throw new BusinessRuleException('The guest has checked out; refund by another method.');
                $factor = $amount / (float) $line->total;
                $rev = $this->folios->postComputed($line->folio, $line->department, "Refund {$order->invoice_no}: {$reason}", 1, -$amount,
                    -round((float) $line->amount * $factor, 2), -round((float) $line->tax_amount * $factor, 2), -round((float) $line->service_amount * $factor, 2),
                    null, 'pos_order', $order->id);
                $folioLineId = $rev->id;
            } elseif ($method === 'cash') {
                if (! $shift) throw new BusinessRuleException('Open a cashier shift to refund cash.');
                CashMovement::create(['pos_shift_id' => $shift->id, 'type' => 'refund', 'amount' => -$amount, 'reason' => $order->invoice_no.' '.$reason,
                    'pos_order_id' => $order->id, 'user_id' => auth()->id()]);
            }
            PosPayment::create(['pos_order_id' => $order->id, 'pos_shift_id' => $shift?->id, 'type' => 'refund', 'method' => $method, 'amount' => -$amount,
                'folio_line_id' => $folioLineId, 'reason' => $reason, 'received_by' => auth()->id()]);
            $refunded = round((float) $order->refunded_amount + $amount, 2);
            $order->update(['refunded_amount' => $refunded, 'status' => $refunded >= (float) $order->paid_amount - 0.009 ? 'refunded' : $order->status]);
            AuditService::log('pos', 'refund', $order, money($amount).' '.$method.' — '.$reason);
        });
        return $order->fresh();
    }

    private function chargeToVilla(PosOrder $order, float $amount, int $bookingId): FolioLine
    {
        $booking = Booking::with(['guest', 'activeVillas.villa'])->find($bookingId);
        if (! $booking || $booking->status !== 'checked_in') {
            throw new BusinessRuleException('Charge to villa is only possible for in-house guests.');
        }
        if ($booking->guest->is_blacklisted) {
            throw new BusinessRuleException('Charging is not allowed for this guest. Take another payment method.');
        }
        $folio = $this->folios->open($booking, 'guest');
        $outlet = $order->outlet;
        // Allocate tax/service proportionally for partial room charges
        $factor = (float) $order->total > 0 ? $amount / (float) $order->total : 1;
        $net = round(((float) $order->subtotal - (float) $order->discount_amount) * $factor, 2);
        $tax = round((float) $order->tax_amount * $factor, 2);
        $service = round($amount - $net - $tax, 2);
        $villa = $booking->activeVillas->first()?->villa;
        $order->update(['booking_id' => $booking->id, 'villa_id' => $order->villa_id ?? $villa?->id, 'guest_name' => $order->guest_name ?? $booking->guest->fullName()]);

        return $this->folios->postComputed($folio, $outlet->folio_department, "{$outlet->name} check {$order->order_no}".($order->table ? ' · table '.$order->table->name : ''),
            1, $amount, $net, $tax, $service, null, 'pos_order', $order->id);
    }
}
