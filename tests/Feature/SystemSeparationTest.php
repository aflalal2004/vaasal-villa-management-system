<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\HkTask;
use App\Models\KeyCard;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PosOrder;
use App\Models\PosTable;
use App\Models\TableReservation;
use App\Modules\Booking\Services\AvailabilityService;
use App\Modules\Booking\Services\BookingService;
use App\Modules\FrontDesk\Services\CheckInService;
use App\Modules\POS\Services\PosDashboardService;
use Tests\TestCase;

/**
 * Hotel PMS and Restaurant POS are separate systems joined only at the integration points:
 * POS room charge → hotel folio, checkout ↔ open restaurant checks, checkout → housekeeping.
 */
class SystemSeparationTest extends TestCase
{
    public function test_hotel_staff_cannot_use_the_pos_and_restaurant_staff_cannot_use_the_pms(): void
    {
        // Hotel manager: full PMS, no restaurant POS
        $this->as('manager@vaasalvilla.test');
        foreach (['admin.dashboard', 'admin.bookings.index', 'admin.frontdesk.index', 'admin.housekeeping.index', 'admin.invoices.index'] as $r) {
            $this->get(route($r))->assertOk();
        }
        foreach (['pos.dashboard', 'pos.terminal', 'pos.orders.index', 'pos.reservations.index', 'pos.sales', 'pos.menu.index'] as $r) {
            $this->get(route($r))->assertForbidden();
        }
        $this->get(route('admin.dashboard'))->assertDontSee('Restaurant sales')->assertDontSee('POS cashier status');

        // Restaurant manager: full POS, no hotel PMS (no folio posting, bookings, front desk)
        $this->as('posmanager@vaasalvilla.test');
        foreach (['pos.dashboard', 'pos.terminal', 'pos.orders.index', 'pos.reservations.index', 'pos.sales', 'pos.menu.index', 'pos.today', 'pos.inventory.items.index'] as $r) {
            $this->get(route($r))->assertOk();
        }
        foreach (['admin.dashboard', 'admin.bookings.index', 'admin.frontdesk.index', 'admin.charge-items.index', 'admin.invoices.index', 'admin.guests.index'] as $r) {
            $this->get(route($r))->assertForbidden();
        }
        $this->get(route('pos.dashboard'))->assertDontSee('Hotel PMS');

        // Reception and housekeeping: no POS at all
        foreach (['reception@vaasalvilla.test', 'housekeeping@vaasalvilla.test'] as $email) {
            $this->as($email);
            $this->get(route('pos.dashboard'))->assertForbidden();
            $this->get(route('pos.terminal'))->assertForbidden();
        }

        // Administrator spans both systems and can switch between them
        $this->as('admin@vaasalvilla.test');
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Restaurant POS')->assertSee('Restaurant sales');
        $this->get(route('pos.dashboard'))->assertOk()->assertSee('Hotel PMS');
        $this->post('/logout');
        $this->post('/login', ['login' => 'posmanager', 'password' => 'Vaasal@2026'])->assertRedirect(route('pos.dashboard'));
    }

    public function test_room_charge_from_pos_reaches_the_folio_and_is_settled_at_checkout(): void
    {
        // PMS: reception checks a guest in
        $this->as('reception@vaasalvilla.test');
        $villa = app(AvailabilityService::class)->availableVillas(now(), now()->addDays(2))->first();
        $villa->update(['hk_status' => 'ready', 'occupancy_status' => 'vacant']);
        $booking = app(BookingService::class)->create([
            'source' => 'walk_in', 'guest' => ['first_name' => 'Aflal', 'last_name' => 'Guest', 'email' => uniqid().'@example.test'],
            'arrival' => now()->toDateString(), 'departure' => now()->addDays(2)->toDateString(), 'villas' => [['villa_id' => $villa->id, 'adults' => 2]], 'ignore_rules' => true,
        ]);
        $card = KeyCard::where('status', 'available')->where('type', 'guest')->first();
        app(CheckInService::class)->checkIn($booking, ['id_type' => 'passport', 'id_number' => 'N1234567', 'card_uids' => [$booking->activeVillas->first()->id => $card->uid]]);
        HkTask::where('villa_id', $villa->id)->whereIn('status', HkTask::OPEN)->update(['status' => 'cancelled']);

        // POS: cashier opens a room-service check over the terminal API, sends it to the kitchen and charges it to the villa
        $this->as('cashier@vaasalvilla.test');
        $outlet = Outlet::where('code', 'IRD')->first() ?? Outlet::first();
        $this->getJson(route('pos.api.in-house'))->assertOk()->assertJsonFragment(['booking_id' => $booking->id]);
        $order = $this->postJson(route('pos.api.orders.open'), ['outlet_id' => $outlet->id, 'type' => 'room_service', 'booking_id' => $booking->id])->assertOk()->json();
        $dish = MenuItem::where('code', 'MN3')->first();
        // Required modifiers (e.g. spice level) are enforced by the POS; pick the first option of each required group.
        $mods = $dish->modifierGroups()->with('modifiers')->get()->filter(fn ($g) => $g->min_select > 0)->map(fn ($g) => $g->modifiers->first()->id)->values()->all();
        $this->postJson(route('pos.api.orders.items.add', $order['id']), ['menu_item_id' => $dish->id, 'qty' => 2, 'modifiers' => $mods])->assertOk();
        $this->postJson(route('pos.api.orders.fire', $order['id']))->assertOk();
        $total = (float) PosOrder::find($order['id'])->total;
        $this->assertGreaterThan(0, $total);

        // A second, still-open check blocks checkout until the restaurant settles it
        $open = $this->postJson(route('pos.api.orders.open'), ['outlet_id' => $outlet->id, 'type' => 'room_service', 'booking_id' => $booking->id])->assertOk()->json();
        $this->postJson(route('pos.api.orders.items.add', $open['id']), ['menu_item_id' => MenuItem::where('code', 'DR1')->first()->id])->assertOk();
        $this->postJson(route('pos.api.orders.fire', $open['id']))->assertOk();

        $this->postJson(route('pos.api.orders.pay', $order['id']), ['tenders' => [['method' => 'room_charge', 'amount' => $total, 'booking_id' => $booking->id]]])
            ->assertOk()->assertJsonPath('status', 'charged_to_room');

        // PMS: the charge is on the guest folio; reception sees the open check and asks the restaurant to settle it
        $this->as('reception@vaasalvilla.test');
        $folio = $booking->folios()->where('payer_type', 'guest')->first();
        $this->assertTrue($folio->lines()->where('department', $outlet->folio_department)->where('source_id', $order['id'])->exists(), 'room charge posted to the hotel folio');
        $this->get(route('admin.frontdesk.checkout', $booking))->assertOk()->assertSee($open['order_no'])->assertSee('Request restaurant settlement');
        $this->post(route('admin.frontdesk.checkout.store', $booking), ['confirm_early' => 1])->assertSessionHas('error');
        $this->assertSame('checked_in', $booking->fresh()->status, 'checkout blocked while a restaurant check is open');
        $this->post(route('admin.frontdesk.restaurant-settlement', $booking))->assertSessionHas('success');

        $cashier = $this->as('cashier@vaasalvilla.test');
        $this->assertTrue(AppNotification::visibleTo($cashier)->where('type', 'pos.settlement_request')->where('url', route('pos.order', $open['id']))->exists());
        $openTotal = (float) PosOrder::find($open['id'])->total;
        $this->postJson(route('pos.api.orders.pay', $open['id']), ['tenders' => [['method' => 'room_charge', 'amount' => $openTotal, 'booking_id' => $booking->id]]])
            ->assertOk()->assertJsonPath('status', 'charged_to_room');

        // PMS: settle the combined folio and check out
        $this->as('reception@vaasalvilla.test');
        $this->get(route('admin.frontdesk.checkout', $booking))->assertOk()->assertDontSee('Request restaurant settlement');
        app(\App\Modules\FrontDesk\Services\RoomChargeService::class)->postNights($booking->fresh(), now()->toDateString());
        $balance = $folio->fresh()->balance();
        $this->assertGreaterThanOrEqual($total + $openTotal, $balance);
        $this->post(route('admin.frontdesk.checkout.store', $booking), ['confirm_early' => 1, 'payments' => [$folio->id => ['method' => 'card', 'amount' => $balance]]])
            ->assertRedirect(route('admin.bookings.show', $booking));

        $booking->refresh();
        $this->assertSame('checked_out', $booking->status);
        $invoice = $booking->invoices()->where('type', 'invoice')->latest('id')->first();
        $this->assertNotNull($invoice);
        $this->assertTrue(collect($invoice->lines)->pluck('department')->contains(fn ($d) => str_contains(strtolower($d), 'room service') || str_contains(strtolower($d), 'restaurant')),
            'final invoice combines restaurant/room-service charges with the room');
        $this->assertSame('dirty', $villa->fresh()->hk_status);
        $this->assertTrue(HkTask::where('villa_id', $villa->id)->where('type', 'departure')->where('status', 'pending')->exists());
    }

    public function test_pos_dashboard_figures_come_from_the_database(): void
    {
        $this->as('posmanager@vaasalvilla.test');
        $d = app(PosDashboardService::class)->build();
        $this->assertEquals(round((float) PosOrder::whereIn('status', ['paid', 'charged_to_room', 'refunded'])->where('closed_at', '>=', today())->sum('total'), 2), $d['sales_today']);
        $this->assertSame(PosOrder::whereIn('status', ['open', 'billed'])->count(), $d['open_checks']);
        $this->assertSame(PosTable::where('is_active', true)->count(), $d['tables_total']);
        $this->assertCount(7, $d['week']);
        $this->get(route('pos.dashboard'))->assertOk()->assertSee('Quick Actions')->assertSee('Sales — Last 7 Days')->assertSee('Popular Dishes')
            ->assertSee('Recent Orders')->assertSee('Upcoming Reservations')->assertSee('Low Stock Alerts');
    }

    public function test_table_reservations_prevent_double_booking_and_seat_into_a_check(): void
    {
        $this->as('posmanager@vaasalvilla.test');
        $outlet = Outlet::where('code', 'REST')->first();
        $table = $outlet->tables()->whereDoesntHave('openOrder')->first();
        TableReservation::where('pos_table_id', $table->id)->delete();
        $at = now()->addHours(2)->startOfHour();
        $payload = ['outlet_id' => $outlet->id, 'pos_table_id' => $table->id, 'guest_name' => 'Ms. Silva', 'party_size' => 2, 'reserved_for' => $at->format('Y-m-d\TH:i'), 'duration_minutes' => 90];

        $this->post(route('pos.reservations.store'), $payload)->assertSessionHas('success');
        $this->post(route('pos.reservations.store'), ['guest_name' => 'Mr. Clash', 'reserved_for' => $at->copy()->addMinutes(30)->format('Y-m-d\TH:i')] + $payload)->assertSessionHas('error');
        $this->post(route('pos.reservations.store'), ['guest_name' => 'Mr. Later', 'reserved_for' => $at->copy()->addMinutes(90)->format('Y-m-d\TH:i')] + $payload)->assertSessionHas('success');

        $r = TableReservation::where('guest_name', 'Ms. Silva')->firstOrFail();
        $this->assertSame('confirmed', $r->status);
        $this->getJson(route('pos.api.tables', ['outlet' => $outlet->id]))->assertOk()->assertJsonFragment(['guest' => 'Ms. Silva']);

        $res = $this->post(route('pos.reservations.status', $r), ['status' => 'seated']);
        $r->refresh();
        $this->assertSame('seated', $r->status);
        $res->assertRedirect(route('pos.order', $r->pos_order_id));
        $this->assertSame($table->id, $r->order->pos_table_id);
        $this->post(route('pos.reservations.status', $r), ['status' => 'cancelled'])->assertSessionHas('error');

        $r->order->update(['status' => 'paid', 'closed_at' => now()]);
        $this->get(route('pos.reservations.index', ['date' => $at->toDateString()]))->assertOk()->assertSee('Ms. Silva');
        $this->assertSame('completed', $r->fresh()->status);

        $this->as('reception@vaasalvilla.test');
        $this->get(route('pos.reservations.index'))->assertForbidden();
    }
}
