<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\HkTask;
use App\Models\Invoice;
use App\Models\KeyCard;
use App\Models\Kot;
use App\Models\LostFoundItem;
use App\Models\MaintenanceTicket;
use App\Models\MenuItem;
use App\Models\Offer;
use App\Models\PosOrder;
use App\Models\PosShift;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\TourOperator;
use App\Models\User;
use App\Models\Villa;
use App\Models\VillaType;
use App\Models\ChargeItem;
use App\Models\Employee;
use App\Modules\Reports\Services\ReportService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Opens every screen of the application as each role and checks it renders
 * (admin: 200 on everything; other roles: 200, redirect or 403 — never a server error).
 */
class ScreensSmokeTest extends TestCase
{
    private const SKIP = ['logout', 'login', 'login.attempt', 'password.request', 'password.reset', 'operator.register', 'pay.sandbox', 'pay.return', 'pay.cancel',
        'site.weather', 'storage.local', 'up', 'operator.rooming.template'];

    private function staticGetRoutes(string $prefix): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods()) && $r->getName() && ! str_contains($r->uri(), '{') && str_starts_with($r->getName(), $prefix)
                && ! in_array($r->getName(), self::SKIP, true) && ! str_starts_with($r->uri(), 'api/') && ! str_starts_with($r->getName(), 'pos.api.'))
            ->map(fn ($r) => $r->getName())->values()->all();
    }

    public function test_admin_can_open_every_back_office_and_pos_screen(): void
    {
        $this->as('admin@vaasalvilla.test');
        foreach (array_merge($this->staticGetRoutes('admin.'), $this->staticGetRoutes('pos.'), ['profile.edit', 'password.change']) as $name) {
            $res = $this->get(route($name));
            $this->assertContains($res->getStatusCode(), [200, 302], "$name returned {$res->getStatusCode()}".($res->getStatusCode() >= 500 ? ': '.$res->exception?->getMessage() : ''));
        }
    }

    public function test_admin_can_open_every_record_screen(): void
    {
        $this->as('admin@vaasalvilla.test');
        $inHouse = Booking::where('status', 'checked_in')->first();
        $arrival = Booking::where('status', 'confirmed')->where('arrival', now()->toDateString())->first();
        $order = PosOrder::whereIn('status', ['paid', 'charged_to_room'])->first();
        $open = PosOrder::where('status', 'open')->first();
        $kot = Kot::first();
        $urls = [
            route('admin.bookings.show', $inHouse), route('admin.bookings.voucher', $inHouse), route('admin.bookings.confirmation', $inHouse), route('admin.bookings.registration', $inHouse),
            route('admin.frontdesk.checkin', $arrival), route('admin.frontdesk.checkout', $inHouse), route('admin.folios.print', Folio::first()),
            route('admin.guests.show', Guest::first()), route('admin.guests.edit', Guest::first()),
            route('admin.villas.show', Villa::first()), route('admin.villas.edit', Villa::first()), route('admin.villa-types.edit', VillaType::first()),
            route('admin.offers.edit', Offer::first()), route('admin.charge-items.edit', ChargeItem::first()),
            route('admin.invoices.show', Invoice::where('type', 'invoice')->first()), route('admin.invoices.show', Invoice::where('type', 'operator_invoice')->first()),
            route('admin.housekeeping.tasks.show', HkTask::first()), route('admin.maintenance.show', MaintenanceTicket::first()), route('admin.lost-found.edit', LostFoundItem::first()),
            route('admin.keycards.show', KeyCard::first()), route('admin.operators.show', TourOperator::first()), route('admin.operators.edit', TourOperator::first()),
            route('admin.operators.statement', TourOperator::first()), route('admin.staff.employees.show', Employee::first()), route('admin.staff.employees.edit', Employee::first()),
            route('admin.users.edit', User::first()), route('admin.roles.edit', Role::where('slug', 'manager')->first()),
            route('pos.bill.print', $open), route('pos.receipt', $order), route('pos.kot.print', [$kot->order, $kot]), route('pos.shifts.show', PosShift::first()),
            route('pos.menu.items.edit', MenuItem::first()), route('pos.inventory.suppliers.edit', Supplier::first()),
            route('pos.api.orders.show', $open), route('pos.api.tables', ['outlet' => $open->outlet_id]), route('pos.api.menu', ['outlet' => $open->outlet_id]), route('pos.kds.feed'),
        ];
        foreach (array_keys(ReportService::TYPES) as $type) {
            $urls[] = route('admin.reports.show', $type);
            $urls[] = route('admin.reports.show', ['type' => $type, 'export' => 'csv']);
        }
        foreach ($urls as $url) {
            $res = $this->get($url);
            $this->assertSame(200, $res->getStatusCode(), "$url returned {$res->getStatusCode()}".($res->exception ? ': '.$res->exception->getMessage() : ''));
        }
    }

    public function test_every_role_gets_a_page_or_a_clean_denial(): void
    {
        $routes = array_merge($this->staticGetRoutes('admin.'), $this->staticGetRoutes('pos.'));
        foreach (User::where('user_type', 'staff')->whereDoesntHave('roles', fn ($q) => $q->where('slug', 'admin'))->get() as $user) {
            $this->actingAs($user);
            foreach ($routes as $name) {
                $code = $this->get(route($name))->getStatusCode();
                $this->assertContains($code, [200, 302, 403], "{$user->email} → $name returned $code");
            }
        }
    }

    public function test_public_website_pages_render(): void
    {
        $b = Booking::where('status', 'confirmed')->where('source', 'website')->first() ?? Booking::where('status', 'confirmed')->first();
        foreach ([route('home'), route('site.about'), route('site.villas'), route('site.villa', VillaType::first()), route('site.services'), route('site.gallery'),
            route('site.offers'), route('site.contact'), route('book.search'), route('book.search', ['arrival' => now()->addDays(40)->toDateString(), 'departure' => now()->addDays(43)->toDateString(), 'promo' => 'STAY4']),
            route('book.details', ['type' => VillaType::first()->id, 'plan' => 1, 'arrival' => now()->addDays(40)->toDateString(), 'departure' => now()->addDays(43)->toDateString(), 'adults' => 2]),
            route('manage.lookup'), route('manage.show', $b->manage_token), route('manage.voucher', $b->manage_token), route('book.confirmation', $b->manage_token),
            route('login'), route('operator.register'), route('password.request')] as $url) {
            $res = $this->get($url);
            $this->assertSame(200, $res->getStatusCode(), "$url returned {$res->getStatusCode()}".($res->exception ? ': '.$res->exception->getMessage() : ''));
        }
    }

    public function test_operator_portal_pages_render_and_are_scoped(): void
    {
        $this->as('operator@sunrise-tours.test');
        foreach ($this->staticGetRoutes('operator.') as $name) {
            $this->get(route($name))->assertOk();
        }
        $own = Booking::where('tour_operator_id', auth()->user()->tour_operator_id)->first();
        $other = Booking::whereNotNull('tour_operator_id')->where('tour_operator_id', '!=', auth()->user()->tour_operator_id)->first();
        if ($own) $this->get(route('operator.bookings.show', $own))->assertOk();
        if ($other) $this->get(route('operator.bookings.show', $other))->assertNotFound();
        $this->get(route('admin.dashboard'))->assertRedirect(); // operators never reach the back office
    }
}
