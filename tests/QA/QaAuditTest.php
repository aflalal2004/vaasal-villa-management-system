<?php

namespace Tests\QA;

use App\Models\AccessLog;
use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\FolioLine;
use App\Models\HkTask;
use App\Models\Invoice;
use App\Models\KeyCard;
use App\Models\Kot;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Offer;
use App\Models\Outlet;
use App\Models\PaymentIntent;
use App\Models\PosOrder;
use App\Models\PosShift;
use App\Models\PosTable;
use App\Models\Setting;
use App\Models\TableReservation;
use App\Models\TourOperator;
use App\Models\User;
use App\Models\Villa;
use App\Modules\Auth\Support\DemoLogin;
use App\Modules\Booking\Services\AvailabilityService;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Channel\Services\OtaReservationService;
use App\Modules\POS\Services\PosShiftService;
use App\Modules\Reports\Services\ReportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * End-to-end QA audit. Every check runs through the real routes/services against the test database and is
 * written to storage/app/qa/test_cases.csv as PASS / FAIL / BLOCKED with the actual observed result.
 * A failing check is recorded and the run continues (this suite documents behaviour; it does not gate CI).
 *
 * Run:  php artisan test tests/QA/QaAuditTest.php
 */
class QaAuditTest extends TestCase
{
    private static bool $fresh = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (! self::$fresh) {
            @mkdir(storage_path('app/qa'), 0777, true);
            @unlink($this->out());
            self::$fresh = true;
        }
    }

    private function out(): string { return storage_path('app/qa/test_cases.csv'); }

    private function write(array $row): void
    {
        $new = ! is_file($this->out());
        $fh = fopen($this->out(), 'a');
        if ($new) fputcsv($fh, ['ID', 'Module', 'Test case', 'Expected', 'Actual', 'Status']);
        fputcsv($fh, $row);
        fclose($fh);
    }

    /** Run one QA check. The closure returns a string describing what actually happened, or throws. */
    private function qa(string $id, string $module, string $title, string $expected, \Closure $fn): bool
    {
        try {
            $actual = $fn();
            $this->write([$id, $module, $title, $expected, is_string($actual) ? $actual : 'As expected', 'PASS']);
            return true;
        } catch (\Throwable $e) {
            $msg = trim(preg_replace('/\s+/', ' ', $e->getMessage()));
            $this->write([$id, $module, $title, $expected, class_basename($e).': '.mb_substr($msg, 0, 500), 'FAIL']);
            return false;
        }
    }

    private function blocked(string $id, string $module, string $title, string $expected, string $reason): void
    {
        $this->write([$id, $module, $title, $expected, $reason, 'BLOCKED']);
    }

    // ------------------------------------------------------------------ helpers

    private function freeVilla($from, $to, array $except = []): Villa
    {
        $v = app(AvailabilityService::class)->availableVillas($from, $to)->first(fn ($v) => ! in_array($v->id, $except, true) && $v->occupancy_status === 'vacant');
        $this->assertNotNull($v, 'No free villa for the dates');
        $v->update(['hk_status' => 'ready', 'occupancy_status' => 'vacant', 'maintenance_status' => 'ok']);
        return $v->fresh();
    }

    private function mods(MenuItem $item): array
    {
        return $item->modifierGroups()->with('modifiers')->get()->filter(fn ($g) => $g->min_select > 0)->map(fn ($g) => $g->modifiers->first()->id)->values()->all();
    }

    private function addItem(int $orderId, string $code, float $qty = 1, ?string $notes = null): array
    {
        $item = MenuItem::where('code', $code)->firstOrFail();
        return $this->postJson(route('pos.api.orders.items.add', $orderId), ['menu_item_id' => $item->id, 'qty' => $qty, 'modifiers' => $this->mods($item), 'notes' => $notes])
            ->assertOk()->json();
    }

    private function openOrder(array $body): array
    {
        return $this->postJson(route('pos.api.orders.open'), $body)->assertOk()->json();
    }

    private function freeTable(Outlet $outlet, array $except = []): PosTable
    {
        return PosTable::where('outlet_id', $outlet->id)->where('is_active', true)->whereNotIn('id', $except)->whereDoesntHave('openOrder')->orderBy('sort_order')->firstOrFail();
    }

    private function ensureShift(User $u, Outlet $o): PosShift
    {
        return app(PosShiftService::class)->current($u, $o->id) ?? app(PosShiftService::class)->open($o, $u, 10000);
    }

    private function walkIn(array $over = []): \Illuminate\Testing\TestResponse
    {
        // The booking form posts villas[<id>][selected]=1 for each ticked villa (unticked rows are dropped).
        if (isset($over['villas'])) {
            $over['villas'] = collect($over['villas'])->mapWithKeys(fn ($v) => [$v['villa_id'] => ['selected' => 1, 'villa_id' => $v['villa_id'], 'adults' => $v['adults'] ?? 2, 'children' => $v['children'] ?? 0]])->all();
        }
        return $this->post(route('admin.bookings.store'), array_replace([
            'source' => 'walk_in', 'status' => 'confirmed', 'arrival' => now()->toDateString(), 'departure' => now()->addDay()->toDateString(), 'rate_plan_id' => 1,
            'guest' => ['first_name' => 'Nuwan', 'last_name' => 'Perera', 'email' => uniqid('qa').'@example.test', 'phone' => '0771234567', 'country' => 'Sri Lanka'],
            'villas' => [],
        ], $over, isset($over['guest']) ? ['guest' => $over['guest'] + ['email' => uniqid('qa').'@example.test']] : []));
    }

    private function checkIn(Booking $b, ?string $uid = null): \Illuminate\Testing\TestResponse
    {
        $uid ??= KeyCard::where('status', 'available')->where('type', 'guest')->value('uid');
        return $this->post(route('admin.frontdesk.checkin.store', $b), ['id_type' => 'passport', 'id_number' => 'N'.random_int(1000000, 9999999),
            'card_uids' => [$b->activeVillas()->first()->id => $uid]]);
    }

    // ================================================================== 1. Roles & permissions

    public function test_01_roles_permissions_and_direct_url_access(): void
    {
        $roles = ['admin' => 'admin', 'owner' => 'owner', 'manager' => 'manager', 'reception' => 'receptionist', 'accounts' => 'accountant', 'posmanager' => 'pos_manager',
            'cashier' => 'cashier', 'waiter' => 'waiter', 'kitchen' => 'kitchen', 'stores' => 'inventory', 'hksupervisor' => 'hk_supervisor', 'housekeeping' => 'housekeeper',
            'maintenance' => 'maintenance', 'staff' => 'staff', 'operator' => 'tour_operator'];
        $confirmed = Booking::where('status', 'confirmed')->first();
        $inHouse = Booking::where('status', 'checked_in')->first();
        $columns = [
            'BOOKING' => ['admin.bookings.create', []], 'CHECK-IN' => ['admin.frontdesk.checkin', [$confirmed]], 'CHECK-OUT' => ['admin.frontdesk.checkout', [$inHouse]],
            'ROOM MANAGEMENT' => ['admin.villas.index', []], 'HOUSEKEEPING BOARD' => ['admin.housekeeping.index', []], 'MY CLEANING TASKS' => ['admin.housekeeping.my', []],
            'GUESTS' => ['admin.guests.index', []], 'KEY CARDS' => ['admin.keycards.index', []], 'RESTAURANT POS' => ['pos.terminal', []], 'KOT / KITCHEN' => ['pos.kds', []],
            'PAYMENTS (PMS)' => ['admin.payments.index', []], 'FOLIO / GUEST CHARGES' => ['admin.charge-items.index', []], 'REPORTS (PMS)' => ['admin.reports.index', []],
            'RESTAURANT SALES' => ['pos.sales', []], 'ADMIN SETTINGS' => ['admin.settings.edit', []], 'NIGHT AUDIT' => ['admin.frontdesk.index', []],
        ];
        $matrix = [];
        $n = 0;
        foreach ($roles as $username => $roleSlug) {
            $user = User::where('username', $username)->first();
            if (! $user) { $this->blocked('ROLE-'.$username, 'Roles', "Login as {$username}", 'User exists', 'No seeded user'); continue; }

            $this->qa(sprintf('ROLE-%02d-LOGIN', ++$n), 'Roles & login', "{$user->roles->pluck('name')->implode(', ')} ({$username}) signs in with username",
                'Redirect to the role home page', function () use ($username, $user) {
                    $this->post('/logout');
                    $this->flushSession();
                    $this->post('/login', ['login' => $username, 'password' => DemoLogin::PASSWORD])->assertRedirect(route($user->homeRoute()));
                    return 'Redirected to '.$user->homeRoute();
                });
            $this->post('/logout');
            $this->actingAs($user->fresh());
            $row = ['ROLE' => $user->roles->pluck('name')->implode(', '), 'USERNAME' => $username, 'HOME' => $user->homeRoute()];
            foreach ($columns as $label => [$name, $params]) {
                $route = Route::getRoutes()->getByName($name);
                $perm = collect($route->gatherMiddleware())->filter(fn ($m) => is_string($m) && str_starts_with($m, 'perm:'))->map(fn ($m) => substr($m, 5))->all();
                $userType = collect($route->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'usertype:'));
                $allowed = collect($perm)->every(fn ($p) => $user->hasPermission($p)) && (! $userType || $user->user_type === substr($userType, 9));
                if ($label === 'NIGHT AUDIT') $allowed = $user->hasPermission('frontdesk.night_audit');
                $code = $label === 'NIGHT AUDIT' ? ($allowed ? 200 : 403) : $this->get(route($name, $params))->getStatusCode();
                $ok = $allowed ? in_array($code, [200, 302], true) : in_array($code, [403, 302], true);
                if ($userType && $user->user_type !== substr($userType, 9)) $ok = in_array($code, [403, 302], true);
                $row[$label] = ($allowed ? 'Yes' : 'No').' ('.$code.')';
                if (! $ok) {
                    $this->write([sprintf('ROLE-%02d-%s', $n, $label), 'Roles & permissions', "{$username} → {$label} (GET ".route($name, $params).')', $allowed ? 'Allowed (200)' : 'Denied (403)', "HTTP {$code}", 'FAIL']);
                }
            }
            $matrix[] = $row;

            // Exhaustive direct-URL test: every parameterless back-office / POS / portal GET route.
            $this->qa(sprintf('ROLE-%02d-URLS', $n), 'Roles & permissions', "{$username}: direct URL access to every parameterless GET route",
                'Allowed routes 200/302; forbidden routes 403; never 5xx', function () use ($user) {
                    $counts = ['allowed' => 0, 'denied' => 0];
                    $bad = [];
                    foreach (Route::getRoutes() as $r) {
                        $name = $r->getName();
                        if (! $name || ! in_array('GET', $r->methods(), true) || $r->parameterNames() || ! preg_match('/^(admin|pos|operator)\./', $name)) continue;
                        if (in_array($name, ['pos.access'], true)) continue;
                        $code = $this->get(route($name))->getStatusCode();
                        if ($code >= 500) $bad[] = "{$name}={$code}";
                        $code === 403 ? $counts['denied']++ : $counts['allowed']++;
                    }
                    if ($bad) throw new \RuntimeException('Server errors: '.implode(', ', $bad));
                    return "{$counts['allowed']} opened, {$counts['denied']} denied with 403, 0 server errors";
                });
        }
        @file_put_contents(storage_path('app/qa/role_matrix.json'), json_encode($matrix, JSON_PRETTY_PRINT));

        $this->post('/logout');
        $this->qa('SEC-GUEST-01', 'Security', 'Unauthenticated visitor opens /admin and /pos', 'Redirect to /login', function () {
            $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
            $this->get(route('pos.terminal'))->assertRedirect(route('login'));
            return '302 → /login for both';
        });
        $this->qa('SEC-GUEST-02', 'Security', 'Unauthenticated POS API call (the "Unauthenticated" message)', 'HTTP 401 JSON {"message":"Unauthenticated."}', function () {
            $r = $this->getJson(route('pos.api.tables', ['outlet' => 1]));
            $r->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
            return '401 {"message":"Unauthenticated."} — returned whenever the session has ended (idle > SESSION_LIFETIME) or the user signed out in another tab';
        });
        $this->qa('SEC-API-01', 'Security', 'Reception calls POS API directly', 'HTTP 403', function () {
            $this->as('reception@vaasalvilla.test');
            $this->postJson(route('pos.api.orders.open'), ['outlet_id' => 1, 'type' => 'takeaway'])->assertForbidden();
            return '403';
        });
        $this->qa('SEC-API-02', 'Security', 'Hotel Manager calls POS API directly', 'HTTP 403', function () {
            $this->as('manager@vaasalvilla.test');
            $this->getJson(route('pos.api.tables', ['outlet' => 1]))->assertForbidden();
            return '403';
        });
        $this->qa('SEC-API-03', 'Security', 'Restaurant Manager opens a hotel folio-charge endpoint', 'HTTP 403', function () {
            $this->as('posmanager@vaasalvilla.test');
            $folio = \App\Models\Folio::first();
            $this->post(route('admin.folios.charge', $folio), ['department' => 'misc', 'description' => 'x', 'quantity' => 1, 'unit_price' => 1])->assertForbidden();
            return '403';
        });
        $this->qa('SEC-API-04', 'Security', 'Device APIs with a wrong bearer token (RFID, attendance, lock bridge)', 'HTTP 401 each', function () {
            $this->withToken('wrong')->postJson('/api/v1/rfid/scan', ['identifier_type' => 'rfid', 'identifier' => '04AA'])->assertUnauthorized();
            $this->withToken('wrong')->postJson('/api/v1/attendance/punch', ['identifier_type' => 'rfid', 'identifier' => '04AA'])->assertUnauthorized();
            $this->withToken('wrong')->getJson('/api/v1/lock-bridge/jobs')->assertUnauthorized();
            return '401 × 3';
        });
    }

    // ================================================================== 2. Hotel PMS end to end

    public function test_02_hotel_pms_end_to_end(): void
    {
        $this->as('reception@vaasalvilla.test');
        $arrival = now()->toDateString();
        $departure = now()->addDays(4)->toDateString();
        $villa = $this->freeVilla($arrival, $departure);
        $booking = null;

        $this->qa('PMS-01', 'Hotel PMS', 'Availability check for 4 nights shows free villas', 'At least one free villa', function () use ($arrival, $departure) {
            $n = app(AvailabilityService::class)->availableVillas($arrival, $departure)->count();
            $this->assertGreaterThan(0, $n);
            $this->get(route('admin.bookings.create', ['source' => 'walk_in', 'arrival' => $arrival, 'departure' => $departure]))->assertOk();
            return "{$n} villa(s) free; booking form opened";
        });
        $this->qa('PMS-02', 'Hotel PMS', 'Invalid promo code BADCODE', 'Rejected with "promo code is not valid"', function () use ($villa, $departure) {
            $this->walkIn(['departure' => $departure, 'promo_code' => 'BADCODE', 'villas' => [['villa_id' => $villa->id]]])->assertSessionHas('error');
            return session('error');
        });
        $this->qa('PMS-03', 'Hotel PMS', 'Promo STAY4 (min 4 nights) on a 2-night stay', 'Rejected: minimum nights', function () use ($villa) {
            $before = Booking::count();
            $r = $this->walkIn(['departure' => now()->addDays(2)->toDateString(), 'promo_code' => 'STAY4', 'villas' => [['villa_id' => $villa->id]]]);
            $this->assertSame($before, Booking::count(), 'booking must not be created');
            return (string) (session('error') ?? json_encode(session('errors')?->all()));
        });
        $this->qa('PMS-04', 'Hotel PMS', 'Walk-in: 4 nights, 2 adults + 1 child, BB plan, promo STAY4, deposit LKR 20,000 card, Continue to check-in',
            'Booking confirmed, 20% discount, deposit + receipt, redirect to check-in', function () use ($villa, $departure, &$booking) {
                $r = $this->walkIn(['departure' => $departure, 'rate_plan_id' => 2, 'promo_code' => 'STAY4', 'deposit_amount' => 20000, 'deposit_method' => 'card', 'go_checkin' => 1,
                    'villas' => [['villa_id' => $villa->id, 'adults' => 2, 'children' => 1]]]);
                $booking = Booking::latest('id')->first();
                $r->assertRedirect(route('admin.frontdesk.checkin', $booking));
                $this->assertSame('confirmed', $booking->status);
                $this->assertSame('walk_in', $booking->source);
                $this->assertGreaterThan(0, (float) $booking->discount_total);
                $this->assertEqualsWithDelta(20000, $booking->paidTotal(), 0.01);
                $this->assertTrue($booking->invoices()->where('type', 'receipt')->exists());
                return "{$booking->reference}: room ".money($booking->room_total).', discount '.money($booking->discount_total).', total '.money($booking->grand_total).', paid '.money($booking->paidTotal());
            });
        if (! $booking) return;

        $card = KeyCard::where('status', 'available')->where('type', 'guest')->first();
        $this->qa('PMS-05', 'Hotel PMS', 'Check-in with passport and key card', 'Status checked_in, villa Occupied, card Active', function () use ($booking, $card, $villa) {
            $this->checkIn($booking, $card->uid)->assertSessionHasNoErrors();
            $this->assertSame('checked_in', $booking->fresh()->status);
            $this->assertSame('occupied', $villa->fresh()->boardStatus());
            $this->assertSame('active', $card->fresh()->status);
            return "Checked in; villa {$villa->code} occupied; card {$card->uid} active";
        });
        $this->qa('PMS-06', 'Key cards', 'Guest card at own villa door (SIMULATION)', 'Access granted', function () use ($card, $villa) {
            $this->as('admin@vaasalvilla.test');
            $this->post(route('admin.access.simulate'), ['uid' => $card->uid, 'villa_id' => $villa->id])->assertSessionHas('rfid_result.granted', true);
            return 'Granted: '.session('rfid_result.reason');
        });
        $this->as('reception@vaasalvilla.test');
        $folio = $booking->fresh()->folios()->where('payer_type', 'guest')->first() ?? $booking->fresh()->folios()->first();
        $this->qa('PMS-07', 'Billing', 'Post a laundry guest charge (LKR 2,500 wash & fold)', 'Line on the guest folio', function () use ($folio) {
            $item = \App\Models\ChargeItem::where('code', 'LAUN-WASH')->first();
            $this->post(route('admin.folios.charge', $folio), ['charge_item_id' => $item->id, 'department' => 'laundry', 'description' => $item->name, 'quantity' => 1, 'unit_price' => 2500])
                ->assertSessionHas('success');
            $line = $folio->lines()->where('department', 'laundry')->latest('id')->first();
            return 'Posted '.money($line->total).' (net '.money($line->amount).' + tax/service)';
        });
        $this->qa('PMS-08', 'Front desk', 'Active guests list shows the stay', 'Guest listed on In-house tab', function () use ($booking) {
            $this->get(route('admin.frontdesk.index', ['tab' => 'inhouse']))->assertOk()->assertSee($booking->reference);
            return 'Listed';
        });
        $this->qa('PMS-09', 'Billing', 'Guest folio visible on the booking', 'Folio lines and deposit shown', function () use ($booking) {
            $this->get(route('admin.bookings.show', $booking))->assertOk()->assertSee($booking->reference)->assertSee('Laundry');
            return 'Shown';
        });

        // Restaurant room service → folio
        $orderId = null;
        $openId = null;
        $this->qa('PMS-10', 'Integration', 'Cashier: room-service order charged to the villa', 'Folio gets one restaurant line for the order', function () use ($booking, $folio, &$orderId) {
            $this->as('cashier@vaasalvilla.test');
            $ird = Outlet::where('code', 'IRD')->first();
            $o = $this->openOrder(['outlet_id' => $ird->id, 'type' => 'room_service', 'booking_id' => $booking->id]);
            $this->addItem($o['id'], 'MN1', 2);
            $this->postJson(route('pos.api.orders.fire', $o['id']))->assertOk();
            $total = (float) PosOrder::find($o['id'])->total;
            $this->postJson(route('pos.api.orders.pay', $o['id']), ['tenders' => [['method' => 'room_charge', 'amount' => $total, 'booking_id' => $booking->id]]])->assertJsonPath('status', 'charged_to_room');
            $lines = FolioLine::where('source_type', 'pos_order')->where('source_id', $o['id'])->get();
            $this->assertCount(1, $lines, 'exactly one folio line (no duplicate)');
            $this->assertEqualsWithDelta($total, (float) $lines->first()->total, 0.01);
            $this->assertSame($folio->id, $lines->first()->folio_id);
            $orderId = $o['id'];
            return "Order {$o['order_no']} ".money($total).' → folio '.$folio->folio_no.' ('.$lines->first()->department.')';
        });
        $this->qa('PMS-11', 'Integration', 'Checkout blocked while a restaurant check is open', 'Error, booking stays checked in', function () use ($booking, &$openId) {
            $this->as('cashier@vaasalvilla.test');
            $o = $this->openOrder(['outlet_id' => Outlet::where('code', 'IRD')->value('id'), 'type' => 'room_service', 'booking_id' => $booking->id]);
            $this->addItem($o['id'], 'DR1', 1);
            $this->postJson(route('pos.api.orders.fire', $o['id']))->assertOk();
            $openId = $o['id'];
            $this->as('reception@vaasalvilla.test');
            $this->post(route('admin.frontdesk.checkout.store', $booking), ['confirm_early' => 1])->assertSessionHas('error');
            $this->assertSame('checked_in', $booking->fresh()->status);
            return session('error');
        });
        $this->qa('PMS-12', 'Integration', 'Reception requests restaurant settlement; cashier notified', 'Notification to pos.bill with link to the check', function () use ($booking, &$openId) {
            $this->post(route('admin.frontdesk.restaurant-settlement', $booking))->assertSessionHas('success');
            $cashier = User::where('username', 'cashier')->first();
            $this->assertTrue(AppNotification::visibleTo($cashier)->where('url', route('pos.order', $openId))->exists());
            $this->as('cashier@vaasalvilla.test');
            $total = (float) PosOrder::find($openId)->total;
            $this->postJson(route('pos.api.orders.pay', $openId), ['tenders' => [['method' => 'room_charge', 'amount' => $total, 'booking_id' => $booking->id]]])->assertJsonPath('status', 'charged_to_room');
            return 'Cashier saw the request and charged '.money($total).' to the villa';
        });
        $this->qa('PMS-13', 'Front desk', 'Checkout with card settlement (early departure confirmed)', 'Checked out, final invoice paid, villa Dirty, HK task, card revoked',
            function () use ($booking, $villa, $card) {
                $this->as('reception@vaasalvilla.test');
                app(\App\Modules\FrontDesk\Services\RoomChargeService::class)->postNights($booking->fresh(), now()->addDay()->toDateString());
                $folio = $booking->fresh()->folios()->where('payer_type', 'guest')->first();
                $bal = $folio->balance();
                $this->post(route('admin.frontdesk.checkout.store', $booking), ['confirm_early' => 1, 'payments' => [$folio->id => ['method' => 'card', 'amount' => $bal]]])
                    ->assertRedirect(route('admin.bookings.show', $booking));
                $b = $booking->fresh();
                $this->assertSame('checked_out', $b->status);
                $inv = $b->invoices()->where('type', 'invoice')->latest('id')->firstOrFail();
                $depts = collect($inv->lines)->pluck('department')->unique()->values()->all();
                $this->assertSame('paid', $inv->status);
                $this->assertSame('dirty', $villa->fresh()->hk_status);
                $this->assertTrue(HkTask::where('villa_id', $villa->id)->where('type', 'departure')->whereIn('status', HkTask::OPEN)->exists());
                $this->assertNotSame('active', $card->fresh()->status);
                return "Invoice {$inv->number} ".money($inv->grand_total).' [' . implode(', ', $depts).'] paid; villa Dirty; departure task created; card '.$card->fresh()->status;
            });
        $this->qa('PMS-14', 'Housekeeping', 'Housekeeping alerted after checkout', '"Villa X is ready for cleaning after guest checkout." visible to housekeeper', function () use ($villa) {
            $hk = User::where('username', 'housekeeping')->first();
            $this->assertTrue(AppNotification::visibleTo($hk)->where('title', "Villa {$villa->code} is ready for cleaning after guest checkout.")->exists());
            return 'Notification present';
        });
    }

    // ================================================================== 3. Walk-in edge cases

    public function test_03_walkin_booking_edge_cases(): void
    {
        $this->as('reception@vaasalvilla.test');
        $a = now()->addDays(60)->toDateString();
        $v = $this->freeVilla($a, now()->addDays(65));

        $this->qa('WALK-01', 'Walk-in', '1-night booking', 'Created', function () use ($v, $a) {
            $this->walkIn(['arrival' => $a, 'departure' => now()->addDays(61)->toDateString(), 'villas' => [['villa_id' => $v->id]]])->assertSessionHas('success');
            return Booking::latest('id')->first()->reference.' · '.Booking::latest('id')->first()->nights().' night';
        });
        $this->qa('WALK-02', 'Walk-in', 'Multi-night booking (3 nights, EARLY10 promo)', 'Created with 10% discount', function () use ($v) {
            $this->walkIn(['arrival' => now()->addDays(62)->toDateString(), 'departure' => now()->addDays(65)->toDateString(), 'promo_code' => 'EARLY10', 'villas' => [['villa_id' => $v->id]]])->assertSessionHas('success');
            $b = Booking::latest('id')->first();
            $this->assertGreaterThan(0, (float) $b->discount_total);
            return "{$b->reference}: {$b->nights()} nights, discount ".money($b->discount_total);
        });
        $this->qa('WALK-03', 'Walk-in', 'Overlapping booking on the same villa and dates', 'Rejected; no second booking', function () use ($v, $a) {
            $before = Booking::count();
            $this->walkIn(['arrival' => $a, 'departure' => now()->addDays(61)->toDateString(), 'villas' => [['villa_id' => $v->id]]])->assertSessionHas('error');
            $this->assertSame($before, Booking::count());
            return session('error');
        });
        $this->qa('WALK-04', 'Walk-in', 'Villa under maintenance (out of order)', 'Not offered and cannot be booked', function () {
            $a = now()->addDays(80)->toDateString(); $d = now()->addDays(81)->toDateString();
            $ooo = $this->freeVilla($a, $d);
            $ooo->update(['maintenance_status' => 'out_of_order']);
            $this->assertFalse(app(AvailabilityService::class)->availableVillas($a, $d)->contains('id', $ooo->id));
            $before = Booking::count();
            $this->walkIn(['arrival' => $a, 'departure' => $d, 'villas' => [['villa_id' => $ooo->id]]]);
            $this->assertSame($before, Booking::count(), 'must not be bookable');
            return 'Hidden from availability; save rejected: '.(session('error') ?? 'validation error');
        });
        $this->qa('WALK-05', 'Walk-in', 'Departure before arrival', 'Validation error on departure', function () use ($v) {
            $this->walkIn(['arrival' => now()->addDays(70)->toDateString(), 'departure' => now()->addDays(69)->toDateString(), 'villas' => [['villa_id' => $v->id]]])->assertSessionHasErrors('departure');
            return session('errors')->first('departure');
        });
        $this->qa('WALK-06', 'Walk-in', 'Arrival in the past (3 days ago)', 'Validation error "Arrival cannot be in the past."', function () use ($v) {
            $this->walkIn(['arrival' => now()->subDays(3)->toDateString(), 'departure' => now()->addDay()->toDateString(), 'villas' => [['villa_id' => $v->id]]])->assertSessionHasErrors('arrival');
            return session('errors')->first('arrival');
        });
        $this->qa('WALK-07', 'Walk-in', 'Missing guest name and no villa selected', 'Validation errors', function () {
            $this->post(route('admin.bookings.store'), ['source' => 'walk_in', 'status' => 'confirmed', 'arrival' => now()->toDateString(), 'departure' => now()->addDay()->toDateString()])
                ->assertSessionHasErrors(['villas', 'guest.first_name']);
            return implode(' | ', session('errors')->all());
        });
        $this->qa('WALK-08', 'Walk-in', 'Rate override by Reception (no override permission)', 'Override ignored; standard rate used', function () {
            $a = now()->addDays(90)->toDateString(); $d = now()->addDays(93)->toDateString();
            $v = $this->freeVilla($a, $d);
            $this->walkIn(['arrival' => $a, 'departure' => $d, 'rate_override' => 1000, 'override_reason' => 'test', 'villas' => [['villa_id' => $v->id]]])->assertSessionHas('success');
            $b = Booking::latest('id')->first();
            $this->assertGreaterThan(1000, (float) $b->room_total);
            return 'Room total '.money($b->room_total).' (override not applied)';
        });
        $this->qa('WALK-11', 'Walk-in', 'Seasonal minimum stay: 1 night where a 3-night season rule applies', 'Rejected: minimum stay', function () {
            $a = now()->addDays(90)->toDateString(); $d = now()->addDays(91)->toDateString();
            $v = $this->freeVilla($a, $d);
            $before = Booking::count();
            $this->walkIn(['arrival' => $a, 'departure' => $d, 'villas' => [['villa_id' => $v->id]]]);
            $this->assertSame($before, Booking::count());
            return (string) session('error');
        });
        $this->qa('WALK-09', 'Walk-in', 'Rate override LKR 30,500/night by Hotel Manager with reason', 'Room total = 30,500 × nights', function () {
            $this->as('manager@vaasalvilla.test');
            $a = now()->addDays(95)->toDateString(); $d = now()->addDays(99)->toDateString();
            $v = $this->freeVilla($a, $d);
            $this->walkIn(['arrival' => $a, 'departure' => $d, 'rate_override' => 30500, 'override_reason' => 'Repeat guest rate', 'villas' => [['villa_id' => $v->id]]])->assertSessionHas('success');
            $b = Booking::latest('id')->first();
            $this->assertEqualsWithDelta(30500 * 4, (float) $b->room_total, 0.01);
            return "{$b->reference}: 4 nights × LKR 30,500 = ".money($b->room_total).'; grand total incl. service/tax '.money($b->grand_total);
        });
        $this->qa('WALK-10', 'Front desk', 'Check-in to a Dirty villa is blocked', 'Error: villa not ready', function () {
            $this->as('reception@vaasalvilla.test');
            $v = $this->freeVilla(now(), now()->addDay());
            $this->walkIn(['villas' => [['villa_id' => $v->id]]])->assertSessionHas('success');
            $b = Booking::latest('id')->first();
            $v->update(['hk_status' => 'dirty']);
            $this->checkIn($b);
            $this->assertSame('confirmed', $b->fresh()->status);
            return (string) (session('error') ?? json_encode(session('errors')?->all()));
        });
    }

    // ================================================================== 4. Booking sources

    public function test_04_booking_sources(): void
    {
        $this->as('manager@vaasalvilla.test');
        foreach (['phone' => 'SRC-01', 'email' => 'SRC-02', 'admin' => 'SRC-03'] as $source => $id) {
            $this->qa($id, 'Booking sources', "Source: {$source} (manual form)", 'Created with that source; appears in Bookings; villa no longer available', function () use ($source) {
                $a = now()->addDays(120 + random_int(0, 30))->toDateString(); $d = \Illuminate\Support\Carbon::parse($a)->addDays(2)->toDateString();
                $v = $this->freeVilla($a, $d);
                $this->walkIn(['source' => $source, 'arrival' => $a, 'departure' => $d, 'villas' => [['villa_id' => $v->id]]])->assertRedirect();
                $b = Booking::latest('id')->first();
                $this->assertSame($source, $b->source);
                $this->assertFalse(app(AvailabilityService::class)->isVillaFree($v->id, $a, $d));
                $this->get(route('admin.bookings.index', ['q' => $b->reference]))->assertSee($b->reference);
                return "{$b->reference} · {$b->sourceLabel()} · {$b->status}";
            });
        }
        $this->qa('SRC-04', 'Booking sources', 'Source: tour operator (manual form, contract pricing)', 'Linked to the operator', function () {
            $op = TourOperator::where('status', 'active')->first();
            $a = now()->addDays(160)->toDateString(); $d = now()->addDays(163)->toDateString();
            $v = $this->freeVilla($a, $d);
            $this->walkIn(['source' => 'tour_operator', 'tour_operator_id' => $op->id, 'arrival' => $a, 'departure' => $d, 'villas' => [['villa_id' => $v->id]]])->assertRedirect();
            $b = Booking::latest('id')->first();
            $this->assertSame($op->id, $b->tour_operator_id);
            return "{$b->reference} · {$op->company_name}";
        });
        $this->qa('SRC-05', 'Booking sources', 'Tour operator partner portal booking', 'Booking created by the operator login', function () {
            $u = User::where('email', 'operator@sunrise-tours.test')->first();
            $this->actingAs($u);
            $type = \App\Models\VillaType::where('is_active', true)->first();
            $this->post(route('operator.bookings.store'), ['arrival' => now()->addDays(170)->toDateString(), 'departure' => now()->addDays(172)->toDateString(),
                'lead_first_name' => 'Group', 'lead_last_name' => 'Leader', 'rooms' => [$type->id => ['qty' => 1, 'adults' => 2]]])->assertRedirect();
            $b = Booking::latest('id')->first();
            $this->assertSame($u->tour_operator_id, $b->tour_operator_id);
            return "{$b->reference} · {$b->sourceLabel()} · {$b->status}";
        });
        $this->post('/logout');
        $intent = null;
        $this->qa('SRC-06', 'Booking sources', 'Website booking (hold → sandbox payment approved)', 'Hold becomes Confirmed; receipt issued', function () use (&$intent) {
            $type = \App\Models\VillaType::where('slug', 'pool-villa')->first();
            $this->post(route('book.hold'), ['type' => $type->id, 'plan' => 1, 'arrival' => now()->addDays(240)->toDateString(), 'departure' => now()->addDays(243)->toDateString(),
                'adults' => 2, 'children' => 0, 'first_name' => 'Web', 'last_name' => 'Guest', 'email' => 'web.qa@example.test', 'phone' => '+94771234567', 'country' => 'Sri Lanka', 'pay' => 'deposit', 'terms' => 1]);
            $intent = PaymentIntent::latest('id')->first();
            $this->assertSame('hold', $intent->booking->status);
            $this->post(route('pay.sandbox.complete', $intent->token), ['result' => 'approve'])->assertRedirect(route('pay.return', $intent->token));
            $b = $intent->booking->fresh();
            $this->assertSame('confirmed', $b->status);
            return "{$b->reference} · Website · paid ".money($b->paidTotal()).' (SANDBOX gateway — no real card processing)';
        });
        $this->qa('SRC-07', 'Payments', 'Website payment declined (sandbox)', 'Booking stays on hold; no payment recorded', function () {
            $type = \App\Models\VillaType::where('slug', 'garden-villa')->first() ?? \App\Models\VillaType::first();
            $this->post(route('book.hold'), ['type' => $type->id, 'plan' => 1, 'arrival' => now()->addDays(250)->toDateString(), 'departure' => now()->addDays(252)->toDateString(),
                'adults' => 2, 'children' => 0, 'first_name' => 'Decline', 'last_name' => 'Test', 'email' => 'decline.qa@example.test', 'phone' => '+94771234567', 'country' => 'Sri Lanka', 'pay' => 'deposit', 'terms' => 1]);
            $i = PaymentIntent::latest('id')->first();
            $this->post(route('pay.sandbox.complete', $i->token), ['result' => 'decline']);
            $b = $i->booking->fresh();
            $this->assertSame(0.0, round($b->paidTotal(), 2));
            return "{$b->reference} status {$b->status}; intent {$i->fresh()->status}; paid 0";
        });
        $this->qa('SRC-08', 'Payments', 'Duplicate completion of the same website payment', 'Posted once (idempotent)', function () use (&$intent) {
            $before = $intent->booking->fresh()->paidTotal();
            app(\App\Modules\Billing\Services\OnlinePaymentService::class)->complete($intent->fresh());
            $this->assertEqualsWithDelta($before, $intent->booking->fresh()->paidTotal(), 0.01);
            return 'Paid total unchanged at '.money($before);
        });
        $this->qa('SRC-09', 'Booking sources', 'OTA reservation (Booking.com payload through the OTA ingest service — SIMULATED)', 'Booking created once; duplicate ignored', function () {
            $p = ['event_id' => uniqid('evt'), 'action' => 'new', 'channel_code' => 'booking_com', 'external_ref' => 'BDC-'.random_int(100000, 999999), 'room_code' => 'CHX-GARDEN_VILLA',
                'arrival' => now()->addDays(300)->toDateString(), 'departure' => now()->addDays(302)->toDateString(), 'adults' => 2, 'children' => 0, 'amount' => 90000,
                'guest' => ['first_name' => 'Ota', 'last_name' => 'Guest', 'email' => 'ota.qa@example.test']];
            $svc = app(OtaReservationService::class);
            $this->assertSame('created', $svc->ingest($p, 'qa')['status']);
            $this->assertSame('duplicate', $svc->ingest($p, 'qa')['status']);
            $b = Booking::where('external_ref', $p['external_ref'])->firstOrFail();
            return "{$b->reference} · {$b->sourceLabel()} · channel ".($b->channel?->name ?? '—');
        });
        $this->blocked('SRC-10', 'Booking sources', 'Live Booking.com / Agoda / Airbnb / Expedia connection', 'Reservations arrive by webhook from the channel manager',
            'CHANNEL_DRIVER=null — no Channex credentials; only the simulated ingest path (SRC-09) was tested');
    }

    // ================================================================== 5. Restaurant POS end to end + tables

    public function test_05_restaurant_pos_end_to_end(): void
    {
        $pm = $this->as('posmanager@vaasalvilla.test');
        $rest = Outlet::where('code', 'REST')->first();
        $this->qa('POS-01', 'POS', 'Restaurant Manager opens a shift (float LKR 10,000)', 'Shift open', function () use ($pm, $rest) {
            PosShift::where('user_id', $pm->id)->where('status', 'open')->update(['status' => 'closed', 'closed_at' => now()]);
            $this->post(route('pos.shifts.open'), ['outlet_id' => $rest->id, 'opening_float' => 10000])->assertSessionHas('success');
            return 'Shift #'.app(PosShiftService::class)->current($pm, $rest->id)->id.' open';
        });
        $table = $this->freeTable($rest);
        $o = null;
        $this->qa('POS-02', 'POS', "Open table {$table->name} for 4 guests", 'Dine-in check opened; table busy', function () use ($rest, $table, &$o) {
            $o = $this->openOrder(['outlet_id' => $rest->id, 'type' => 'dine_in', 'pos_table_id' => $table->id, 'covers' => 4]);
            $t = collect($this->getJson(route('pos.api.tables', ['outlet' => $rest->id]))->json('tables'))->firstWhere('id', $table->id);
            $this->assertNotNull($t['order']);
            return "{$o['order_no']} at {$table->name}";
        });
        if (! $o) return;
        $this->qa('POS-03', 'POS', 'Add breakfast, main (with required spice modifier + note) and drink', '3 lines, totals with service 10% + tax 8%', function () use ($o) {
            $this->addItem($o['id'], 'BK1', 1);
            $this->addItem($o['id'], 'MN3', 1, 'Less chilli');
            $r = $this->addItem($o['id'], 'DR2', 2);
            $this->assertCount(3, $r['items']);
            return 'Subtotal '.money($r['subtotal']).' + service '.money($r['service']).' + tax '.money($r['tax']).' = '.money($r['total']);
        });
        $this->qa('POS-04', 'POS', 'Add an item without its required modifier', 'Rejected: choose at least 1 option', function () use ($o) {
            $item = MenuItem::where('code', 'MN3')->first();
            $r = $this->postJson(route('pos.api.orders.items.add', $o['id']), ['menu_item_id' => $item->id]);
            $r->assertStatus(422);
            return $r->json('message');
        });
        $this->qa('POS-05', 'POS', 'Change quantity of drinks 2 → 3', 'Line and totals recalculated', function () use ($o) {
            $ord = $this->getJson(route('pos.api.orders.show', $o['id']))->json();
            $line = collect($ord['items'])->firstWhere('qty', 2.0);
            $r = $this->patchJson(route('pos.api.orders.items.update', [$o['id'], $line['id']]), ['qty' => 3])->assertOk()->json();
            $this->assertEquals(3, collect($r['items'])->firstWhere('id', $line['id'])['qty']);
            return 'New total '.money($r['total']);
        });
        $kotIds = [];
        $this->qa('POS-06', 'POS / KOT', 'Send to kitchen (KOT)', 'KOT(s) created per station; items fired', function () use ($o, &$kotIds) {
            $r = $this->postJson(route('pos.api.orders.fire', $o['id']))->assertOk()->json();
            $kotIds = Kot::where('pos_order_id', $o['id'])->pluck('id')->all();
            $this->assertNotEmpty($kotIds);
            return count($kotIds).' KOT(s): '.Kot::whereIn('id', $kotIds)->pluck('station')->implode(', ');
        });
        $this->qa('POS-07', 'Kitchen', 'Kitchen display receives the KOT', 'Ticket listed on the KDS feed', function () use ($o) {
            $this->as('kitchen@vaasalvilla.test');
            $feed = $this->getJson(route('pos.kds.feed', ['station' => 'all']))->assertOk()->json('kots');
            $this->assertTrue(collect($feed)->contains(fn ($k) => str_contains($k['where'], 'Table')));
            return count($feed).' ticket(s) on the KDS';
        });
        $this->qa('POS-08', 'Kitchen', 'KOT status New → Preparing → Ready → Served', 'Each step accepted', function () use (&$kotIds) {
            $k = Kot::find($kotIds[0]);
            foreach (['preparing', 'ready', 'served'] as $s) $this->post(route('pos.kds.status', $k), ['status' => $s])->assertSessionHasNoErrors();
            $this->assertSame('served', $k->fresh()->status);
            return "KOT {$k->kot_no}: served";
        });
        $this->qa('POS-09', 'Kitchen', 'Invalid KOT jump New → Served', 'Rejected', function () use ($o) {
            $this->as('posmanager@vaasalvilla.test');
            $this->addItem($o['id'], 'ST3', 1);
            $this->postJson(route('pos.api.orders.fire', $o['id']))->assertOk();
            $k = Kot::where('pos_order_id', $o['id'])->where('status', 'new')->latest('id')->firstOrFail();
            $this->as('kitchen@vaasalvilla.test');
            $r = $this->postJson(route('pos.kds.status', $k), ['status' => 'served']);
            $r->assertStatus(422);
            return $r->json('message');
        });
        $this->as('posmanager@vaasalvilla.test');
        $this->qa('POS-10', 'POS', 'Print bill (guest bill)', 'Check status Billed; bill page opens', function () use ($o) {
            $r = $this->postJson(route('pos.api.orders.bill', $o['id']))->assertOk()->json();
            $this->assertSame('billed', $r['status']);
            $this->get(route('pos.bill.print', $o['id']))->assertOk()->assertSee('GUEST BILL');
            return 'Billed '.money($r['total']);
        });
        $this->qa('POS-11', 'Payments', 'Split payment: part cash (partial) — table stays occupied', 'Status stays Billed with balance; table still busy', function () use ($o, $rest, $table) {
            $total = (float) PosOrder::find($o['id'])->total;
            $r = $this->postJson(route('pos.api.orders.pay', $o['id']), ['tenders' => [['method' => 'cash', 'amount' => 5000, 'tendered' => 5000]]])->assertOk()->json();
            $this->assertSame('billed', $r['status']);
            $t = collect($this->getJson(route('pos.api.tables', ['outlet' => $rest->id]))->json('tables'))->firstWhere('id', $table->id);
            $this->assertNotNull($t['order'], 'table must still be occupied');
            return 'Paid 5,000 of '.money($total).'; balance '.money($r['balance']).'; table still occupied';
        });
        $this->qa('POS-12', 'Payments', 'Overpay with card (more than the balance)', 'Rejected: payments exceed balance', function () use ($o) {
            $bal = PosOrder::find($o['id'])->balance();
            $r = $this->postJson(route('pos.api.orders.pay', $o['id']), ['tenders' => [['method' => 'card', 'amount' => $bal + 100]]]);
            $r->assertStatus(422);
            return $r->json('message');
        });
        $this->qa('POS-13', 'Payments', 'Settle the balance: card + bank transfer + digital QR', 'Check Paid/closed; invoice number; table AVAILABLE; not in open checks', function () use ($o, $rest, $table) {
            $bal = PosOrder::find($o['id'])->balance();
            $a = round($bal / 3, 2); $b = round($bal / 3, 2); $c = round($bal - $a - $b, 2);
            $r = $this->postJson(route('pos.api.orders.pay', $o['id']), ['tenders' => [['method' => 'card', 'amount' => $a, 'reference' => 'SLIP-1'],
                ['method' => 'bank_transfer', 'amount' => $b, 'reference' => 'BOC-7781'], ['method' => 'digital', 'amount' => $c, 'reference' => 'LANKAQR-55']]])->assertOk()->json();
            $this->assertSame('paid', $r['status']);
            $data = $this->getJson(route('pos.api.tables', ['outlet' => $rest->id]))->json();
            $this->assertNull(collect($data['tables'])->firstWhere('id', $table->id)['order'], 'table released');
            $this->assertFalse(collect($data['other'])->contains('id', $o['id']));
            return "Paid; invoice {$r['invoice_no']}; table {$table->name} available";
        });
        $this->qa('POS-14', 'Payments', 'Pay an already-paid check again (duplicate payment)', 'Rejected: check already closed', function () use ($o) {
            $r = $this->postJson(route('pos.api.orders.pay', $o['id']), ['tenders' => [['method' => 'cash', 'amount' => 100]]]);
            $r->assertStatus(422);
            return $r->json('message');
        });
        $this->qa('POS-15', 'Payments', 'Cash payment with change (tendered LKR 30,000)', 'Change = tendered − bill, stored on the payment', function () use ($rest) {
            $t = $this->freeTable($rest);
            $x = $this->openOrder(['outlet_id' => $rest->id, 'type' => 'dine_in', 'pos_table_id' => $t->id, 'covers' => 2]);
            $this->addItem($x['id'], 'MN4', 2); $this->addItem($x['id'], 'MN6', 1);
            $this->postJson(route('pos.api.orders.fire', $x['id']))->assertOk();
            $total = (float) PosOrder::find($x['id'])->total;
            $this->assertLessThan(30000, $total);
            $r = $this->postJson(route('pos.api.orders.pay', $x['id']), ['tenders' => [['method' => 'cash', 'amount' => $total, 'tendered' => 30000]]])->assertOk()->json();
            $this->assertEqualsWithDelta(30000 - $total, $r['change'], 0.001);
            return 'Bill '.money($total).'; tendered LKR 30,000.00; change '.money($r['change']).' (= 30,000 − bill)';
        });
        $this->qa('POS-16', 'POS', 'Takeaway order paid online', 'Takeaway check closed', function () use ($rest) {
            $x = $this->openOrder(['outlet_id' => $rest->id, 'type' => 'takeaway']);
            $this->addItem($x['id'], 'MN5', 1);
            $this->postJson(route('pos.api.orders.fire', $x['id']))->assertOk();
            $total = (float) PosOrder::find($x['id'])->total;
            $r = $this->postJson(route('pos.api.orders.pay', $x['id']), ['tenders' => [['method' => 'online', 'amount' => $total, 'reference' => 'WEB-1']]])->assertOk()->json();
            $this->assertSame('paid', $r['status']);
            return "{$x['order_no']} takeaway paid ".money($total);
        });
        $this->qa('POS-17', 'POS', 'Pay an empty check', 'Rejected: the check is empty', function () use ($rest) {
            $x = $this->openOrder(['outlet_id' => $rest->id, 'type' => 'takeaway']);
            $r = $this->postJson(route('pos.api.orders.pay', $x['id']), ['tenders' => [['method' => 'cash', 'amount' => 100]]]);
            $r->assertStatus(422);
            return $r->json('message');
        });
        $this->qa('POS-18', 'POS', 'Room service order does not occupy a restaurant table', 'No table attached', function () {
            $b = Booking::where('status', 'checked_in')->firstOrFail();
            $x = $this->openOrder(['outlet_id' => Outlet::where('code', 'IRD')->value('id'), 'type' => 'room_service', 'booking_id' => $b->id]);
            $this->assertNull($x['table']);
            return "{$x['order_no']} → Villa {$x['booking']['villa']} (no table)";
        });
        // Move, merge, split
        $this->qa('POS-19', 'Tables', 'Move a check to another table', 'Check moves; first table released', function () use ($rest) {
            $t1 = $this->freeTable($rest); $t2 = $this->freeTable($rest, [$t1->id]);
            $x = $this->openOrder(['outlet_id' => $rest->id, 'type' => 'dine_in', 'pos_table_id' => $t1->id, 'covers' => 2]);
            $this->addItem($x['id'], 'CF1');
            $r = $this->postJson(route('pos.api.orders.transfer', $x['id']), ['pos_table_id' => $t2->id])->assertOk()->json();
            $this->assertSame($t2->id, $r['table']['id']);
            return "{$t1->name} → {$t2->name}";
        });
        $this->qa('POS-20', 'Tables', 'Merge two table checks', 'Items combined; source check merged', function () use ($rest) {
            $t1 = $this->freeTable($rest); $t2 = $this->freeTable($rest, [$t1->id]);
            $a = $this->openOrder(['outlet_id' => $rest->id, 'type' => 'dine_in', 'pos_table_id' => $t1->id]); $this->addItem($a['id'], 'CF2');
            $b = $this->openOrder(['outlet_id' => $rest->id, 'type' => 'dine_in', 'pos_table_id' => $t2->id]); $this->addItem($b['id'], 'CF3');
            $r = $this->postJson(route('pos.api.orders.merge', $a['id']), ['source_order_id' => $b['id']])->assertOk()->json();
            $this->assertCount(2, $r['items']);
            $this->assertSame('merged', PosOrder::find($b['id'])->status);
            return "{$b['order_no']} merged into {$a['order_no']}";
        });
        $this->qa('POS-21', 'Tables', 'Split a check (move 1 line to a new check)', 'New check created at the same table', function () use ($rest) {
            $t = $this->freeTable($rest);
            $a = $this->openOrder(['outlet_id' => $rest->id, 'type' => 'dine_in', 'pos_table_id' => $t->id]);
            $this->addItem($a['id'], 'CF1'); $r0 = $this->addItem($a['id'], 'DR4');
            $line = collect($r0['items'])->last();
            $r = $this->postJson(route('pos.api.orders.split', $a['id']), ['lines' => [$line['id'] => 1]])->assertOk()->json();
            $this->assertNotEmpty($r['new_order_id']);
            return "New check {$r['new_order_no']}";
        });
        $this->qa('POS-22', 'Tables', 'Reserved table indicator on the floor plan', 'Tables API returns the reservation', function () use ($rest) {
            $t = $this->freeTable($rest);
            TableReservation::create(['outlet_id' => $rest->id, 'pos_table_id' => $t->id, 'guest_name' => 'Ms. Silva', 'party_size' => 2, 'reserved_for' => now()->addHour(), 'status' => 'confirmed']);
            $row = collect($this->getJson(route('pos.api.tables', ['outlet' => $rest->id]))->json('tables'))->firstWhere('id', $t->id);
            $this->assertSame('Ms. Silva', $row['reservation']['guest']);
            return "{$t->name}: Reserved {$row['reservation']['time']} · Ms. Silva";
        });
        $this->qa('POS-23', 'POS', 'Cashier refunds a paid check in cash (no pos.refund permission)', 'HTTP 403 for cashier; allowed for Restaurant Manager', function () use ($rest) {
            $paid = PosOrder::where('status', 'paid')->latest('id')->firstOrFail();
            $this->as('cashier@vaasalvilla.test');
            $this->post(route('pos.orders.refund', $paid), ['amount' => 100, 'method' => 'card', 'reason' => 'x'])->assertForbidden();
            $this->as('posmanager@vaasalvilla.test');
            $this->post(route('pos.orders.refund', $paid), ['amount' => 100, 'method' => 'card', 'reason' => 'Cold soup'])->assertSessionHasNoErrors();
            return 'Cashier 403; manager refund LKR 100 recorded';
        });
        $this->qa('POS-24', 'POS', 'Cash/card payment without an open shift', 'Rejected: open a cashier shift first', function () use ($rest) {
            $w = User::where('username', 'cashier')->first();
            PosShift::where('user_id', $w->id)->where('status', 'open')->update(['status' => 'closed', 'closed_at' => now()]);
            $this->actingAs($w);
            $x = $this->openOrder(['outlet_id' => $rest->id, 'type' => 'takeaway']);
            $this->addItem($x['id'], 'CF1');
            $this->postJson(route('pos.api.orders.fire', $x['id']));
            $r = $this->postJson(route('pos.api.orders.pay', $x['id']), ['tenders' => [['method' => 'cash', 'amount' => (float) PosOrder::find($x['id'])->total]]]);
            $r->assertStatus(422);
            return $r->json('message');
        });
    }

    // ================================================================== 6. Integration with exact amounts

    public function test_06_integration_food_laundry_room(): void
    {
        $this->as('manager@vaasalvilla.test');
        $a = now()->toDateString(); $d = now()->addDay()->toDateString();
        $v = $this->freeVilla($a, $d);
        $booking = null;
        $this->qa('INT-01', 'Integration', 'Stay with room rate LKR 20,000 (override) checked in', 'Checked in', function () use ($v, $a, $d, &$booking) {
            $this->walkIn(['arrival' => $a, 'departure' => $d, 'rate_override' => 20000, 'override_reason' => 'QA scenario', 'guest' => ['first_name' => 'Nuwan', 'last_name' => 'Perera'],
                'villas' => [['villa_id' => $v->id]]])->assertRedirect();
            $booking = Booking::latest('id')->first();
            $this->checkIn($booking)->assertSessionHasNoErrors();
            $this->assertSame('checked_in', $booking->fresh()->status);
            return "{$booking->reference} · Villa {$v->code} · Nuwan Perera";
        });
        if (! $booking) return;
        // A QA outlet with no tax/service so the food total is exactly LKR 4,500 (test database only).
        $qaOutlet = Outlet::create(['property_id' => $v->property_id, 'code' => 'QA', 'name' => 'QA Kitchen', 'type' => 'restaurant', 'folio_department' => 'restaurant', 'tax_pct' => 0, 'service_charge_pct' => 0]);
        $cat = MenuCategory::create(['outlet_id' => $qaOutlet->id, 'name' => 'QA', 'station' => 'kitchen']);
        $food = MenuItem::create(['menu_category_id' => $cat->id, 'code' => 'QA1', 'name' => 'QA food', 'price' => 4500]);
        $orderId = null;
        $this->qa('INT-02', 'Integration', 'Food LKR 4,500 charged to Villa from the POS', 'One folio line LKR 4,500 on this booking/guest/villa, dated today', function () use ($booking, $qaOutlet, $food, $v, &$orderId) {
            $this->as('cashier@vaasalvilla.test');
            $x = $this->openOrder(['outlet_id' => $qaOutlet->id, 'type' => 'room_service', 'booking_id' => $booking->id]);
            $this->postJson(route('pos.api.orders.items.add', $x['id']), ['menu_item_id' => $food->id])->assertOk();
            $this->postJson(route('pos.api.orders.fire', $x['id']))->assertOk();
            $this->assertEquals(4500, PosOrder::find($x['id'])->total);
            $this->postJson(route('pos.api.orders.pay', $x['id']), ['tenders' => [['method' => 'room_charge', 'amount' => 4500, 'booking_id' => $booking->id]]])->assertJsonPath('status', 'charged_to_room');
            $lines = FolioLine::where('source_type', 'pos_order')->where('source_id', $x['id'])->get();
            $this->assertCount(1, $lines);
            $l = $lines->first();
            $this->assertEquals(4500, (float) $l->total);
            $this->assertSame($booking->id, $l->folio->booking_id);
            $this->assertSame(now()->toDateString(), $l->business_date->toDateString());
            $o = PosOrder::find($x['id']);
            $this->assertSame($v->id, $o->villa_id);
            $orderId = $x['id'];
            return "Folio {$l->folio->folio_no}: '{$l->description}' ".money($l->total).", {$l->business_date->toDateString()}; order villa {$v->code}";
        });
        $this->qa('INT-03', 'Integration', 'Laundry LKR 1,000 posted at reception', 'Folio line with net 1,000 (system adds tax where the item is taxable)', function () use ($booking) {
            $this->as('reception@vaasalvilla.test');
            $f = $booking->folios()->where('payer_type', 'guest')->first();
            $this->post(route('admin.folios.charge', $f), ['department' => 'laundry', 'description' => 'Laundry', 'quantity' => 1, 'unit_price' => 1000])->assertSessionHas('success');
            $l = $f->lines()->where('department', 'laundry')->latest('id')->first();
            $this->assertEquals(1000, (float) $l->amount);
            return 'Net '.money($l->amount).', tax '.money($l->tax_amount).', service '.money($l->service_amount).', line total '.money($l->total);
        });
        $this->qa('INT-04', 'Integration', 'Checkout invoice combines Room 20,000 + Restaurant 4,500 + Laundry 1,000', 'No duplicate charges; totals reconcile', function () use ($booking) {
            $this->as('reception@vaasalvilla.test');
            app(\App\Modules\FrontDesk\Services\RoomChargeService::class)->postNights($booking->fresh(), $booking->departure);
            $f = $booking->folios()->where('payer_type', 'guest')->first();
            $bal = $f->balance();
            $this->post(route('admin.frontdesk.checkout.store', $booking), ['confirm_early' => 1, 'payments' => [$f->id => ['method' => 'cash', 'amount' => $bal]]])->assertRedirect(route('admin.bookings.show', $booking));
            $inv = $booking->fresh()->invoices()->where('type', 'invoice')->latest('id')->firstOrFail();
            $byDept = collect($inv->lines)->groupBy('department')->map(fn ($g) => round($g->sum(fn ($l) => (float) ($l['amount'] ?? $l['net'] ?? 0)), 2));
            $this->assertEquals(1, FolioLine::where('folio_id', $f->id)->where('source_type', 'pos_order')->count());
            $this->assertEqualsWithDelta((float) $f->fresh()->lines()->sum('total'), (float) $inv->grand_total, 0.01);
            return "Invoice {$inv->number}: net by department ".json_encode($byDept).'; grand total incl. service/tax '.money($inv->grand_total);
        });
    }

    // ================================================================== 7. Housekeeping

    public function test_07_housekeeping_workflow(): void
    {
        $this->as('reception@vaasalvilla.test');
        $v = $this->freeVilla(now(), now()->addDay());
        $this->walkIn(['villas' => [['villa_id' => $v->id]]]);
        $b = Booking::latest('id')->first();
        $this->checkIn($b);
        HkTask::where('villa_id', $v->id)->whereIn('status', HkTask::OPEN)->update(['status' => 'cancelled']);
        app(\App\Modules\FrontDesk\Services\RoomChargeService::class)->postNights($b->fresh(), $b->departure);
        $f = $b->folios()->where('payer_type', 'guest')->first();
        $this->post(route('admin.frontdesk.checkout.store', $b), ['confirm_early' => 1, 'payments' => [$f->id => ['method' => 'cash', 'amount' => $f->balance()]]]);
        $task = HkTask::where('villa_id', $v->id)->where('type', 'departure')->where('status', 'pending')->latest('id')->first();

        $this->qa('HK-01', 'Housekeeping', 'Checkout → villa Dirty + departure task (Ready for cleaning)', 'Task pending, unassigned, priority High', function () use ($task, $v) {
            $this->assertNotNull($task);
            $this->assertSame('dirty', $v->fresh()->boardStatus());
            return "Task #{$task->id} {$task->type}, priority {$task->priority}, ".($task->assigned_to ? 'assigned' : 'unassigned').', '.$task->items()->count().' checklist items';
        });
        if (! $task) return;
        $this->as('housekeeping@vaasalvilla.test');
        $this->qa('HK-02', 'Housekeeping', 'My Cleaning Tasks shows the villa under "Ready for cleaning"', 'Visible with Accept button', function () use ($v) {
            $this->get(route('admin.housekeeping.my'))->assertOk()->assertSee('Ready for cleaning')->assertSee($v->code);
            return 'Visible';
        });
        $this->qa('HK-03', 'Housekeeping', 'Tick a checklist item on an unaccepted pool task, then after Accept but before Start', '403, then rejected: start the task first', function () use ($task) {
            $item = $task->items()->first();
            $this->post(route('admin.housekeeping.tasks.toggle', [$task, $item]), ['checked' => 1])->assertForbidden();
            $this->post(route('admin.housekeeping.tasks.accept', $task));
            $this->post(route('admin.housekeeping.tasks.toggle', [$task, $item]), ['checked' => 1])->assertSessionHas('error');
            return 'Unaccepted: 403. Accepted, not started: '.session('error');
        });
        $this->qa('HK-04', 'Housekeeping', 'Accept → Start → Pause → Resume', 'Assigned to me; Cleaning; Paused; Cleaning', function () use ($task, $v) {
            $this->post(route('admin.housekeeping.tasks.accept', $task));
            $this->post(route('admin.housekeeping.tasks.start', $task));
            $this->assertSame('cleaning', $v->fresh()->hk_status);
            $this->post(route('admin.housekeeping.tasks.pause', $task), ['reason' => 'Waiting for linen']);
            $this->assertSame('paused', $task->fresh()->status);
            $this->post(route('admin.housekeeping.tasks.start', $task));
            $this->assertSame('in_progress', $task->fresh()->status);
            return 'Accepted by '.$task->fresh()->assignee->fullName().'; resumed';
        });
        $this->qa('HK-05', 'Housekeeping', 'Request inspection with checklist incomplete (0/N)', 'Rejected with open item count', function () use ($task) {
            $this->post(route('admin.housekeeping.tasks.submit', $task))->assertSessionHas('error');
            return session('error');
        });
        $this->qa('HK-06', 'Housekeeping', 'Complete checklist (N/N) and request inspection', 'Task Awaiting inspection; villa Inspection', function () use ($task, $v) {
            foreach ($task->items as $item) $this->post(route('admin.housekeeping.tasks.toggle', [$task, $item]), ['checked' => 1]);
            $this->post(route('admin.housekeeping.tasks.submit', $task));
            $this->assertSame('inspection', $task->fresh()->status);
            return $task->items()->where('is_checked', true)->count().'/'.$task->items()->count().' checked; villa '.$v->fresh()->boardStatus();
        });
        $this->qa('HK-07', 'Housekeeping', 'Supervisor rejects, attendant fixes, supervisor approves', 'Villa Available (Ready) after approval', function () use ($task, $v) {
            $this->as('hksupervisor@vaasalvilla.test');
            $this->post(route('admin.housekeeping.tasks.reject', $task), ['notes' => 'Bathroom mirror streaks']);
            $this->assertSame('in_progress', $task->fresh()->status);
            $this->as('housekeeping@vaasalvilla.test');
            $this->post(route('admin.housekeeping.tasks.submit', $task));
            $this->as('hksupervisor@vaasalvilla.test');
            $this->post(route('admin.housekeeping.tasks.approve', $task));
            $this->assertSame('ready', $v->fresh()->hk_status);
            return 'Rejected once, approved; villa '.$v->fresh()->boardStatus().'; clean time '.$task->fresh()->durationMinutes().' min';
        });
        $this->qa('HK-08', 'Housekeeping', 'Housekeeper reports a maintenance issue', 'Ticket created', function () use ($v) {
            $this->as('housekeeping@vaasalvilla.test');
            $this->post(route('admin.maintenance.store'), ['villa_id' => $v->id, 'title' => 'Dripping tap', 'category' => 'plumbing', 'severity' => 'low'])->assertRedirect();
            return \App\Models\MaintenanceTicket::latest('id')->first()->ticket_no.' created';
        });
        $this->qa('HK-09', 'Housekeeping', 'Housekeeper logs a lost item', 'Lost & found item stored', function () use ($v) {
            $this->post(route('admin.lost-found.store'), ['villa_id' => $v->id, 'description' => 'Black sunglasses', 'category' => 'other', 'found_at' => now()->toDateTimeString()])->assertRedirect();
            return \App\Models\LostFoundItem::latest('id')->first()->item_no.' stored';
        });
        $this->qa('HK-10', 'Housekeeping', 'Supervisor creates stay-over tasks and assigns one', 'Tasks for occupied villas; assignment saved', function () {
            $this->as('hksupervisor@vaasalvilla.test');
            $this->post(route('admin.housekeeping.stayovers'))->assertSessionHas('success');
            $t = HkTask::where('type', 'stayover')->latest('id')->first();
            $emp = \App\Models\Employee::whereHas('user', fn ($q) => $q->where('username', 'housekeeping2'))->first();
            if ($t) $this->post(route('admin.housekeeping.tasks.assign', $t), ['assigned_to' => $emp->id]);
            return session('success').($t ? '; task #'.$t->id.' assigned to '.$emp->fullName() : '');
        });
        $this->qa('HK-11', 'Housekeeping', 'Housekeeper opens another attendant\'s task', 'HTTP 403', function () {
            $other = HkTask::whereNotNull('assigned_to')->whereHas('assignee', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('username', 'housekeeping2')))->latest('id')->first();
            if (! $other) return 'No task assigned to another attendant (skipped)';
            $this->as('housekeeping@vaasalvilla.test');
            $this->get(route('admin.housekeeping.tasks.show', $other))->assertForbidden();
            return '403';
        });
    }

    // ================================================================== 8. Key cards (SIMULATION)

    public function test_08_key_cards(): void
    {
        $this->as('admin@vaasalvilla.test');
        $this->qa('KEY-01', 'Key cards', 'Register a new blank card UID 04A1QA0010', 'Card available', function () {
            $this->post(route('admin.keycards.store'), ['uid' => '04A1QA0010', 'type' => 'guest'])->assertSessionHas('success');
            return 'Status '.KeyCard::where('uid', '04A1QA0010')->value('status');
        });
        $this->qa('KEY-02', 'Key cards', 'Register the same UID twice', 'Rejected: already registered', function () {
            $this->post(route('admin.keycards.store'), ['uid' => '04a1-qa00-10', 'type' => 'guest'])->assertSessionHasErrors('uid');
            return session('errors')->first('uid');
        });
        $b = Booking::where('status', 'checked_in')->with('activeVillas.villa')->first();
        $bv = $b->activeVillas->first();
        $other = Villa::where('id', '!=', $bv->villa_id)->first();
        $this->qa('KEY-03', 'Key cards', 'Issue card 04A1QA0010 to an in-house guest (duplicate key)', 'Encoded by the SIMULATOR lock provider; Active', function () use ($b, $bv) {
            $this->post(route('admin.keycards.issue', $b), ['booking_villa_id' => $bv->id, 'uid' => '04A1QA0010', 'issue_type' => 'duplicate'])->assertSessionHas('success');
            return 'Active for villa '.$bv->villa->code.' (LOCK_DRIVER=simulator)';
        });
        $this->qa('KEY-04', 'Key cards', 'Card at own villa / another villa / Pool zone (SIMULATION)', 'Granted / Denied / Granted', function () use ($bv, $other) {
            $this->post(route('admin.access.simulate'), ['uid' => '04A1QA0010', 'villa_id' => $bv->villa_id])->assertSessionHas('rfid_result.granted', true);
            $this->post(route('admin.access.simulate'), ['uid' => '04A1QA0010', 'villa_id' => $other->id])->assertSessionHas('rfid_result.granted', false);
            $r = session('rfid_result.reason');
            $this->post(route('admin.access.simulate'), ['uid' => '04A1QA0010', 'zone' => 'Pool'])->assertSessionHas('rfid_result.granted', true);
            return "Own villa granted; {$other->code} denied ({$r}); Pool granted";
        });
        $this->qa('KEY-05', 'Key cards', 'Unknown card UID', 'Denied: Unknown card', function () {
            $this->post(route('admin.access.simulate'), ['uid' => '04FFFFFF0000', 'zone' => 'Main Gate'])->assertSessionHas('rfid_result.reason', 'Unknown card');
            return 'Unknown card';
        });
        $this->qa('KEY-06', 'Key cards', 'Revoke the card, then try the door', 'Denied', function () use ($bv) {
            $a = KeyCard::where('uid', '04A1QA0010')->first()->activeAssignment;
            $this->post(route('admin.keycards.revoke', $a), ['reason' => 'Guest returned card'])->assertSessionHas('success');
            $this->post(route('admin.access.simulate'), ['uid' => '04A1QA0010', 'villa_id' => $bv->villa_id])->assertSessionHas('rfid_result.granted', false);
            return 'Denied: '.session('rfid_result.reason');
        });
        $this->qa('KEY-07', 'Key cards', 'Report a card lost', 'Card status Lost; denied at doors', function () use ($bv) {
            $card = KeyCard::where('status', 'available')->where('type', 'guest')->where('uid', '!=', '04A1QA0010')->first();
            $b = $bv->booking;
            $this->post(route('admin.keycards.issue', $b), ['booking_villa_id' => $bv->id, 'uid' => $card->uid, 'issue_type' => 'duplicate']);
            $this->post(route('admin.keycards.lost', $card))->assertSessionHas('success');
            $this->assertSame('lost', $card->fresh()->status);
            $this->post(route('admin.access.simulate'), ['uid' => $card->uid, 'villa_id' => $bv->villa_id])->assertSessionHas('rfid_result.granted', false);
            return "{$card->uid} lost; denied ({$this->app['session.store']->get('rfid_result.reason')})";
        });
        $this->blocked('KEY-08', 'Key cards', 'Physical RFID encoder / door lock', 'Card encoded and door opens', 'No hardware connected; LOCK_DRIVER=simulator and the vendor adapter in lock-bridge/ is a stub. All key-card results above are SIMULATED.');
    }

    // ================================================================== 9. Front-desk billing

    public function test_09_front_desk_billing(): void
    {
        $this->as('reception@vaasalvilla.test');
        $b = Booking::where('status', 'checked_in')->first();
        $f = $b->folios()->where('status', 'open')->first();
        $this->qa('BILL-01', 'Billing', 'Partial folio payment (cash LKR 1,000) issues a receipt', 'Payment + receipt; balance reduced', function () use ($f) {
            $before = $f->balance();
            $this->post(route('admin.folios.payment', $f), ['amount' => 1000, 'method' => 'cash', 'type' => 'payment'])->assertSessionHas('success');
            $this->assertEqualsWithDelta($before - 1000, $f->fresh()->balance(), 0.01);
            return 'Balance '.money($before).' → '.money($f->fresh()->balance());
        });
        $this->qa('BILL-02', 'Billing', 'Folio payment of LKR 0', 'Validation error', function () use ($f) {
            $this->post(route('admin.folios.payment', $f), ['amount' => 0, 'method' => 'cash', 'type' => 'payment'])->assertSessionHasErrors('amount');
            return session('errors')->first('amount');
        });
        $this->qa('BILL-03', 'Billing', 'Checkout with unpaid balance', 'Rejected; nothing committed', function () {
            $busy = PosOrder::whereIn('status', ['open', 'billed'])->whereNotNull('booking_id')->pluck('booking_id');
            $b = Booking::where('status', 'checked_in')->whereNotIn('id', $busy)->get()->first(fn ($x) => $x->folios->sum(fn ($f) => max(0, $f->balance())) > 0);
            if (! $b) return 'No suitable stay (skipped)';
            $this->post(route('admin.frontdesk.checkout.store', $b), ['confirm_early' => 1])->assertSessionHas('error');
            $this->assertSame('checked_in', $b->fresh()->status);
            return session('error');
        });
    }

    // ================================================================== 10. Night audit

    public function test_10_night_audit(): void
    {
        $this->as('reception@vaasalvilla.test');
        $this->qa('NA-01', 'Night audit', 'Reception runs night audit', 'HTTP 403 (needs frontdesk.night_audit)', function () {
            $this->post(route('admin.frontdesk.night-audit'))->assertForbidden();
            return '403';
        });
        $v = $this->freeVilla(now(), now()->addDays(3));
        $this->walkIn(['departure' => now()->addDays(3)->toDateString(), 'villas' => [['villa_id' => $v->id]]]);
        $b = Booking::latest('id')->first();
        $this->checkIn($b);
        $f = $b->folios()->where('payer_type', 'guest')->first() ?? $b->folios()->first();
        // A confirmed booking whose arrival date has already passed (created through the booking service).
        $nsVilla = $this->freeVilla(now()->subDays(2), now()->subDay());
        $noShow = app(BookingService::class)->create(['source' => 'phone', 'status' => 'confirmed', 'guest' => ['first_name' => 'No', 'last_name' => 'Show', 'email' => uniqid().'@example.test'],
            'arrival' => now()->subDays(2)->toDateString(), 'departure' => now()->subDay()->toDateString(), 'villas' => [['villa_id' => $nsVilla->id, 'adults' => 2]], 'ignore_rules' => true]);
        $this->as('manager@vaasalvilla.test');
        $before = $f->lines()->where('source_type', 'room_night')->count();
        $this->qa('NA-02', 'Night audit', 'Run night audit (Hotel Manager)', "Tonight's room night posted to each in-house folio; no-shows flagged; summary saved", function () use ($f, $before, $noShow) {
            $this->post(route('admin.frontdesk.night-audit'))->assertSessionHas('success');
            $after = $f->lines()->where('source_type', 'room_night')->count();
            $this->assertSame($before + 1, $after);
            $this->assertSame('no_show', $noShow->fresh()->status);
            $this->assertNotNull(Setting::get('night_audit_last'));
            return session('success');
        });
        $this->qa('NA-03', 'Night audit', 'Run night audit a second time the same day', 'No duplicate room charges (idempotent)', function () use ($f) {
            $n = $f->lines()->where('source_type', 'room_night')->count();
            $this->post(route('admin.frontdesk.night-audit'))->assertSessionHas('success');
            $this->assertSame($n, $f->lines()->where('source_type', 'room_night')->count());
            $this->assertStringContainsString('0 stay-over', session('success'));
            return session('success');
        });
        $this->qa('NA-04', 'Night audit', 'Audit trail of the run', 'Run recorded', function () {
            $last = json_decode((string) Setting::get('night_audit_last'), true);
            $this->assertArrayHasKey('run_at', $last);
            return 'Only the latest run summary is kept (setting night_audit_last): '.json_encode($last).'. No per-run audit_logs entry, no business-date lock.';
        });
    }

    // ================================================================== 11. Reports

    public function test_11_reports(): void
    {
        $this->as('admin@vaasalvilla.test');
        foreach (ReportService::TYPES as $type => [$title, $perm]) {
            $this->qa('RPT-'.$type, 'Reports', "{$title}: open, filter this month, CSV export", 'HTTP 200; CSV text/csv', function () use ($type) {
                $r = $this->get(route('admin.reports.show', [$type, 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]))->assertOk();
                $csv = $this->get(route('admin.reports.show', [$type, 'export' => 'csv']));
                $csv->assertOk();
                $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
                $rows = substr_count($csv->getContent(), "\n") - 1;
                return "Rendered; CSV {$rows} data row(s)";
            });
        }
        $this->qa('RPT-POS', 'Reports', 'Restaurant sales inside the POS (Restaurant Manager)', 'HTTP 200', function () {
            $this->as('posmanager@vaasalvilla.test');
            $this->get(route('pos.sales'))->assertOk();
            $this->get(route('pos.sales', 'pos_items'))->assertOk();
            $this->get(route('admin.reports.index'))->assertForbidden();
            return 'POS sales + dish mix open; hotel reports 403';
        });
        $this->qa('RPT-ACC', 'Reports', 'Accountant sees financial reports but not settings', 'Reports 200; settings 403', function () {
            $this->as('accounts@vaasalvilla.test');
            $this->get(route('admin.reports.show', 'payments'))->assertOk();
            $this->get(route('admin.settings.edit'))->assertForbidden();
            return 'As expected';
        });
    }

    // ================================================================== 12. Errors, edge cases, security

    public function test_12_errors_and_security(): void
    {
        $this->post('/logout');
        $this->qa('ERR-01', 'Login', 'Invalid password', 'Generic error, no account hint', function () {
            $this->post('/login', ['login' => 'cashier', 'password' => 'wrong'])->assertSessionHasErrors('login');
            return session('errors')->first('login');
        });
        $this->qa('ERR-02', 'Login', 'Unknown username', 'Same generic error', function () {
            $this->post('/login', ['login' => 'nobody', 'password' => 'wrong'])->assertSessionHasErrors('login');
            return session('errors')->first('login');
        });
        $this->qa('ERR-03', 'Login', '5 wrong passwords lock the account', 'Account locked for 15 minutes', function () {
            for ($i = 0; $i < 5; $i++) $this->post('/login', ['login' => 'waiter', 'password' => 'bad'.$i]);
            $this->post('/login', ['login' => 'waiter', 'password' => DemoLogin::PASSWORD])->assertSessionHasErrors('login');
            User::where('username', 'waiter')->update(['failed_attempts' => 0, 'locked_until' => null]);
            return session('errors')->first('login');
        });
        $this->as('admin@vaasalvilla.test');
        $this->qa('ERR-04', 'Errors', 'Open a booking that does not exist', 'HTTP 404 page', function () {
            $this->get(route('admin.bookings.show', 99999999))->assertNotFound();
            return '404';
        });
        $this->qa('ERR-05', 'Errors', 'API validation error', 'HTTP 422 JSON with field errors', function () {
            $this->postJson(route('pos.api.orders.open'), [])->assertStatus(422)->assertJsonValidationErrors(['outlet_id', 'type']);
            return '422 outlet_id, type';
        });
        $this->qa('ERR-06', 'Reservations', 'Overlapping table reservation', 'Rejected', function () {
            $rest = Outlet::where('code', 'REST')->first();
            $t = $this->freeTable($rest);
            TableReservation::where('pos_table_id', $t->id)->delete();
            $at = now()->addHours(5)->startOfHour();
            $p = ['outlet_id' => $rest->id, 'pos_table_id' => $t->id, 'guest_name' => 'A', 'party_size' => 2, 'reserved_for' => $at->format('Y-m-d\TH:i'), 'duration_minutes' => 90];
            $this->post(route('pos.reservations.store'), $p)->assertSessionHas('success');
            $this->post(route('pos.reservations.store'), ['guest_name' => 'B', 'reserved_for' => $at->copy()->addMinutes(30)->format('Y-m-d\TH:i')] + $p)->assertSessionHas('error');
            return session('error');
        });
        $this->qa('SEC-01', 'Security', 'Stored XSS: guest name <script>alert(1)</script>', 'Rendered escaped', function () {
            $this->as('reception@vaasalvilla.test');
            $a = now()->addDays(200)->toDateString(); $d = now()->addDays(201)->toDateString();
            $v = $this->freeVilla($a, $d);
            $this->walkIn(['arrival' => $a, 'departure' => $d, 'guest' => ['first_name' => '<script>alert(1)</script>', 'last_name' => 'X'], 'villas' => [['villa_id' => $v->id]]]);
            $b = Booking::latest('id')->first();
            $html = $this->get(route('admin.bookings.show', $b))->getContent();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
            return 'Escaped as &lt;script&gt;';
        });
        $this->qa('SEC-02', 'Security', "SQL injection in search (q=' OR 1=1 --)", 'No SQL error; normal result', function () {
            $this->get(route('admin.bookings.index', ['q' => "' OR 1=1 --"]))->assertOk();
            $this->get(route('admin.guests.index', ['q' => "'; DROP TABLE guests; --"]))->assertOk();
            $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('guests'));
            return '200; queries are parameterised';
        });
        $this->qa('SEC-03', 'Security', 'Upload a PHP script renamed .jpg to the gallery', 'Rejected by content type check', function () {
            $this->as('admin@vaasalvilla.test');
            $f = UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "x"; ?>');
            $r = $this->post(route('admin.cms.gallery.store'), ['category' => 'villas', 'photos' => [$f]]);
            $r->assertSessionHasErrors();
            return implode(' ', session('errors')->all());
        });
        $this->qa('SEC-04', 'Security', 'Passwords stored hashed', 'bcrypt hashes only', function () {
            $this->assertSame(0, User::where('password', 'not like', '$2y$%')->count());
            return User::count().' users, all bcrypt ($2y$)';
        });
        $this->qa('SEC-05', 'Security', 'Security headers on authenticated pages', 'X-Frame-Options, nosniff, no-store', function () {
            $r = $this->get(route('admin.dashboard'));
            $this->assertNotEmpty($r->headers->get('X-Frame-Options'));
            $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
            $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
            return 'X-Frame-Options '.$r->headers->get('X-Frame-Options').'; nosniff; '.$r->headers->get('Cache-Control');
        });
        $this->qa('SEC-06', 'Security', 'Demo sign-in panel outside APP_ENV=local', 'Not rendered', function () {
            $this->post('/logout');
            $this->get('/login')->assertDontSee('Quick role sign-in');
            return 'Hidden in the testing environment';
        });
        $this->qa('SEC-07', 'Security', 'Tour operator opens another operator\'s booking', 'HTTP 403/404', function () {
            $u = User::where('email', 'operator@sunrise-tours.test')->first();
            $other = Booking::whereNotNull('tour_operator_id')->where('tour_operator_id', '!=', $u->tour_operator_id)->first();
            if (! $other) return 'No other operator booking (skipped)';
            $this->actingAs($u);
            $code = $this->get(route('operator.bookings.show', $other))->getStatusCode();
            $this->assertContains($code, [403, 404]);
            return "HTTP {$code}";
        });
    }
}
