<?php

namespace App\Modules\Booking\Services;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingVilla;
use App\Models\Channel;
use App\Models\Commission;
use App\Models\Guest;
use App\Models\Offer;
use App\Models\OperatorContract;
use App\Models\Property;
use App\Models\RatePlan;
use App\Models\TourOperator;
use App\Models\Villa;
use App\Modules\Billing\Services\FolioService;
use App\Modules\Booking\Exceptions\InventoryConflictException;
use App\Modules\Channel\Services\ChannelSyncService;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Villa\Services\PricingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single entry point for creating and changing reservations from every source.
 * All inventory writes happen inside one DB transaction through AvailabilityService.
 */
class BookingService
{
    public function __construct(
        private AvailabilityService $availability,
        private PricingService $pricing,
        private FolioService $folios,
        private ChannelSyncService $channelSync,
    ) {}

    /**
     * @param array $data {
     *   source, channel_code?, guest: Guest|array, arrival, departure,
     *   villas: [ ['villa_id'=>?, 'villa_type_id'=>?, 'adults'=>, 'children'=>] ],
     *   rate_plan_id?, promo_code?|offer_id?, tour_operator_id?, status? (hold|tentative|confirmed),
     *   payment_mode?, external_ref?, special_requests?, arrival_time?, group_name?, rate_override?,
     *   hold_minutes?, stay_guests?: [bv_index => [ [first_name,last_name,...] ]]
     * }
     */
    public function create(array $data): Booking
    {
        $arrival = Carbon::parse($data['arrival'])->startOfDay();
        $departure = Carbon::parse($data['departure'])->startOfDay();
        if ($departure->lte($arrival)) {
            throw new BusinessRuleException('Departure must be at least one night after arrival.');
        }
        if (empty($data['villas'])) {
            throw new BusinessRuleException('Select at least one villa.');
        }

        $source = $data['source'];
        $channel = Channel::byCode($data['channel_code'] ?? ($source === 'ota' ? 'booking_com' : $source));
        $plan = ! empty($data['rate_plan_id']) ? RatePlan::find($data['rate_plan_id']) : RatePlan::where('is_active', true)->orderBy('id')->first();
        $offer = ! empty($data['offer_id']) ? Offer::find($data['offer_id']) : $this->pricing->findPromo($data['promo_code'] ?? null);
        if (! empty($data['promo_code']) && ! $offer) {
            throw new BusinessRuleException('The promo code "'.$data['promo_code'].'" is not valid.');
        }
        $operator = ! empty($data['tour_operator_id']) ? TourOperator::findOrFail($data['tour_operator_id']) : null;
        if ($operator && $operator->status !== 'active') {
            throw new BusinessRuleException('Tour operator '.$operator->company_name.' is not active.');
        }
        $contract = $operator ? ($operator->activeContract($arrival->toDateString())?->load('rates')) : null;
        $status = $data['status'] ?? 'confirmed';

        $booking = DB::transaction(function () use ($data, $arrival, $departure, $source, $channel, $plan, $offer, $operator, $contract, $status) {
            $guest = $data['guest'] instanceof Guest ? $data['guest'] : $this->resolveGuest($data['guest']);
            if ($guest->is_blacklisted && $source !== 'ota') {
                throw new BusinessRuleException('This guest is blacklisted. A manager must review before booking.');
            }

            $booking = Booking::create([
                'property_id' => Property::current()->id,
                'reference' => DocumentNumberService::next('booking'),
                'channel_id' => $channel->id,
                'source' => $source,
                'external_ref' => $data['external_ref'] ?? null,
                'guest_id' => $guest->id,
                'tour_operator_id' => $operator?->id,
                'operator_contract_id' => $contract?->id,
                'group_name' => $data['group_name'] ?? null,
                'status' => $status,
                'arrival' => $arrival,
                'departure' => $departure,
                'adults' => 0,
                'children' => 0,
                'rate_plan_id' => $plan?->id,
                'offer_id' => $offer?->id,
                'currency' => config('vaasal.currency'),
                'special_requests' => $data['special_requests'] ?? null,
                'arrival_time' => $data['arrival_time'] ?? null,
                'payment_mode' => $data['payment_mode'] ?? ($operator ? 'credit' : 'deposit'),
                'hold_expires_at' => $status === 'hold' ? now()->addMinutes($data['hold_minutes'] ?? config('vaasal.hold_minutes')) : null,
                'confirmed_at' => $status === 'confirmed' ? now() : null,
                'manage_token' => Str::random(48),
                'created_by' => auth()->id(),
            ]);

            $totals = ['room_total' => 0, 'discount' => 0, 'service' => 0, 'tax' => 0, 'grand_total' => 0, 'deposit_due' => 0];
            $usedVillaIds = [];
            foreach (array_values($data['villas']) as $i => $line) {
                $villa = $this->pickVilla($line, $arrival, $departure, $usedVillaIds);
                $usedVillaIds[] = $villa->id;
                $adults = (int) ($line['adults'] ?? 2);
                $children = (int) ($line['children'] ?? 0);
                $quote = $this->pricing->quote($villa->type, $arrival, $departure, $adults, $children, $plan, $offer, $contract, $villa);
                if (! $quote['ok'] && $source !== 'ota' && empty($data['ignore_rules'])) {
                    throw new BusinessRuleException(implode(' ', $quote['errors']));
                }
                if (isset($data['rate_override']) && $data['rate_override'] !== null && $data['rate_override'] !== '') {
                    $quote = $this->applyOverride($quote, (float) $data['rate_override']);
                }

                $bv = BookingVilla::create([
                    'booking_id' => $booking->id,
                    'villa_id' => $villa->id,
                    'villa_type_id' => $villa->villa_type_id,
                    'arrival' => $arrival,
                    'departure' => $departure,
                    'adults' => $adults,
                    'children' => $children,
                    'nightly_rates' => $quote['nightly'],
                    'total' => $quote['room_total'],
                ]);
                $bv->setRelation('villa', $villa);
                $this->availability->reserve($bv);

                foreach ($data['stay_guests'][$i] ?? [] as $sg) {
                    if (! empty($sg['first_name'])) {
                        $bv->guests()->create($sg);
                    }
                }
                foreach ($totals as $k => $_) {
                    $totals[$k] += $quote[$k];
                }
                $booking->adults += $adults;
                $booking->children += $children;
            }

            $commissionPct = $contract ? (float) $contract->commission_pct : (float) $channel->commission_pct;
            $booking->fill([
                'room_total' => $totals['room_total'],
                'discount_total' => $totals['discount'],
                'service_total' => $totals['service'],
                'tax_total' => $totals['tax'],
                'grand_total' => $totals['grand_total'],
                'deposit_due' => $totals['deposit_due'],
                'commission_pct' => $commissionPct,
                'commission_amount' => round(($totals['room_total'] - $totals['discount']) * $commissionPct / 100, 2),
            ])->save();

            if ($offer) {
                $offer->increment('used_count');
            }

            // Guest folio for extras; operator bookings get a separate operator folio for room charges.
            $this->folios->open($booking, 'guest');
            if ($operator) {
                $this->folios->open($booking, 'operator');
            }

            $this->event($booking, 'created', 'Booking created via '.$booking->sourceLabel().' ('.$booking->statusLabel().')');
            AuditService::log('booking', 'created', $booking, "Booking {$booking->reference} created ({$source})");

            return $booking;
        });

        $booking->load(['guest', 'villas.villa', 'channel']);
        if ($status !== 'hold') {
            NotificationService::bookingCreated($booking);
        }
        $this->channelSync->queueAri($arrival, $departure);

        return $booking;
    }

    public function confirm(Booking $booking, ?string $note = null): Booking
    {
        if (! in_array($booking->status, ['hold', 'tentative'], true)) {
            return $booking;
        }
        $booking->update(['status' => 'confirmed', 'confirmed_at' => now(), 'hold_expires_at' => null]);
        $this->event($booking, 'confirmed', $note ?? 'Booking confirmed');
        NotificationService::bookingCreated($booking->fresh(['guest']));
        return $booking;
    }

    public function cancel(Booking $booking, string $reason, ?float $fee = null): Booking
    {
        if (! in_array($booking->status, ['hold', 'tentative', 'confirmed'], true)) {
            throw new BusinessRuleException('Only bookings that have not checked in can be cancelled.');
        }
        $fee ??= $this->cancellationFee($booking);

        DB::transaction(function () use ($booking, $reason, $fee) {
            foreach ($booking->activeVillas as $bv) {
                $this->availability->release($bv);
                $bv->update(['status' => 'cancelled']);
            }
            $booking->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason, 'cancellation_fee' => $fee, 'hold_expires_at' => null]);
            if ($fee > 0 && ($folio = $booking->folios()->where('payer_type', $booking->tour_operator_id ? 'operator' : 'guest')->first())) {
                $this->folios->post($folio, 'room', 'Cancellation fee', 1, $fee, applyTax: false);
            }
            Commission::where('booking_id', $booking->id)->update(['status' => 'cancelled']);
            $this->event($booking, 'cancelled', "Cancelled: {$reason}".($fee > 0 ? ' · fee '.money($fee) : ''));
            AuditService::log('booking', 'cancelled', $booking, $reason);
        });

        NotificationService::notify('booking.cancelled', "Booking {$booking->reference} cancelled", $reason, route('admin.bookings.show', $booking), 'warning', 'bookings.view');
        $this->channelSync->queueAri($booking->arrival, $booking->departure);
        return $booking->fresh();
    }

    /** Cancellation penalty per the booking's rate plan policy. */
    public function cancellationFee(Booking $booking): float
    {
        $plan = $booking->ratePlan;
        if (! $plan) return 0.0;
        if (! $plan->is_refundable) return (float) $booking->grand_total;
        $daysBefore = now()->startOfDay()->diffInDays($booking->arrival, false);
        if ($daysBefore >= $plan->free_cancel_days) return 0.0;
        return round((float) $booking->grand_total * (float) $plan->cancel_penalty_pct / 100, 2);
    }

    /** Move dates for all villas on the booking; re-prices and re-reserves atomically. */
    public function changeDates(Booking $booking, string $arrival, string $departure, ?string $reason = null): Booking
    {
        if (! in_array($booking->status, ['hold', 'tentative', 'confirmed', 'checked_in'], true)) {
            throw new BusinessRuleException('This booking can no longer be modified.');
        }
        $arrivalC = Carbon::parse($arrival)->startOfDay();
        $departureC = Carbon::parse($departure)->startOfDay();
        if ($departureC->lte($arrivalC)) {
            throw new BusinessRuleException('Departure must be after arrival.');
        }
        if ($booking->status === 'checked_in' && ! $arrivalC->equalTo($booking->arrival)) {
            throw new BusinessRuleException('Arrival date cannot change after check-in. Change the departure date instead.');
        }
        $old = ['arrival' => $booking->arrival->toDateString(), 'departure' => $booking->departure->toDateString()];

        DB::transaction(function () use ($booking, $arrivalC, $departureC, $reason, $old) {
            $totals = ['room_total' => 0, 'discount' => 0, 'service' => 0, 'tax' => 0, 'grand_total' => 0, 'deposit_due' => 0];
            foreach ($booking->activeVillas()->with('villa.type')->get() as $bv) {
                $this->availability->release($bv);
                $bv->update(['arrival' => $arrivalC, 'departure' => $departureC]);
                $quote = $this->pricing->quote($bv->villa->type, $arrivalC, $departureC, $bv->adults, $bv->children,
                    $booking->ratePlan, $booking->offer, $booking->contract?->load('rates'), $bv->villa);
                $bv->update(['nightly_rates' => $quote['nightly'], 'total' => $quote['room_total']]);
                $this->availability->reserve($bv);
                foreach ($totals as $k => $_) {
                    $totals[$k] += $quote[$k];
                }
            }
            $booking->update([
                'arrival' => $arrivalC, 'departure' => $departureC,
                'room_total' => $totals['room_total'], 'discount_total' => $totals['discount'], 'service_total' => $totals['service'],
                'tax_total' => $totals['tax'], 'grand_total' => $totals['grand_total'], 'deposit_due' => $totals['deposit_due'],
                'commission_amount' => round(($totals['room_total'] - $totals['discount']) * (float) $booking->commission_pct / 100, 2),
                'version' => $booking->version + 1,
            ]);
            $this->event($booking, 'dates_changed', 'Dates changed '.$old['arrival'].'→'.$old['departure'].' to '.$arrivalC->toDateString().'→'.$departureC->toDateString().($reason ? " ({$reason})" : ''), $old);
            AuditService::log('booking', 'dates_changed', $booking, $reason, $old, ['arrival' => $arrivalC->toDateString(), 'departure' => $departureC->toDateString()]);
        });

        $this->channelSync->queueAri(min($old['arrival'], $arrivalC->toDateString()), max($old['departure'], $departureC->toDateString()));
        return $booking->fresh();
    }

    /** Move a booking line to another villa (room move / re-assignment). */
    public function changeVilla(BookingVilla $bv, Villa $newVilla, ?string $reason = null): BookingVilla
    {
        $booking = $bv->booking;
        $oldVilla = $bv->villa;
        if ($newVilla->id === $oldVilla->id) return $bv;

        DB::transaction(function () use ($bv, $newVilla, $oldVilla, $booking, $reason) {
            $from = $booking->status === 'checked_in' ? now()->startOfDay() : $bv->arrival;
            $this->availability->release($bv, $from);
            if (! $this->availability->isVillaFree($newVilla->id, $from, $bv->departure, $bv->id)) {
                throw new InventoryConflictException("Villa {$newVilla->code} is not free for the remaining nights.");
            }

            $rows = [];
            foreach ($this->availability->nights($from, $bv->departure) as $d) {
                $rows[] = ['villa_id' => $newVilla->id, 'stay_date' => $d, 'booking_villa_id' => $bv->id, 'created_at' => now()];
            }
            // Earlier nights (already stayed) keep pointing at the old villa row for history.
            try {
                \App\Models\InventoryNight::insert($rows);
            } catch (\Illuminate\Database\QueryException) {
                throw new InventoryConflictException("Villa {$newVilla->code} is not free for the remaining nights.");
            }
            $bv->update(['villa_id' => $newVilla->id, 'villa_type_id' => $newVilla->villa_type_id]);

            if ($booking->status === 'checked_in') {
                $oldVilla->update(['occupancy_status' => 'vacant', 'hk_status' => 'dirty']);
                $newVilla->update(['occupancy_status' => 'occupied']);
                app(\App\Modules\Housekeeping\Services\HousekeepingService::class)->createTask($oldVilla, 'departure', now(), $booking, 'high', notes: 'Room move — guest moved to '.$newVilla->code);
            }
            $this->event($booking, 'villa_changed', "Moved from {$oldVilla->code} to {$newVilla->code}".($reason ? " ({$reason})" : ''));
            AuditService::log('booking', 'villa_changed', $booking, "{$oldVilla->code} → {$newVilla->code}");
        });

        return $bv->fresh('villa');
    }

    /** Release unpaid website/portal holds whose TTL has passed. */
    public function expireHolds(): int
    {
        $count = 0;
        Booking::where('status', 'hold')->where('hold_expires_at', '<', now())->each(function (Booking $b) use (&$count) {
            DB::transaction(function () use ($b) {
                foreach ($b->activeVillas as $bv) {
                    $this->availability->release($bv);
                    $bv->update(['status' => 'cancelled']);
                }
                $b->update(['status' => 'expired']);
                $this->event($b, 'expired', 'Hold expired without payment; inventory released');
            });
            $this->channelSync->queueAri($b->arrival, $b->departure);
            $count++;
        });
        return $count;
    }

    /**
     * Bring an expired hold back to 'hold' by re-reserving its villas (e.g. a payment arrived after expiry).
     * Returns false — leaving everything untouched — when any night has since been sold.
     */
    public function reinstate(Booking $booking): bool
    {
        if ($booking->status !== 'expired' || $booking->arrival->lt(today())) {
            return false;
        }
        try {
            DB::transaction(function () use ($booking) {
                foreach ($booking->villas()->with('villa')->where('status', 'cancelled')->get() as $bv) {
                    $this->availability->reserve($bv);
                    $bv->update(['status' => 'active']);
                }
                $booking->update(['status' => 'hold']);
                $this->event($booking, 'reinstated', 'Expired hold reinstated; villas re-reserved');
            });
        } catch (InventoryConflictException) {
            return false;
        }
        $this->channelSync->queueAri($booking->arrival, $booking->departure);
        return true;
    }

    public function markNoShow(Booking $booking): Booking
    {
        if ($booking->status !== 'confirmed' || $booking->arrival->isFuture()) {
            throw new BusinessRuleException('Only confirmed bookings whose arrival date has passed can be marked no-show.');
        }
        $fee = $this->cancellationFee($booking);
        DB::transaction(function () use ($booking, $fee) {
            foreach ($booking->activeVillas as $bv) {
                $this->availability->release($bv, now()->startOfDay());
            }
            $booking->update(['status' => 'no_show', 'cancellation_fee' => $fee]);
            if ($fee > 0 && ($folio = $booking->folios()->first())) {
                $this->folios->post($folio, 'room', 'No-show fee', 1, $fee, applyTax: false);
            }
            $this->event($booking, 'no_show', 'Marked as no-show'.($fee > 0 ? ' · fee '.money($fee) : ''));
        });
        $this->channelSync->queueAri(now(), $booking->departure);
        return $booking->fresh();
    }

    public function event(Booking $booking, string $event, ?string $description = null, ?array $data = null): void
    {
        BookingEvent::create(['booking_id' => $booking->id, 'event' => $event, 'description' => $description, 'data' => $data, 'user_id' => auth()->id()]);
    }

    public function resolveGuest(array $g): Guest
    {
        $guest = null;
        if (! empty($g['id'])) {
            $guest = Guest::find($g['id']);
        }
        if (! $guest && ! empty($g['email'])) {
            $guest = Guest::where('email', $g['email'])->first();
        }
        $fields = array_filter(array_intersect_key($g, array_flip(['title', 'first_name', 'last_name', 'email', 'phone', 'country', 'nationality', 'address', 'marketing_consent'])),
            fn ($v) => $v !== null && $v !== '');
        if ($guest) {
            $guest->fill($fields)->save();
            return $guest;
        }
        if (empty($fields['first_name']) || empty($fields['last_name'])) {
            throw new BusinessRuleException('Guest first and last name are required.');
        }
        return Guest::create($fields);
    }

    private function pickVilla(array $line, Carbon $arrival, Carbon $departure, array $exclude): Villa
    {
        if (! empty($line['villa_id'])) {
            $villa = Villa::with('type')->findOrFail($line['villa_id']);
            if (! $villa->isSellable()) {
                throw new BusinessRuleException("Villa {$villa->code} is out of order and cannot be booked.");
            }
            return $villa;
        }
        $villa = $this->availability->availableVillas($arrival, $departure, $line['villa_type_id'] ?? null)
            ->reject(fn ($v) => in_array($v->id, $exclude, true))->first();
        if (! $villa) {
            throw new InventoryConflictException('No villa of the requested type is available for these dates.');
        }
        return $villa;
    }

    private function applyOverride(array $quote, float $nightlyRate): array
    {
        $quote['nightly'] = array_map(fn () => round($nightlyRate, 2), $quote['nightly']);
        $room = round($nightlyRate * $quote['nights'], 2);
        $p = Property::current();
        $service = round(($room - $quote['discount']) * (float) $p->service_charge_pct / 100, 2);
        $tax = round(($room - $quote['discount'] + $service) * (float) $p->tax_pct / 100, 2);
        $grand = round($room - $quote['discount'] + $service + $tax, 2);
        return array_merge($quote, ['room_total' => $room, 'service' => $service, 'tax' => $tax, 'grand_total' => $grand,
            'deposit_due' => round($grand * $quote['deposit_pct'] / 100, 2)]);
    }
}

