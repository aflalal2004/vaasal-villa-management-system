<?php

namespace App\Modules\Channel\Services;

use App\Models\Booking;
use App\Models\BookingConflict;
use App\Models\Channel;
use App\Models\ChannelMapping;
use App\Models\ChannelSyncLog;
use App\Models\VillaType;
use App\Models\WebhookInbox;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Inbound OTA reservations from the channel manager.
 * - Idempotent: duplicate webhook events are ignored (webhook_inbox unique provider+event_id),
 *   and a booking is unique per (channel, external_ref).
 * - Never silently drops a reservation: if inventory is not available the reservation is put
 *   in the conflict queue and managers are alerted immediately.
 */
class OtaReservationService
{
    public function __construct(private BookingService $bookings) {}

    /** @return array{status:string, message:string, booking_id:?int, conflict_id:?int} */
    public function ingest(array $r, string $provider = 'channel'): array
    {
        $r += ['action' => 'new', 'channel_code' => 'booking_com', 'room_code' => null, 'rate_code' => null, 'adults' => 2, 'children' => 0,
            'amount' => 0, 'guest' => [], 'notes' => null];
        $r['event_id'] ??= $r['external_ref'].'-'.$r['action'].'-'.md5(json_encode($r));

        $inbox = WebhookInbox::firstOrCreate(
            ['provider' => $provider, 'event_id' => $r['event_id']],
            ['event_type' => 'reservation.'.$r['action'], 'payload' => $r]
        );
        if ($inbox->processed_at) {
            return ['status' => 'duplicate', 'message' => 'Event already processed.', 'booking_id' => null, 'conflict_id' => null];
        }

        $channel = Channel::where('code', $r['channel_code'])->first() ?? Channel::where('code', 'booking_com')->firstOrFail();
        $existing = Booking::where('channel_id', $channel->id)->where('external_ref', $r['external_ref'])->first();

        try {
            $result = match ($r['action']) {
                'cancelled' => $this->cancel($existing, $r),
                'modified' => $existing ? $this->modify($existing, $r) : $this->create($channel, $r),
                default => $existing
                    ? ['status' => 'duplicate', 'message' => 'Booking already exists: '.$existing->reference, 'booking_id' => $existing->id, 'conflict_id' => null]
                    : $this->create($channel, $r),
            };
            $inbox->update(['processed_at' => now(), 'error' => null]);
        } catch (\Throwable $e) {
            $inbox->update(['error' => mb_substr($e->getMessage(), 0, 500)]);
            throw $e;
        }

        ChannelSyncLog::create(['direction' => 'in', 'type' => 'reservation', 'status' => 'processed', 'date_from' => $r['arrival'],
            'date_to' => $r['departure'], 'payload' => $r, 'response' => $result['message'], 'processed_at' => now(), 'attempts' => 1]);

        return $result;
    }

    private function create(Channel $channel, array $r): array
    {
        $type = $this->resolveType($channel, $r);
        try {
            if (! $type) {
                throw new BusinessRuleException('No villa type is mapped to OTA room code "'.($r['room_code'] ?? '?').'".');
            }
            $booking = $this->bookings->create([
                'source' => 'ota',
                'channel_code' => $channel->code,
                'external_ref' => $r['external_ref'],
                'guest' => $r['guest'] + ['first_name' => 'OTA', 'last_name' => 'Guest'],
                'arrival' => $r['arrival'],
                'departure' => $r['departure'],
                'villas' => [['villa_type_id' => $type->id, 'adults' => $r['adults'], 'children' => $r['children']]],
                'status' => 'confirmed',
                'payment_mode' => 'pay_at_property',
                'special_requests' => $r['notes'],
                'ignore_rules' => true,
            ]);
            if ($r['amount'] > 0 && abs($r['amount'] - (float) $booking->grand_total) > 1) {
                $this->bookings->event($booking, 'ota_amount', 'OTA amount '.money($r['amount']).' differs from PMS price '.money($booking->grand_total).'. Review rate parity.');
            }
            return ['status' => 'created', 'message' => 'Created '.$booking->reference, 'booking_id' => $booking->id, 'conflict_id' => null];
        } catch (BusinessRuleException $e) {
            $conflict = DB::transaction(fn () => BookingConflict::create([
                'channel_id' => $channel->id,
                'external_ref' => $r['external_ref'],
                'guest_name' => trim(($r['guest']['first_name'] ?? '').' '.($r['guest']['last_name'] ?? '')) ?: 'OTA Guest',
                'guest_email' => $r['guest']['email'] ?? null,
                'villa_type_id' => $type?->id,
                'arrival' => $r['arrival'],
                'departure' => $r['departure'],
                'amount' => $r['amount'],
                'reason' => $e->getMessage(),
                'payload' => $r,
            ]));
            NotificationService::bookingConflict($conflict->load('channel'));
            return ['status' => 'conflict', 'message' => 'Placed in conflict queue: '.$e->getMessage(), 'booking_id' => null, 'conflict_id' => $conflict->id];
        }
    }

    private function modify(Booking $booking, array $r): array
    {
        try {
            $this->bookings->changeDates($booking, $r['arrival'], $r['departure'], 'OTA modification');
            return ['status' => 'modified', 'message' => 'Modified '.$booking->reference, 'booking_id' => $booking->id, 'conflict_id' => null];
        } catch (BusinessRuleException $e) {
            $conflict = BookingConflict::create([
                'channel_id' => $booking->channel_id, 'external_ref' => $r['external_ref'], 'guest_name' => $booking->guest->fullName(),
                'guest_email' => $booking->guest->email, 'villa_type_id' => $booking->villas->first()?->villa_type_id, 'booking_id' => $booking->id,
                'arrival' => $r['arrival'], 'departure' => $r['departure'], 'amount' => $r['amount'],
                'reason' => 'Modification could not be applied: '.$e->getMessage(), 'payload' => $r,
            ]);
            NotificationService::bookingConflict($conflict->load('channel'));
            return ['status' => 'conflict', 'message' => 'Modification placed in conflict queue', 'booking_id' => $booking->id, 'conflict_id' => $conflict->id];
        }
    }

    private function cancel(?Booking $booking, array $r): array
    {
        if (! $booking) {
            return ['status' => 'ignored', 'message' => 'Cancellation for unknown booking '.$r['external_ref'], 'booking_id' => null, 'conflict_id' => null];
        }
        if ($booking->status === 'cancelled') {
            return ['status' => 'duplicate', 'message' => 'Already cancelled', 'booking_id' => $booking->id, 'conflict_id' => null];
        }
        $this->bookings->cancel($booking, 'Cancelled by OTA', 0);
        return ['status' => 'cancelled', 'message' => 'Cancelled '.$booking->reference, 'booking_id' => $booking->id, 'conflict_id' => null];
    }

    private function resolveType(Channel $channel, array $r): ?VillaType
    {
        if (! empty($r['room_code'])) {
            // Mappings are defined per channel; fall back to any mapping of the code (channel-manager room ids are global).
            $map = ChannelMapping::where('external_room_code', $r['room_code'])->where('channel_id', $channel->id)->first()
                ?? ChannelMapping::where('external_room_code', $r['room_code'])->first();
            if ($map) return $map->villaType;
        }
        return null;
    }
}
