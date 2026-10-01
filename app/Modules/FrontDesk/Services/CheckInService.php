<?php

namespace App\Modules\FrontDesk\Services;

use App\Models\Booking;
use App\Models\BookingVilla;
use App\Models\Villa;
use App\Modules\Billing\Services\FolioService;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\UploadService;
use App\Modules\KeyCards\Services\KeyCardService;
use Illuminate\Support\Facades\DB;

/**
 * Arrival registration: verify identity, capture ID/passport, confirm villa assignment
 * (must be Ready), record deposit, mark in-house, then issue key cards.
 */
class CheckInService
{
    public function __construct(
        private FolioService $folios,
        private InvoiceService $invoices,
        private BookingService $bookings,
        private KeyCardService $cards,
    ) {}

    /**
     * @param array $data {
     *   id_type, id_number, id_expiry?, nationality?, id_document (UploadedFile)?,
     *   villa_assignments?: [booking_villa_id => villa_id], allow_not_ready?: bool,
     *   deposit_amount?, deposit_method?, card_uids?: [booking_villa_id => uid], stay_guests?: [...]
     * }
     */
    public function checkIn(Booking $booking, array $data): Booking
    {
        if (! in_array($booking->status, ['confirmed', 'tentative'], true)) {
            throw new BusinessRuleException('Only confirmed bookings can be checked in (current status: '.$booking->statusLabel().').');
        }
        if ($booking->arrival->isAfter(now()->startOfDay())) {
            throw new BusinessRuleException('This booking arrives on '.fmt_date($booking->arrival).'. Change the arrival date to check in early.');
        }
        if ($booking->departure->lte(now()->startOfDay())) {
            throw new BusinessRuleException('The departure date has already passed. Update the stay dates first.');
        }
        if ($booking->guest->is_blacklisted) {
            throw new BusinessRuleException('Guest is blacklisted. Manager approval required.');
        }
        if (empty($data['id_number']) && empty($booking->guest->id_number)) {
            throw new BusinessRuleException('Guest ID or passport number is required for registration.');
        }

        DB::transaction(function () use ($booking, $data) {
            // Room re-assignments requested at the desk
            foreach ($data['villa_assignments'] ?? [] as $bvId => $villaId) {
                $bv = $booking->activeVillas()->findOrFail($bvId);
                if ((int) $villaId && (int) $villaId !== $bv->villa_id) {
                    $this->bookings->changeVilla($bv, Villa::findOrFail($villaId), 'Assigned at check-in');
                }
            }

            foreach ($booking->activeVillas()->with('villa')->get() as $bv) {
                $villa = $bv->villa;
                if ($villa->occupancy_status === 'occupied') {
                    throw new BusinessRuleException("Villa {$villa->code} is still occupied.");
                }
                if ($villa->maintenance_status === 'out_of_order') {
                    throw new BusinessRuleException("Villa {$villa->code} is out of order. Move the guest to another villa.");
                }
                if ($villa->hk_status !== 'ready' && empty($data['allow_not_ready'])) {
                    throw new BusinessRuleException("Villa {$villa->code} is not ready (housekeeping: ".Villa::HK_LABELS[$villa->hk_status].').');
                }
            }

            // Guest identity
            $guest = $booking->guest;
            $guest->fill(array_filter([
                'id_type' => $data['id_type'] ?? null,
                'id_number' => $data['id_number'] ?? null,
                'id_expiry' => $data['id_expiry'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ]));
            if (! empty($data['id_document'])) {
                $guest->id_document_path = UploadService::privateDocument($data['id_document'], 'guest-ids');
            }
            $guest->save();

            foreach ($data['stay_guests'] ?? [] as $bvId => $rows) {
                $bv = BookingVilla::where('booking_id', $booking->id)->findOrFail($bvId);
                foreach ($rows as $row) {
                    if (! empty($row['first_name'])) {
                        $bv->guests()->create($row);
                    }
                }
            }

            $booking->update(['status' => 'checked_in', 'checked_in_at' => now()]);
            foreach ($booking->activeVillas()->with('villa')->get() as $bv) {
                $bv->villa->update(['occupancy_status' => 'occupied']);
            }
            $folio = $this->folios->open($booking, 'guest');

            if (! empty($data['deposit_amount']) && (float) $data['deposit_amount'] > 0) {
                $payment = $this->folios->recordPayment($folio, $data['deposit_method'] ?? 'cash', (float) $data['deposit_amount'], 'deposit', 'Deposit at check-in');
                $this->invoices->issueReceipt($payment);
            }

            $this->bookings->event($booking, 'checked_in', 'Guest checked in');
            AuditService::log('frontdesk', 'check_in', $booking, "Checked in {$booking->reference}");
        });

        // Key cards (after commit so an encoder failure never undoes the registration)
        foreach ($data['card_uids'] ?? [] as $bvId => $uid) {
            if (trim((string) $uid) !== '') {
                $bv = BookingVilla::where('booking_id', $booking->id)->findOrFail($bvId);
                $this->cards->issueForBooking($bv, $uid, 'new');
            }
        }

        return $booking->fresh();
    }
}
