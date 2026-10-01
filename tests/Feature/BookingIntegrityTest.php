<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\InventoryNight;
use App\Models\Villa;
use App\Modules\Booking\Exceptions\InventoryConflictException;
use App\Modules\Billing\Services\OnlinePaymentService;
use App\Modules\Booking\Services\BookingService;
use Tests\TestCase;

/** REQ BK-02/BK-03/BK-05: one availability ledger, database-enforced double-booking prevention. */
class BookingIntegrityTest extends TestCase
{
    private function book(Villa $villa, string $arrival, string $departure, array $extra = []): Booking
    {
        return app(BookingService::class)->create($extra + [
            'source' => 'phone', 'guest' => ['first_name' => 'Test', 'last_name' => 'Guest', 'email' => uniqid().'@example.test'],
            'arrival' => $arrival, 'departure' => $departure, 'villas' => [['villa_id' => $villa->id, 'adults' => 2]], 'ignore_rules' => true,
        ]);
    }

    public function test_overlapping_booking_for_same_villa_is_rejected_by_the_ledger(): void
    {
        $villa = Villa::where('code', 'P2')->first();
        $a = now()->addDays(120)->toDateString();
        $this->book($villa, $a, now()->addDays(124)->toDateString());

        $this->expectException(InventoryConflictException::class);
        $this->book($villa, now()->addDays(122)->toDateString(), now()->addDays(125)->toDateString());
    }

    public function test_failed_booking_leaves_no_partial_rows(): void
    {
        $villa = Villa::where('code', 'P2')->first();
        $this->book($villa, now()->addDays(130)->toDateString(), now()->addDays(133)->toDateString());
        $bookingsBefore = Booking::count();
        $nightsBefore = InventoryNight::count();
        try {
            $this->book($villa, now()->addDays(128)->toDateString(), now()->addDays(131)->toDateString());
        } catch (InventoryConflictException) {
        }
        $this->assertSame($bookingsBefore, Booking::count(), 'Booking row must roll back');
        $this->assertSame($nightsBefore, InventoryNight::count(), 'Inventory rows must roll back');
    }

    public function test_back_to_back_stays_are_allowed(): void
    {
        $villa = Villa::where('code', 'G1')->first();
        $this->book($villa, now()->addDays(140)->toDateString(), now()->addDays(143)->toDateString());
        $b = $this->book($villa, now()->addDays(143)->toDateString(), now()->addDays(145)->toDateString());
        $this->assertSame('confirmed', $b->status);
    }

    public function test_cancel_releases_inventory_to_every_channel(): void
    {
        $villa = Villa::where('code', 'O1')->first();
        $b = $this->book($villa, now()->addDays(150)->toDateString(), now()->addDays(152)->toDateString());
        $this->assertSame(2, InventoryNight::where('villa_id', $villa->id)->whereBetween('stay_date', [now()->addDays(150)->toDateString(), now()->addDays(151)->toDateString()])->count());
        app(BookingService::class)->cancel($b, 'Test', 0);
        $this->assertSame(0, InventoryNight::whereHas('bookingVilla', fn ($q) => $q->where('booking_id', $b->id))->count());
        $this->book($villa, now()->addDays(150)->toDateString(), now()->addDays(152)->toDateString()); // re-sellable
    }

    public function test_expired_website_hold_releases_the_villa(): void
    {
        $villa = Villa::where('code', 'F1')->first();
        $hold = $this->book($villa, now()->addDays(160)->toDateString(), now()->addDays(162)->toDateString(), ['status' => 'hold', 'hold_minutes' => -1]);
        $this->assertSame('hold', $hold->status);
        app(BookingService::class)->expireHolds();
        $this->assertSame('expired', $hold->fresh()->status);
        $this->book($villa, now()->addDays(160)->toDateString(), now()->addDays(162)->toDateString());
    }

    public function test_out_of_order_villa_is_not_offered_or_bookable(): void
    {
        $villa = Villa::where('code', 'P3')->first();
        $villa->update(['maintenance_status' => 'out_of_order']);
        $free = app(\App\Modules\Booking\Services\AvailabilityService::class)->availableVillas(now()->addDays(170), now()->addDays(172));
        $this->assertFalse($free->contains('id', $villa->id));
        $this->expectException(\App\Modules\Core\Exceptions\BusinessRuleException::class);
        $this->book($villa, now()->addDays(170)->toDateString(), now()->addDays(172)->toDateString());
    }

    public function test_date_change_reprices_and_moves_inventory(): void
    {
        $villa = Villa::where('code', 'G2')->first();
        $b = $this->book($villa, now()->addDays(180)->toDateString(), now()->addDays(182)->toDateString());
        $total = (float) $b->grand_total;
        $b = app(BookingService::class)->changeDates($b, now()->addDays(180)->toDateString(), now()->addDays(184)->toDateString());
        $this->assertGreaterThan($total, (float) $b->grand_total);
        $this->assertSame(4, InventoryNight::whereHas('bookingVilla', fn ($q) => $q->where('booking_id', $b->id))->count());
    }

    public function test_late_payment_on_expired_hold_reinstates_booking_when_villa_still_free(): void
    {
        $villa = Villa::where('code', 'F1')->first();
        $hold = $this->book($villa, now()->addDays(200)->toDateString(), now()->addDays(203)->toDateString(), ['status' => 'hold', 'hold_minutes' => -1]);
        app(BookingService::class)->expireHolds();
        $payments = app(OnlinePaymentService::class);
        $payments->complete($payments->createIntent($hold->fresh(), 10000));

        $this->assertSame('confirmed', $hold->fresh()->status);
        $this->assertSame(3, InventoryNight::whereHas('bookingVilla', fn ($q) => $q->where('booking_id', $hold->id))->count());
    }

    public function test_late_payment_on_resold_villa_keeps_booking_expired_and_alerts_staff(): void
    {
        $villa = Villa::where('code', 'F1')->first();
        $hold = $this->book($villa, now()->addDays(210)->toDateString(), now()->addDays(212)->toDateString(), ['status' => 'hold', 'hold_minutes' => -1]);
        app(BookingService::class)->expireHolds();
        $this->book($villa, now()->addDays(211)->toDateString(), now()->addDays(213)->toDateString()); // night resold meanwhile
        $payments = app(OnlinePaymentService::class);
        $intent = $payments->complete($payments->createIntent($hold->fresh(), 10000));

        $this->assertSame('paid', $intent->status);
        $this->assertSame('expired', $hold->fresh()->status);
        $this->assertSame(0, InventoryNight::whereHas('bookingVilla', fn ($q) => $q->where('booking_id', $hold->id))->count());
        $this->assertTrue(AppNotification::where('type', 'payment.late')->exists());
    }
}
