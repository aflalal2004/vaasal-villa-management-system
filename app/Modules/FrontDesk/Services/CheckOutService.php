<?php

namespace App\Modules\FrontDesk\Services;

use App\Models\Booking;
use App\Models\Commission;
use App\Models\PosOrder;
use App\Modules\Billing\Services\FolioService;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Booking\Services\AvailabilityService;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Channel\Services\ChannelSyncService;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use App\Modules\Housekeeping\Services\HousekeepingService;
use App\Modules\KeyCards\Services\KeyCardService;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Coordinated, transactional checkout. In ONE database transaction:
 *  1. block if restaurant checks are still open for the villa,
 *  2. post any remaining room nights (early departure releases unused nights),
 *  3. settle each folio (payment, or transfer to tour-operator city ledger),
 *  4. issue the final combined invoice(s) and close folios,
 *  5. revoke all key cards,
 *  6. set villas Vacant + Dirty and create housekeeping departure tasks,
 *  7. mark the booking checked out and accrue commission.
 * If any step fails, nothing is committed.
 */
class CheckOutService
{
    public function __construct(
        private FolioService $folios,
        private InvoiceService $invoices,
        private RoomChargeService $roomCharges,
        private KeyCardService $cards,
        private HousekeepingService $housekeeping,
        private AvailabilityService $availability,
        private BookingService $bookings,
        private ChannelSyncService $channel,
    ) {}

    /** Preview totals shown on the checkout screen (posts nothing). */
    public function preview(Booking $booking): array
    {
        $out = [];
        foreach ($booking->folios as $folio) {
            $out[$folio->id] = $this->folios->summary($folio);
        }
        return $out;
    }

    /**
     * @param array $settlement { payments: [folio_id => ['method'=>, 'amount'=>]], city_ledger: bool }
     * @return array invoices issued
     */
    public function checkOut(Booking $booking, array $settlement = []): array
    {
        if ($booking->status !== 'checked_in') {
            throw new BusinessRuleException('Only in-house bookings can be checked out.');
        }
        $openChecks = PosOrder::where('booking_id', $booking->id)->whereIn('status', ['open', 'billed'])->count();
        if ($openChecks > 0) {
            throw new BusinessRuleException("{$openChecks} restaurant check(s) are still open for this stay. Close or charge them to the villa first.");
        }

        $issued = [];
        $earlyFrom = null;

        DB::transaction(function () use ($booking, $settlement, &$issued, &$earlyFrom) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->first();
            $today = now()->startOfDay();

            // Early departure: release unused nights and shorten the stay.
            if ($booking->departure->gt($today)) {
                $earlyFrom = $booking->departure->copy();
                $newDeparture = $today->copy()->max($booking->arrival->copy()->addDay());
                foreach ($booking->activeVillas as $bv) {
                    $this->availability->release($bv, $newDeparture);
                    $rates = array_filter($bv->nightly_rates, fn ($d) => $d < $newDeparture->toDateString(), ARRAY_FILTER_USE_KEY);
                    $bv->update(['departure' => $newDeparture, 'nightly_rates' => $rates, 'total' => array_sum($rates)]);
                }
                $booking->update(['departure' => $newDeparture]);
                $this->bookings->event($booking, 'early_departure', 'Early departure — stay shortened to '.fmt_date($newDeparture));
            }

            $this->roomCharges->postNights($booking, $booking->departure);

            foreach ($booking->folios()->where('status', 'open')->get() as $folio) {
                $pay = $settlement['payments'][$folio->id] ?? null;
                if ($pay && (float) ($pay['amount'] ?? 0) > 0) {
                    $payment = $this->folios->recordPayment($folio, $pay['method'] ?? 'cash', (float) $pay['amount'], 'payment', 'Checkout settlement');
                    $this->invoices->issueReceipt($payment);
                }

                $balance = $folio->balance();
                if ($folio->payer_type === 'operator' && $balance > 0.009) {
                    // Transfer to tour operator city ledger: operator is invoiced on credit terms.
                    $invoice = $this->invoices->issueOperatorInvoice($folio);
                    // Folio is settled by the transfer; the operator invoice keeps the receivable until paid.
                    $this->folios->recordPayment($folio, 'city_ledger', $balance, 'payment', 'Transferred to city ledger '.$invoice->number);
                    $issued[] = $invoice;
                } else {
                    if ($balance > 0.009) {
                        throw new BusinessRuleException("Folio {$folio->folio_no} has an outstanding balance of ".money($balance).'. Take payment before checkout.');
                    }
                    if ($balance < -0.009) {
                        throw new BusinessRuleException("Folio {$folio->folio_no} is in credit by ".money(abs($balance)).'. Record a refund before checkout.');
                    }
                    if ($folio->lines()->exists()) {
                        $issued[] = $this->invoices->issueFolioInvoice($folio);
                    }
                }
                $this->folios->close($folio);
            }

            $this->cards->revokeForBooking($booking, 'Checked out');

            foreach ($booking->activeVillas()->with('villa')->get() as $bv) {
                $this->housekeeping->onCheckout($bv->villa, $booking);
            }

            $booking->update(['status' => 'checked_out', 'checked_out_at' => now()]);

            if ((float) $booking->commission_pct > 0) {
                Commission::updateOrCreate(['booking_id' => $booking->id], [
                    'tour_operator_id' => $booking->tour_operator_id,
                    'channel_id' => $booking->channel_id,
                    'basis_amount' => (float) $booking->room_total - (float) $booking->discount_total,
                    'pct' => $booking->commission_pct,
                    'amount' => round(((float) $booking->room_total - (float) $booking->discount_total) * (float) $booking->commission_pct / 100, 2),
                    'status' => 'accrued',
                ]);
            }

            $this->bookings->event($booking, 'checked_out', 'Guest checked out; cards revoked; villa set to Dirty');
            AuditService::log('frontdesk', 'check_out', $booking, "Checked out {$booking->reference}");
        });

        if ($earlyFrom) {
            $this->channel->queueAri(now(), $earlyFrom);
        }
        NotificationService::notify('booking.checked_out', "Checked out: {$booking->reference}", $booking->guest->fullName(),
            route('admin.bookings.show', $booking), 'info', 'frontdesk.checkout');

        return $issued;
    }
}
