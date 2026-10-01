<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\HkTask;
use App\Models\KeyCard;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\Villa;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\FrontDesk\Services\CheckInService;
use App\Modules\FrontDesk\Services\CheckOutService;
use App\Modules\POS\Services\PosBillingService;
use App\Modules\POS\Services\PosOrderService;
use App\Modules\POS\Services\PosShiftService;
use Tests\TestCase;

/**
 * REQ FD-*, POS folio integration, SC-*, KC-06, HK-02:
 * check-in → restaurant charge to villa → coordinated checkout (combined invoice, card revoked, villa Dirty, HK task).
 */
class StayLifecycleTest extends TestCase
{
    private function arriveToday(string $villaCode): Booking
    {
        $villa = Villa::where('code', $villaCode)->first();
        $villa->update(['hk_status' => 'ready', 'occupancy_status' => 'vacant']);
        return app(BookingService::class)->create([
            'source' => 'walk_in', 'guest' => ['first_name' => 'Ana', 'last_name' => 'Lopez', 'email' => uniqid().'@example.test'],
            'arrival' => now()->toDateString(), 'departure' => now()->addDay()->toDateString(), 'villas' => [['villa_id' => $villa->id, 'adults' => 2]], 'ignore_rules' => true,
        ]);
    }

    public function test_full_stay_with_restaurant_charge_produces_one_combined_invoice(): void
    {
        $this->as('reception@vaasalvilla.test');
        // Use a villa that is free tonight
        $villa = app(\App\Modules\Booking\Services\AvailabilityService::class)->availableVillas(now(), now()->addDay())->first();
        $booking = $this->arriveToday($villa->code);
        $card = KeyCard::where('status', 'available')->where('type', 'guest')->first();

        app(CheckInService::class)->checkIn($booking, ['id_type' => 'passport', 'id_number' => 'X1234567', 'card_uids' => [$booking->activeVillas->first()->id => $card->uid]]);
        $booking->refresh();
        $this->assertSame('checked_in', $booking->status);
        $this->assertSame('occupied', $villa->fresh()->occupancy_status);
        $this->assertSame('active', $card->fresh()->status);

        // Restaurant check charged to the villa
        $cashier = $this->as('cashier@vaasalvilla.test');
        $outlet = Outlet::where('code', 'REST')->first();
        if (! app(PosShiftService::class)->current($cashier, $outlet->id)) app(PosShiftService::class)->open($outlet, $cashier, 10000);
        $orders = app(PosOrderService::class);
        $order = $orders->open($outlet, ['type' => 'takeaway']);
        $orders->addItem($order, MenuItem::where('code', 'DR3')->first(), 2);
        $orders->fire($order);
        $order = app(PosBillingService::class)->pay($order, [['method' => 'room_charge', 'amount' => (float) $order->fresh()->total, 'booking_id' => $booking->id]]);
        $this->assertSame('charged_to_room', $order->status);
        $this->assertTrue($booking->folios()->first()->lines()->where('department', 'restaurant')->exists());

        // Checkout: settle and verify every coordinated effect
        $this->as('reception@vaasalvilla.test');
        app(\App\Modules\FrontDesk\Services\RoomChargeService::class)->postNights($booking, $booking->departure);
        $folio = $booking->folios()->where('payer_type', 'guest')->first();
        $invoices = app(CheckOutService::class)->checkOut($booking->fresh(), ['payments' => [$folio->id => ['method' => 'card', 'amount' => $folio->balance()]]]);

        $booking->refresh();
        $this->assertSame('checked_out', $booking->status);
        $invoice = collect($invoices)->firstWhere('type', 'invoice');
        $depts = collect($invoice->lines)->pluck('department')->unique();
        $this->assertTrue($depts->contains('Room') && $depts->contains('Restaurant'), 'Invoice must combine room and restaurant');
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('closed', $folio->fresh()->status);
        $this->assertSame('available', $card->fresh()->status, 'Card returned to stock after revocation');
        $this->assertSame('revoked', $booking->cardAssignments()->first()->status);
        $v = $villa->fresh();
        $this->assertSame(['vacant', 'dirty'], [$v->occupancy_status, $v->hk_status]);
        // One open departure task per villa per day (an existing one is reused, not duplicated)
        $this->assertTrue(HkTask::where('villa_id', $v->id)->where('type', 'departure')->where('scheduled_date', now()->toDateString())->whereIn('status', ['pending', 'in_progress'])->exists());
    }

    public function test_checkout_is_blocked_while_a_restaurant_check_is_open(): void
    {
        $this->as('reception@vaasalvilla.test');
        $booking = Booking::where('status', 'checked_in')->first();
        $order = app(PosOrderService::class)->open(Outlet::where('code', 'IRD')->first(), ['type' => 'room_service', 'booking_id' => $booking->id]);
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/restaurant check/');
        app(CheckOutService::class)->checkOut($booking);
    }

    public function test_checkout_with_unpaid_balance_rolls_back_everything(): void
    {
        $this->as('reception@vaasalvilla.test');
        $booking = Booking::where('status', 'checked_in')->whereNull('tour_operator_id')->whereDoesntHave('folios', fn ($q) => $q->where('payer_type', 'operator'))->first();
        \App\Models\PosOrder::where('booking_id', $booking->id)->whereIn('status', ['open', 'billed'])->update(['status' => 'void']);
        // Make sure something is owed (e.g. a minibar charge not yet paid)
        app(\App\Modules\Billing\Services\FolioService::class)->post($booking->folios()->where('payer_type', 'guest')->first(), 'minibar', 'Minibar', 1, 500000);
        try {
            app(CheckOutService::class)->checkOut($booking);
            $this->fail('Checkout must require settlement');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('outstanding balance', $e->getMessage());
        }
        $this->assertSame('checked_in', $booking->fresh()->status);
        $this->assertTrue($booking->cardAssignments()->where('status', 'active')->exists(), 'Cards stay active when checkout fails');
    }

    public function test_check_in_requires_a_ready_villa(): void
    {
        $this->as('reception@vaasalvilla.test');
        $villa = app(\App\Modules\Booking\Services\AvailabilityService::class)->availableVillas(now(), now()->addDay())->first();
        $booking = $this->arriveToday($villa->code);
        Villa::whereKey($villa->id)->update(['hk_status' => 'dirty']);
        $this->expectException(BusinessRuleException::class);
        app(CheckInService::class)->checkIn($booking, ['id_type' => 'passport', 'id_number' => 'P999']);
    }

    public function test_operator_stay_transfers_room_balance_to_city_ledger(): void
    {
        $this->as('reception@vaasalvilla.test');
        $booking = Booking::where('status', 'checked_in')->whereNotNull('tour_operator_id')->first();
        $this->assertNotNull($booking, 'Seed data has an operator stay in-house');
        \App\Models\PosOrder::where('booking_id', $booking->id)->whereIn('status', ['open', 'billed'])->update(['status' => 'void']);
        $booking->update(['departure' => now()->toDateString()]);
        foreach ($booking->activeVillas as $bv) { $bv->update(['departure' => now()->toDateString()]); }
        app(\App\Modules\FrontDesk\Services\RoomChargeService::class)->postNights($booking->fresh(), now());
        $pay = [];
        foreach ($booking->folios()->where('payer_type', 'guest')->get() as $f) { if ($f->balance() > 0) $pay[$f->id] = ['method' => 'cash', 'amount' => $f->balance()]; }
        $invoices = app(CheckOutService::class)->checkOut($booking->fresh(), ['payments' => $pay]);
        $opInvoice = collect($invoices)->firstWhere('type', 'operator_invoice');
        $this->assertNotNull($opInvoice);
        $this->assertGreaterThan(0, (float) $opInvoice->balance);
        $this->assertSame($booking->tour_operator_id, $opInvoice->tour_operator_id);
        $this->assertTrue(\App\Models\Commission::where('booking_id', $booking->id)->exists());
    }
}
