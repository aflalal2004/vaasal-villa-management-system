<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingConflict;
use App\Models\User;
use App\Models\VillaType;
use App\Modules\Channel\Services\OtaReservationService;
use Tests\TestCase;

/** REQ BK-09/BK-10 (OTA via channel manager, conflict queue) and security requirements. */
class ChannelAndSecurityTest extends TestCase
{
    private function ota(array $over = []): array
    {
        return $over + ['event_id' => uniqid('evt'), 'action' => 'new', 'channel_code' => 'booking_com', 'external_ref' => 'BDC-'.random_int(100000, 999999),
            'room_code' => 'CHX-GARDEN_VILLA', 'arrival' => now()->addDays(200)->toDateString(), 'departure' => now()->addDays(203)->toDateString(),
            'adults' => 2, 'children' => 0, 'amount' => 100000, 'guest' => ['first_name' => 'Ota', 'last_name' => 'Tester', 'email' => 'ota@example.test']];
    }

    public function test_ota_reservation_creates_booking_and_duplicate_webhook_is_ignored(): void
    {
        $svc = app(OtaReservationService::class);
        $payload = $this->ota();
        $r1 = $svc->ingest($payload, 'test');
        $this->assertSame('created', $r1['status']);
        $r2 = $svc->ingest($payload, 'test');
        $this->assertSame('duplicate', $r2['status']);
        $this->assertSame(1, Booking::where('external_ref', $payload['external_ref'])->count());
    }

    public function test_overbooking_goes_to_conflict_queue_and_alerts_managers(): void
    {
        $svc = app(OtaReservationService::class);
        $count = VillaType::where('slug', 'garden-villa')->first()->villas()->count();
        $base = ['arrival' => now()->addDays(210)->toDateString(), 'departure' => now()->addDays(212)->toDateString()];
        for ($i = 0; $i < $count; $i++) {
            $this->assertSame('created', $svc->ingest($this->ota($base))['status']);
        }
        $r = $svc->ingest($this->ota($base));
        $this->assertSame('conflict', $r['status']);
        $conflict = BookingConflict::find($r['conflict_id']);
        $this->assertSame('open', $conflict->status);
        $this->assertDatabaseHas('app_notifications', ['type' => 'booking.conflict', 'permission' => 'conflicts.manage']);
    }

    public function test_ota_cancellation_cancels_the_booking(): void
    {
        $svc = app(OtaReservationService::class);
        $p = $this->ota(['arrival' => now()->addDays(220)->toDateString(), 'departure' => now()->addDays(222)->toDateString()]);
        $svc->ingest($p);
        $svc->ingest(['event_id' => uniqid(), 'action' => 'cancelled'] + $p);
        $this->assertSame('cancelled', Booking::where('external_ref', $p['external_ref'])->first()->status);
    }

    public function test_channel_webhook_rejects_unsigned_requests(): void
    {
        $this->postJson('/webhooks/channel/null', $this->ota())->assertStatus(401);
        $this->postJson('/webhooks/channel/null', $this->ota(), ['X-Simulator-Token' => config('vaasal.locks.bridge_token')])->assertOk()->assertJsonPath('results.0.status', 'created');
    }

    public function test_account_locks_after_repeated_failed_logins(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'waiter@vaasalvilla.test', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        }
        $this->assertTrue(User::where('email', 'waiter@vaasalvilla.test')->first()->isLocked());
        $this->post('/login', ['email' => 'waiter@vaasalvilla.test', 'password' => 'Vaasal@2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('login_logs', ['email' => 'waiter@vaasalvilla.test', 'reason' => 'locked']);
    }

    public function test_successful_login_lands_on_role_home(): void
    {
        $this->post('/login', ['email' => 'kitchen@vaasalvilla.test', 'password' => 'Vaasal@2026'])->assertRedirect(route('pos.kds'));
        $this->assertAuthenticated();
    }

    public function test_unknown_email_gets_the_same_message_as_a_wrong_password(): void
    {
        $a = $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'x'])->getSession()->get('errors')->first('email');
        $b = $this->post('/login', ['email' => 'cashier@vaasalvilla.test', 'password' => 'x'])->getSession()->get('errors')->first('email');
        $this->assertSame($a, $b);
    }

    public function test_role_permissions_are_enforced_server_side(): void
    {
        $this->as('waiter@vaasalvilla.test');
        $this->get(route('admin.bookings.index'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('pos.terminal'))->assertOk();
        $this->postJson(route('pos.api.orders.discount', \App\Models\PosOrder::where('status', 'open')->first()), ['type' => 'percent', 'value' => 50, 'reason' => 'x'])->assertForbidden();

        $this->as('operator@sunrise-tours.test');
        $this->get(route('admin.dashboard'))->assertRedirect(route('operator.dashboard'));
        $this->get(route('pos.terminal'))->assertRedirect();
    }

    public function test_non_admin_cannot_grant_administrator_role(): void
    {
        $manager = User::where('email', 'manager@vaasalvilla.test')->first();
        // Give the Manager role user-management rights, but not administrator status
        \App\Models\Role::where('slug', 'manager')->first()->permissions()->attach(\App\Models\Permission::where('slug', 'users.manage')->value('id'));
        $this->actingAs($manager->fresh());
        $this->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@example.test', 'password' => 'Abcdefgh123', 'status' => 'active',
            'roles' => [\App\Models\Role::where('slug', 'admin')->value('id')]])->assertForbidden();
    }

    public function test_security_headers_and_no_store_for_authenticated_pages(): void
    {
        $this->as('admin@vaasalvilla.test');
        $res = $this->get(route('admin.dashboard'));
        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }
}
