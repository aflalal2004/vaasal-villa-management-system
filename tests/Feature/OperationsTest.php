<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Booking;
use App\Models\Employee;
use App\Models\HkTask;
use App\Models\KeyCard;
use App\Models\LockBridge;
use App\Models\LockJob;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PaymentIntent;
use App\Models\PosTable;
use App\Models\RosterEntry;
use App\Models\Shift;
use App\Models\StockItem;
use App\Models\Villa;
use App\Models\VillaType;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Housekeeping\Services\HousekeepingService;
use App\Modules\KeyCards\Services\KeyCardService;
use App\Modules\POS\Services\PosBillingService;
use App\Modules\POS\Services\PosOrderService;
use App\Modules\POS\Services\PosShiftService;
use App\Modules\Staff\Services\AttendanceService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Housekeeping, POS, attendance, key cards / Lock Bridge, website booking + hosted payment. */
class OperationsTest extends TestCase
{
    public function test_housekeeping_flow_makes_villa_ready_only_after_inspection(): void
    {
        $this->as('hksupervisor@vaasalvilla.test');
        $hk = app(HousekeepingService::class);
        $villa = Villa::where('occupancy_status', 'vacant')->first();
        $villa->update(['hk_status' => 'dirty']);
        $task = $hk->createTask($villa, 'departure', now(), null, 'high', Employee::where('email', 'housekeeping@vaasalvilla.test')->value('id'));
        $hk->start($task);
        $this->assertSame('cleaning', $villa->fresh()->hk_status);
        try {
            $hk->requestInspection($task);
            $this->fail('Checklist must be complete');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('checklist', strtolower($e->getMessage()));
        }
        foreach ($task->items as $i) { $hk->toggleItem($task, $i->id, true); }
        $hk->requestInspection($task->fresh());
        $this->assertSame('inspection', $villa->fresh()->hk_status);
        $hk->reject($task->fresh(), 'Towels missing');
        $this->assertSame(['cleaning', 1], [$villa->fresh()->hk_status, $task->fresh()->rejection_count]);
        $hk->requestInspection($task->fresh());
        $hk->approve($task->fresh());
        $this->assertSame('ready', $villa->fresh()->hk_status);
    }

    public function test_pos_order_kots_split_merge_and_payment_with_stock_depletion(): void
    {
        $cashier = $this->as('cashier@vaasalvilla.test');
        $outlet = Outlet::where('code', 'REST')->first();
        \App\Models\PosShift::where('user_id', $cashier->id)->where('status', 'open')->update(['status' => 'closed', 'closed_at' => now()]);
        $orders = app(PosOrderService::class);
        $tables = PosTable::where('outlet_id', $outlet->id)->whereDoesntHave('openOrder')->take(2)->get();
        $o = $orders->open($outlet, ['pos_table_id' => $tables[0]->id, 'covers' => 2]);

        $curry = MenuItem::where('code', 'MN1')->first(); // requires spice level
        try {
            $orders->addItem($o, $curry, 1, []);
            $this->fail('Required modifier must be enforced');
        } catch (BusinessRuleException) {
        }
        $spice = $curry->modifierGroups()->where('name', 'Spice level')->first()->modifiers()->first();
        $orders->addItem($o, $curry, 2, [$spice->id]);
        $orders->addItem($o, MenuItem::where('code', 'DR3')->first(), 2);
        $kots = $orders->fire($o);
        $this->assertEqualsCanonicalizing(['kitchen', 'bar'], collect($kots)->pluck('station')->all(), 'One KOT per station');

        $new = $orders->split($o, [$o->items()->where('station', 'bar')->first()->id => 1]);
        $this->assertSame(1.0, (float) $new->items()->first()->quantity);
        $orders->merge($o, $new);
        $this->assertSame('merged', $new->fresh()->status);

        try {
            app(PosBillingService::class)->pay($o->fresh(), [['method' => 'cash', 'amount' => (float) $o->fresh()->total]]);
            $this->fail('Cash needs an open cashier shift');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('shift', $e->getMessage());
        }
        app(PosShiftService::class)->open($outlet, $cashier, 5000);
        $chicken = StockItem::where('sku', 'MT-CHK')->first()->current_qty;
        $paid = app(PosBillingService::class)->pay($o->fresh(), [['method' => 'cash', 'amount' => (float) $o->fresh()->total, 'tendered' => ceil($o->fresh()->total / 1000) * 1000]]);
        $this->assertSame('paid', $paid->status);
        $this->assertNotNull($paid->invoice_no);
        $this->assertEqualsWithDelta($chicken - 0.36, StockItem::where('sku', 'MT-CHK')->first()->current_qty, 0.001, 'Recipe depletes 0.18 kg × 2');
    }

    public function test_non_cash_payment_uses_the_cashiers_open_shift_at_another_outlet(): void
    {
        $cashier = $this->as('cashier@vaasalvilla.test');
        \App\Models\PosShift::where('user_id', $cashier->id)->where('status', 'open')->update(['status' => 'closed', 'closed_at' => now()]);
        $shift = app(PosShiftService::class)->open(Outlet::where('code', 'REST')->first(), $cashier, 10000);

        // Check at In-villa dining, shift at Vaasal Kitchen: every shift tender must find that shift.
        $orders = app(PosOrderService::class);
        foreach (['bank_transfer', 'card', 'digital', 'cash'] as $method) {
            $o = $orders->open(Outlet::where('code', 'IRD')->first(), ['type' => 'takeaway']);
            $orders->addItem($o, MenuItem::where('code', 'DR3')->first(), 1);
            $orders->fire($o);
            $paid = app(PosBillingService::class)->pay($o->fresh(), [['method' => $method, 'amount' => (float) $o->fresh()->total, 'reference' => 'TX1']]);
            $this->assertSame('paid', $paid->status, $method);
            $this->assertSame($shift->id, $paid->pos_shift_id, $method);
            $this->assertSame($shift->id, $paid->payments()->first()->pos_shift_id, $method);
        }
        $this->assertSame(1, \App\Models\PosShift::where('user_id', $cashier->id)->where('status', 'open')->count(), 'No duplicate shift');
    }

    public function test_attendance_calculates_late_and_overtime_against_roster(): void
    {
        $e = Employee::where('email', 'stores@vaasalvilla.test')->first();
        $shift = Shift::where('name', 'Day')->first(); // 09:00–17:30, grace 10, break 60 → 7.5h scheduled
        $date = now()->addDays(3)->toDateString();
        RosterEntry::updateOrCreate(['employee_id' => $e->id, 'work_date' => $date], ['shift_id' => $shift->id]);
        $svc = app(AttendanceService::class);
        $svc->clock($e, 'rfid', null, Carbon::parse("$date 09:25"));
        $r = $svc->clock($e, 'rfid', null, Carbon::parse("$date 19:25"));
        $this->assertSame(25, $r->late_minutes);
        $this->assertSame(540, $r->worked_minutes); // 600 elapsed − 60 break
        $this->assertSame(90, $r->overtime_minutes); // 540 − 450
        $this->assertSame('late', $r->status);
    }

    public function test_new_key_invalidates_previous_card_and_lost_card_is_replaced(): void
    {
        $this->as('reception@vaasalvilla.test');
        $booking = Booking::where('status', 'checked_in')->first();
        $bv = $booking->activeVillas->first();
        $cards = app(KeyCardService::class);
        $free = KeyCard::where('status', 'available')->where('type', 'guest')->take(3)->get();
        $a1 = $cards->issueForBooking($bv, $free[0]->uid, 'new');
        $a2 = $cards->issueForBooking($bv, $free[1]->uid, 'duplicate');
        $this->assertSame(['active', 'active'], [$a1->status, $a2->status]);
        $a3 = $cards->issueForBooking($bv, $free[2]->uid, 'new');
        $this->assertSame('revoked', $a1->fresh()->status);
        $this->assertSame('revoked', $a2->fresh()->status);
        $this->assertSame('active', $a3->status);
        $replacement = $cards->reportLost($free[2]->fresh(), KeyCard::where('status', 'available')->where('type', 'guest')->first()->uid);
        $this->assertSame('lost', $free[2]->fresh()->status);
        $this->assertSame('active', $replacement->status);
    }

    public function test_lock_bridge_claims_jobs_and_reports_results(): void
    {
        config(['vaasal.locks.driver' => 'bridge']);
        $token = 'test-bridge-token';
        LockBridge::first()->update(['token_hash' => hash('sha256', $token)]);
        $this->as('reception@vaasalvilla.test');
        $bv = Booking::where('status', 'checked_in')->first()->activeVillas->first();
        $a = app(KeyCardService::class)->issueForBooking($bv, KeyCard::where('status', 'available')->first()->uid, 'duplicate');
        $this->assertSame('pending', $a->status);

        $this->getJson('/api/v1/lock-bridge/jobs')->assertUnauthorized();
        $jobs = $this->getJson('/api/v1/lock-bridge/jobs', ['Authorization' => "Bearer $token"])->assertOk()->json('jobs');
        $job = collect($jobs)->firstWhere('payload.card_uid', $a->card->uid);
        $this->assertNotNull($job);
        $this->assertSame('processing', LockJob::where('uuid', $job['uuid'])->value('status'));
        $this->postJson("/api/v1/lock-bridge/jobs/{$job['uuid']}/result", ['success' => true, 'message' => 'Encoded', 'encoder' => 'FO-ENC-1'], ['Authorization' => "Bearer $token"])->assertOk();
        $this->assertSame('active', $a->fresh()->status);
        $this->postJson('/api/v1/lock-bridge/events', ['events' => [['lock_ref' => $bv->villa->lock_ref, 'card_uid' => $a->card->uid, 'event' => 'open', 'occurred_at' => now()->toIso8601String()]]],
            ['Authorization' => "Bearer $token"])->assertOk()->assertJson(['stored' => 1]);
    }

    public function test_website_booking_holds_then_confirms_after_hosted_payment(): void
    {
        $type = VillaType::where('slug', 'pool-villa')->first();
        $res = $this->post(route('book.hold'), [
            'type' => $type->id, 'plan' => 1, 'arrival' => now()->addDays(230)->toDateString(), 'departure' => now()->addDays(233)->toDateString(), 'adults' => 2, 'children' => 0,
            'first_name' => 'Web', 'last_name' => 'Guest', 'email' => 'web.guest@example.test', 'phone' => '+44 7700 900000', 'country' => 'United Kingdom', 'pay' => 'deposit', 'terms' => 1,
        ]);
        $intent = PaymentIntent::latest('id')->first();
        $res->assertRedirect(route('pay.sandbox', $intent->token));
        $this->assertSame('hold', $intent->booking->status);
        $this->assertEqualsWithDelta((float) $intent->booking->deposit_due, (float) $intent->amount, 0.01);

        $this->post(route('pay.sandbox.complete', $intent->token), ['result' => 'approve'])->assertRedirect(route('pay.return', $intent->token));
        $b = $intent->booking->fresh();
        $this->assertSame('confirmed', $b->status);
        $this->assertEqualsWithDelta((float) $intent->amount, $b->paidTotal(), 0.01);
        $this->assertTrue($b->invoices()->where('type', 'receipt')->exists());
        // Idempotent: a second completion does not double-post
        app(\App\Modules\Billing\Services\OnlinePaymentService::class)->complete($intent->fresh());
        $this->assertEqualsWithDelta((float) $intent->amount, $b->fresh()->paidTotal(), 0.01);
    }

    public function test_attendance_device_api_punches_by_rfid_badge(): void
    {
        $e = Employee::where('email', 'maintenance@vaasalvilla.test')->first();
        $e->update(['rfid_uid' => 'BADGE01']);
        \App\Models\AttendanceDevice::create(['name' => 'Gate reader', 'type' => 'rfid', 'api_token_hash' => hash('sha256', 'dev-token')]);
        AttendanceRecord::where('employee_id', $e->id)->where('work_date', now()->toDateString())->delete();
        $this->postJson('/api/v1/attendance/punch', ['identifier_type' => 'rfid', 'identifier' => 'BADGE01'], ['Authorization' => 'Bearer dev-token'])
            ->assertOk()->assertJson(['action' => 'clock_in']);
        $this->postJson('/api/v1/attendance/punch', ['identifier_type' => 'rfid', 'identifier' => 'BADGE01'], ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
    }
}
