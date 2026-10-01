<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\AppNotification;
use App\Models\AttendanceDevice;
use App\Models\Booking;
use App\Models\CashMovement;
use App\Models\HkTask;
use App\Models\KeyCard;
use App\Models\Outlet;
use App\Models\PosShift;
use App\Models\SocialLink;
use App\Models\User;
use App\Models\Villa;
use App\Modules\Auth\Support\DemoLogin;
use App\Modules\Booking\Services\AvailabilityService;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Core\Services\CurrencyService;
use App\Modules\FrontDesk\Services\CheckInService;
use App\Modules\FrontDesk\Services\CheckOutService;
use App\Modules\FrontDesk\Services\RoomChargeService;
use App\Modules\Housekeeping\Services\HousekeepingService;
use App\Modules\POS\Services\PosShiftService;
use Tests\TestCase;

/**
 * Brand & contact identity, username login + demo role panel, display currencies, social links,
 * checkout → housekeeping, cashier day-end, RFID access API/simulator, POS access and notification polling.
 */
class HospitalityUpgradeTest extends TestCase
{
    // ------------------------------------------------------------------ Identity & website

    public function test_website_shows_jaffna_contact_details_and_real_logo(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Jaffna', $html);
        $this->assertStringContainsString('tel:0764413420', $html);
        $this->assertStringContainsString('mailto:aflalal2004@gmail.com', $html);
        $this->assertStringContainsString('https://wa.me/94764413420', $html);
        $this->assertStringContainsString('assets/brand/vaasal-logo-', $html);
        $this->assertDoesNotMatchRegularExpression('/Galle(?!ry)|Mirissa|Lighthouse Road|94770000000/', $html, 'old location text must be gone');
        $this->assertSame('Jaffna, Sri Lanka', contact('location'));
        $this->get('/contact')->assertOk()->assertSee('0764413420')->assertSee('aflalal2004@gmail.com');
    }

    // ------------------------------------------------------------------ Login

    public function test_staff_can_sign_in_with_username_and_role_lands_on_home(): void
    {
        $this->post('/login', ['login' => 'cashier', 'password' => DemoLogin::PASSWORD])->assertRedirect(route('pos.terminal'));
        $this->post('/logout');
        $this->post('/login', ['login' => 'hksupervisor', 'password' => DemoLogin::PASSWORD])->assertRedirect(route('admin.housekeeping.index'));
        $this->post('/logout');
        $this->post('/login', ['login' => 'cashier', 'password' => 'wrong-password'])->assertSessionHasErrors('login');
    }

    public function test_demo_role_panel_is_only_rendered_in_local_environment(): void
    {
        // phpunit runs with APP_ENV=testing: no demo credentials
        $this->get('/login')->assertOk()->assertDontSee('Quick role sign-in')->assertDontSee(DemoLogin::PASSWORD);
        $this->assertSame([], DemoLogin::roles());

        $this->app['env'] = 'local';
        config(['vaasal.demo_login' => true]);
        $this->get('/login')->assertOk()->assertSee('Quick role sign-in')->assertSee('data-username="cashier"', false);
        config(['vaasal.demo_login' => false]);
        $this->get('/login')->assertOk()->assertDontSee('Quick role sign-in');
        $this->app['env'] = 'testing';
    }

    // ------------------------------------------------------------------ Currency

    public function test_currency_conversion_is_display_only_and_remembered(): void
    {
        config(['vaasal.currency_display.api_key' => null]);
        $fx = app(CurrencyService::class);
        $this->assertSame('LKR', $fx->base());
        $this->assertSame(round(72000 * config('vaasal.currency_display.fallback_rates.USD'), 2), $fx->convert(72000, 'USD'));
        $this->assertSame('Rs 72,000', $fx->format(72000, 'LKR'));

        $this->postJson('/currency', ['currency' => 'EUR'])->assertOk()->assertJsonPath('currency', 'EUR')->assertCookie('vv_currency', 'EUR', false);
        $this->postJson('/currency', ['currency' => 'XYZ'])->assertStatus(422);
        $this->getJson('/api/v1/currencies')->assertOk()->assertJsonPath('base', 'LKR')->assertJsonStructure(['rates' => ['USD', 'EUR', 'GBP', 'INR', 'AUD', 'AED']]);

        $html = $this->withUnencryptedCookie('vv_currency', 'USD')->get('/villas')->assertOk()->getContent();
        $this->assertStringContainsString('data-price-lkr=', $html);
        $this->assertStringContainsString('charged in LKR', $html);
    }

    // ------------------------------------------------------------------ Social links

    public function test_admin_manages_social_links_and_footer_uses_them(): void
    {
        $this->as('admin@vaasalvilla.test');
        $this->get(route('admin.social.index'))->assertOk()->assertSee('Social media');
        $this->post(route('admin.social.store'), ['platform' => 'instagram', 'url' => 'https://evil.example.com/x', 'scope' => 'both'])->assertSessionHasErrors('url');
        $this->post(route('admin.social.store'), ['platform' => 'tiktok', 'url' => 'https://www.tiktok.com/@vaasalvilla', 'scope' => 'website'])->assertSessionHasNoErrors();
        $link = SocialLink::where('url', 'https://www.tiktok.com/@vaasalvilla')->firstOrFail();
        $this->post('/logout');
        $this->get('/')->assertSee('https://www.tiktok.com/@vaasalvilla', false);

        $this->as('admin@vaasalvilla.test');
        $this->post(route('admin.social.toggle', $link))->assertSessionHasNoErrors();
        $this->assertFalse($link->fresh()->is_active);
        $this->post('/logout');
        $this->get('/')->assertDontSee('https://www.tiktok.com/@vaasalvilla', false);

        // POS managers may only manage restaurant-facing links
        $this->as('posmanager@vaasalvilla.test');
        $this->get(route('pos.social.index'))->assertOk();
        $this->put(route('pos.social.update', $link), ['platform' => 'tiktok', 'url' => 'https://www.tiktok.com/@x', 'scope' => 'both'])->assertForbidden();
        $this->as('waiter@vaasalvilla.test');
        $this->get(route('admin.social.index'))->assertForbidden();
    }

    // ------------------------------------------------------------------ Checkout → housekeeping

    public function test_checkout_alerts_housekeeping_and_attendant_accepts_pauses_and_completes(): void
    {
        $this->as('reception@vaasalvilla.test');
        $villa = app(AvailabilityService::class)->availableVillas(now(), now()->addDay())->first();
        $villa->update(['hk_status' => 'ready', 'occupancy_status' => 'vacant']);
        $booking = app(BookingService::class)->create([
            'source' => 'walk_in', 'guest' => ['first_name' => 'John', 'last_name' => 'Silva', 'email' => uniqid().'@example.test'],
            'arrival' => now()->toDateString(), 'departure' => now()->addDay()->toDateString(), 'villas' => [['villa_id' => $villa->id, 'adults' => 2]], 'ignore_rules' => true,
        ]);
        $card = KeyCard::where('status', 'available')->where('type', 'guest')->first();
        app(CheckInService::class)->checkIn($booking, ['id_type' => 'passport', 'id_number' => 'P123', 'card_uids' => [$booking->activeVillas->first()->id => $card->uid]]);
        HkTask::where('villa_id', $villa->id)->whereIn('status', HkTask::OPEN)->update(['status' => 'cancelled']);
        app(RoomChargeService::class)->postNights($booking->fresh(), $booking->departure);
        $folio = $booking->folios()->where('payer_type', 'guest')->first();
        app(CheckOutService::class)->checkOut($booking->fresh(), ['payments' => [$folio->id => ['method' => 'cash', 'amount' => $folio->balance()]]]);

        $villa->refresh();
        $this->assertSame('dirty', $villa->boardStatus());
        $this->assertSame('available', $card->fresh()->status, 'guest card revoked');
        $task = HkTask::where('villa_id', $villa->id)->where('type', 'departure')->where('status', 'pending')->latest('id')->firstOrFail();

        // Housekeeping sees the alert immediately (bell poll + board) and the task in the open pool
        $hk = $this->as('housekeeping@vaasalvilla.test');
        $title = "Villa {$villa->code} is ready for cleaning after guest checkout.";
        $this->assertTrue(AppNotification::visibleTo($hk)->where('title', $title)->exists());
        $this->getJson(route('admin.notifications.poll', ['after' => 1]))->assertOk()->assertJsonFragment(['title' => $title]);
        $this->get(route('admin.housekeeping.my'))->assertOk()->assertSee('Ready for cleaning')->assertSee($villa->code);

        $this->post(route('admin.housekeeping.tasks.accept', $task))->assertSessionHasNoErrors();
        $this->assertSame($hk->employee->id, $task->fresh()->assigned_to);
        $this->post(route('admin.housekeeping.tasks.start', $task));
        $this->assertSame('cleaning', $villa->fresh()->boardStatus());
        $this->post(route('admin.housekeeping.tasks.pause', $task), ['reason' => 'Linen delivery']);
        $this->assertSame('paused', $task->fresh()->status);
        $this->post(route('admin.housekeeping.tasks.start', $task));
        $this->assertSame('in_progress', $task->fresh()->status);
        $task->items()->update(['is_checked' => true]);
        $this->post(route('admin.housekeeping.tasks.submit', $task));
        $this->assertSame('inspection', $villa->fresh()->boardStatus());

        $this->as('hksupervisor@vaasalvilla.test');
        app(HousekeepingService::class)->approve($task->fresh());
        $this->assertSame('available', $villa->fresh()->boardStatus());
    }

    public function test_same_day_website_availability_hides_villas_that_are_not_ready(): void
    {
        $avail = app(AvailabilityService::class);
        $free = $avail->availableVillas(now(), now()->addDay());
        $this->assertNotEmpty($free);
        $dirty = $free->first();
        $dirty->update(['hk_status' => 'dirty', 'occupancy_status' => 'vacant']);
        $this->assertFalse($avail->availableVillas(now(), now()->addDay(), null, null, true)->contains('id', $dirty->id));
        $this->assertTrue($avail->availableVillas(now(), now()->addDay())->contains('id', $dirty->id), 'front desk can still sell it for later arrival');
        $this->assertTrue($avail->availableVillas(now()->addDays(40), now()->addDays(41), null, null, true)->contains('id', $dirty->id), 'future dates are unaffected');
    }

    // ------------------------------------------------------------------ Cashier shift

    public function test_cashier_day_end_with_deposit_denominations_variance_review_and_reopen(): void
    {
        $cashier = $this->as('cashier@vaasalvilla.test');
        $outlet = Outlet::where('code', 'BAR')->first() ?? Outlet::first();
        PosShift::where('user_id', $cashier->id)->where('outlet_id', $outlet->id)->where('status', 'open')->update(['status' => 'closed', 'closed_at' => now()]);
        $shifts = app(PosShiftService::class);
        $shift = $shifts->open($outlet, $cashier, 10000);

        $this->post(route('pos.shifts.cash', $shift), ['type' => 'cash_in', 'amount' => 2000, 'reason' => 'Change from safe'])->assertSessionHasNoErrors();
        $this->post(route('pos.shifts.cash', $shift), ['type' => 'cash_out', 'amount' => 1500, 'reason' => 'Ice purchase'])->assertSessionHasNoErrors();
        $this->post(route('pos.shifts.cash', $shift), ['type' => 'deposit', 'amount' => 5000, 'reason' => 'Safe drop', 'reference' => 'SLIP-1'])->assertSessionHasNoErrors();
        $this->post(route('pos.shifts.cash', $shift), ['type' => 'adjustment', 'amount' => -100, 'reason' => 'x'])->assertForbidden();
        $this->assertSame(5500.0, $shift->expectedCash());

        $this->get(route('pos.day-end', ['shift' => $shift->id]))->assertOk()->assertSee('Count the drawer');
        $this->post(route('pos.shifts.close', $shift), ['counted_cash' => 5400])->assertSessionHasErrors('confirm');
        $this->post(route('pos.shifts.close', $shift), ['counted_cash' => 5400, 'denominations' => [1000 => 5, 100 => 3], 'confirm' => 1])->assertSessionHas('error');
        $this->post(route('pos.shifts.close', $shift), ['counted_cash' => 5400, 'denominations' => [1000 => 5, 100 => 4], 'notes' => 'Short 100', 'confirm' => 1])
            ->assertRedirect(route('pos.shifts.show', $shift));
        $shift->refresh();
        $this->assertSame(['closed', 5500.0, 5400.0, -100.0], [$shift->status, (float) $shift->expected_cash, (float) $shift->counted_cash, (float) $shift->variance]);
        $this->assertEquals(5000, $shift->closing_report['deposits']);
        $this->post(route('pos.shifts.cash', $shift), ['type' => 'cash_in', 'amount' => 5, 'reason' => 'x'])->assertSessionHas('error');
        $this->get(route('pos.shifts.print', $shift))->assertOk()->assertSee('Cashier Closing Report');
        $this->get(route('pos.shifts.print', [$shift, 'format' => 'thermal']))->assertOk()->assertSee('VARIANCE');
        $this->get(route('pos.today'))->assertOk()->assertSee('Cash currently in drawer');
        $this->get(route('pos.cash-movements'))->assertOk()->assertSee('SLIP-1');
        $this->post(route('pos.shifts.review', $shift), ['review_status' => 'approved'])->assertForbidden();
        $this->post(route('pos.shifts.reopen', $shift), ['reason' => 'Recount please'])->assertForbidden();

        // Review/reopen belongs to the Restaurant Manager (the Hotel Manager has no POS access).
        $this->as('manager@vaasalvilla.test');
        $this->post(route('pos.shifts.review', $shift), ['review_status' => 'approved'])->assertForbidden();
        $this->as('posmanager@vaasalvilla.test');
        $this->post(route('pos.shifts.review', $shift), ['review_status' => 'flagged', 'review_notes' => 'Explain the 100 short'])->assertSessionHasNoErrors();
        $this->assertSame('flagged', $shift->fresh()->review_status);
        $this->post(route('pos.shifts.reopen', $shift), ['reason' => 'Missed card slip'])->assertSessionHasNoErrors();
        $this->assertSame('open', $shift->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'shift_reopened', 'auditable_id' => $shift->id]);
        $this->assertSame(1, CashMovement::where('pos_shift_id', $shift->id)->where('type', 'deposit')->count());
    }

    // ------------------------------------------------------------------ RFID

    public function test_rfid_device_api_grants_denies_logs_and_punches_attendance(): void
    {
        $token = 'test-reader-token';
        $gate = AttendanceDevice::create(['name' => 'Test Villa Area gate', 'type' => 'rfid', 'purpose' => 'both', 'zone' => 'Villa Area', 'mode' => 'hardware',
            'api_token_hash' => hash('sha256', $token)]);
        $hk = User::where('email', 'housekeeping2@vaasalvilla.test')->first()->employee;
        $hk->update(['rfid_uid' => '04TEST0000AA']);
        \App\Models\AttendanceRecord::where('employee_id', $hk->id)->where(fn ($q) => $q->whereNull('clock_out')->orWhere('work_date', '>=', now()->subDay()->toDateString()))->delete();

        $this->postJson('/api/v1/rfid/scan', ['identifier_type' => 'rfid', 'identifier' => '04TEST0000AA'])->assertUnauthorized();
        $ok = $this->withToken($token)->postJson('/api/v1/rfid/scan', ['identifier_type' => 'rfid', 'identifier' => '04TEST0000AA', 'occurred_at' => now()->toIso8601String()]);
        $ok->assertOk()->assertJsonPath('decision', 'allow')->assertJsonPath('holder_type', 'staff')->assertJsonPath('attendance.action', 'clock_in');

        $unknown = $this->withToken($token)->postJson('/api/v1/rfid/scan', ['identifier_type' => 'rfid', 'identifier' => '04FFFFFFFF99']);
        $unknown->assertOk()->assertJsonPath('decision', 'deny')->assertJsonPath('reason', 'Unknown card');

        $lost = KeyCard::where('status', 'lost')->first();
        if ($lost) {
            $this->withToken($token)->postJson('/api/v1/rfid/scan', ['identifier_type' => 'rfid', 'identifier' => $lost->uid])->assertJsonPath('decision', 'deny');
        }
        $this->assertSame(2 + ($lost ? 1 : 0), AccessLog::where('device_id', $gate->id)->count());
        $this->assertTrue(AccessLog::where('device_id', $gate->id)->where('granted', false)->where('reason', 'Unknown card')->exists());

        // Guest card: its own villa yes, another villa no
        $a = \App\Models\KeyCardAssignment::with(['card', 'villa'])->where('status', 'active')->whereNotNull('booking_id')->first();
        if ($a) {
            $other = Villa::where('id', '!=', $a->villa_id)->first();
            $this->as('admin@vaasalvilla.test');
            $this->post(route('admin.access.simulate'), ['uid' => $a->card->uid, 'villa_id' => $a->villa_id])->assertSessionHas('rfid_result.granted', true);
            $this->post(route('admin.access.simulate'), ['uid' => $a->card->uid, 'villa_id' => $other->id])->assertSessionHas('rfid_result.granted', false);
            $this->get(route('admin.access.simulator'))->assertOk()->assertSee('Access');
            $this->get(route('admin.access.devices'))->assertOk()->assertSee('Test Villa Area gate');
        }
    }

    // ------------------------------------------------------------------ POS access & notifications

    public function test_pos_access_entry_point_routes_by_role(): void
    {
        $this->get(route('pos.access'))->assertOk()->assertSee('Sign in to POS');
        $this->as('waiter@vaasalvilla.test');
        $this->get(route('pos.access'))->assertRedirect(route('pos.terminal'));
        $this->as('kitchen@vaasalvilla.test');
        $this->get(route('pos.access'))->assertRedirect(route('pos.kds'));
        $this->as('housekeeping@vaasalvilla.test');
        $this->get(route('pos.access'))->assertForbidden()->assertSee('No POS access');
        $this->get(route('pos.terminal'))->assertForbidden();
    }

    public function test_every_new_screen_renders_for_admin(): void
    {
        $this->as('admin@vaasalvilla.test');
        foreach (['admin.dashboard', 'admin.frontdesk.index', 'admin.housekeeping.index', 'admin.settings.edit', 'admin.social.index', 'admin.access.devices',
            'admin.access.simulator', 'admin.keycards.logs', 'pos.today', 'pos.day-end', 'pos.cash-movements', 'pos.shifts.index', 'pos.social.index'] as $r) {
            $this->get(route($r))->assertOk();
        }
        $this->get(route('admin.cms.index', ['tab' => 'media']))->assertOk()->assertSee('Stock media');
        $this->getJson(route('admin.cms.media.search', ['q' => 'villa']))->assertOk()->assertJsonStructure(['items', 'provider', 'fallback', 'providers']);
        $this->getJson(route('admin.notifications.poll'))->assertOk()->assertJsonStructure(['unread', 'last_id', 'items']);
    }
}
