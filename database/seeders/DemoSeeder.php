<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\ChargeItem;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\HkTask;
use App\Models\KeyCard;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\Outlet;
use App\Models\PosTable;
use App\Models\TourOperator;
use App\Models\User;
use App\Models\Villa;
use App\Modules\Billing\Services\FolioService;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Channel\Services\OtaReservationService;
use App\Modules\FrontDesk\Services\CheckInService;
use App\Modules\FrontDesk\Services\CheckOutService;
use App\Modules\FrontDesk\Services\RoomChargeService;
use App\Modules\Housekeeping\Services\HousekeepingService;
use App\Modules\KeyCards\Services\KeyCardService;
use App\Modules\Maintenance\Services\MaintenanceService;
use App\Modules\Operators\Services\OperatorService;
use App\Modules\POS\Services\PosBillingService;
use App\Modules\POS\Services\PosOrderService;
use App\Modules\POS\Services\PosShiftService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Realistic operating history, produced by running the real domain services with
 * time travel (Carbon::setTestNow): past stays with POS charges, checkouts, housekeeping,
 * key cards; guests in-house today; arrivals; future bookings; an OTA conflict; tickets.
 */
class DemoSeeder extends Seeder
{
    private BookingService $bookings;
    private CheckInService $checkIn;
    private CheckOutService $checkOut;
    private FolioService $folios;
    private HousekeepingService $hk;
    private PosOrderService $orders;
    private PosBillingService $billing;
    private PosShiftService $shifts;
    private int $cardIdx = 0;
    private array $cards = [];

    public function run(): void
    {
        mt_srand(28);
        $this->bookings = app(BookingService::class);
        $this->checkIn = app(CheckInService::class);
        $this->checkOut = app(CheckOutService::class);
        $this->folios = app(FolioService::class);
        $this->hk = app(HousekeepingService::class);
        $this->orders = app(PosOrderService::class);
        $this->billing = app(PosBillingService::class);
        $this->shifts = app(PosShiftService::class);
        $this->cards = KeyCard::where('type', 'guest')->pluck('uid')->all();

        $today = now()->startOfDay();
        $admin = User::where('email', 'reception@vaasalvilla.test')->first();
        $cashier = User::where('email', 'cashier@vaasalvilla.test')->first();
        $villa = fn ($code) => Villa::where('code', $code)->first();

        $guests = [
            ['Emma', 'Clarke', 'emma.clarke@example.test', 'United Kingdom'], ['Rahul', 'Menon', 'rahul.menon@example.test', 'India'],
            ['Julia', 'Schmidt', 'julia.schmidt@example.test', 'Germany'], ['Tom', 'Nguyen', 'tom.nguyen@example.test', 'Australia'],
            ['Chloé', 'Martin', 'chloe.martin@example.test', 'France'], ['Kenji', 'Sato', 'kenji.sato@example.test', 'Japan'],
            ['Amelia', 'Brown', 'amelia.brown@example.test', 'United States'], ['Nuwan', 'Perera', 'nuwan.perera@example.test', 'Sri Lanka'],
            ['Isabella', 'Rossi', 'isabella.rossi@example.test', 'Italy'], ['Oliver', 'Jensen', 'oliver.jensen@example.test', 'Denmark'],
            ['Priyanka', 'Iyer', 'priyanka.iyer@example.test', 'India'], ['Lucas', 'Silva', 'lucas.silva@example.test', 'Brazil'],
            ['Sarah', 'Cohen', 'sarah.cohen@example.test', 'Israel'], ['Daniel', 'Fischer', 'daniel.fischer@example.test', 'Switzerland'],
            ['Mei', 'Lin', 'mei.lin@example.test', 'Singapore'], ['Aaron', 'Walsh', 'aaron.walsh@example.test', 'Ireland'],
        ];
        $g = function (int $i) use ($guests) {
            [$f, $l, $e, $c] = $guests[$i % count($guests)];
            return ['first_name' => $f, 'last_name' => $l, 'email' => $e, 'phone' => '+'.mt_rand(30, 99).' '.mt_rand(100, 999).' '.mt_rand(100000, 999999), 'country' => $c, 'nationality' => $c];
        };

        // ---------------- Past stays (last ~7 weeks) ----------------
        $history = [
            [-48, 4, 'P1', 'website', 0], [-45, 3, 'G1', 'phone', 1], [-41, 5, 'O1', 'ota', 2], [-38, 3, 'F1', 'tour_operator', 3],
            [-35, 4, 'P2', 'website', 4], [-31, 2, 'G2', 'walk_in', 5], [-29, 6, 'O2', 'ota', 6], [-26, 3, 'P3', 'website', 7],
            [-23, 4, 'P1', 'tour_operator', 8], [-20, 3, 'G1', 'website', 9], [-18, 5, 'O1', 'website', 10], [-15, 3, 'F1', 'phone', 11],
            [-12, 4, 'P2', 'ota', 12], [-10, 3, 'O2', 'website', 13], [-9, 4, 'P3', 'tour_operator', 14], [-7, 3, 'G2', 'website', 15],
            [-6, 3, 'P1', 'phone', 0],
        ];
        Auth::login($admin);
        foreach ($history as [$offset, $nights, $code, $source, $gi]) {
            $arrival = $today->copy()->addDays($offset);
            $departure = $arrival->copy()->addDays($nights);
            Carbon::setTestNow($arrival->copy()->setTime(9, 0));
            $booking = $this->book($source, $g($gi), $villa($code), $arrival, $departure);
            $this->prePay($booking, $source);

            Carbon::setTestNow($arrival->copy()->setTime(14, 30));
            $this->doCheckIn($booking);

            // Restaurant & services during the stay
            for ($d = 0; $d < $nights; $d++) {
                $day = $arrival->copy()->addDays($d);
                Carbon::setTestNow($day->copy()->setTime(19, 30));
                Auth::login($cashier);
                $this->ensureShift($cashier, $day);
                $this->posOrder($booking, $d % 2 === 0 ? 'room_charge' : 'card', mt_rand(2, 4));
                if (mt_rand(0, 2) === 0) $this->posOrder(null, 'cash', mt_rand(1, 3)); // walk-in diner
                Auth::login($admin);
                if (mt_rand(0, 3) === 0) {
                    $item = ChargeItem::whereIn('code', ['SPA-60', 'LAUN-WASH', 'EXC-DELFT', 'TRF-LOCAL', 'MINI-BEER'])->inRandomOrder()->first();
                    $this->folios->post($this->folios->open($booking), $item->department, $item->name, 1, (float) $item->price, $item, applyService: $item->service_chargeable);
                }
            }
            $this->closeShift($cashier, $departure->copy()->subDay());

            Carbon::setTestNow($departure->copy()->setTime(10, 45));
            $this->doCheckOut($booking);
            $this->cleanVilla($booking, $departure);
        }

        // ---------------- Guests in-house now ----------------
        $inHouse = [
            [-2, 4, 'O1', 'website', 2], [-1, 4, 'P2', 'ota', 4], [-3, 3, 'F1', 'tour_operator', 5], [-1, 2, 'G1', 'phone', 6],
        ];
        $current = [];
        foreach ($inHouse as [$offset, $nights, $code, $source, $gi]) {
            $arrival = $today->copy()->addDays($offset);
            Carbon::setTestNow($arrival->copy()->setTime(9, 0));
            $b = $this->book($source, $g($gi + 3), $villa($code), $arrival, $arrival->copy()->addDays($nights));
            $this->prePay($b, $source);
            Carbon::setTestNow($arrival->copy()->setTime(15, 0));
            $this->doCheckIn($b);
            // Night audit already ran for nights before today
            app(RoomChargeService::class)->postNights($b, $today);
            $current[$code] = $b;
        }

        // G2 guest checked out this morning → villa dirty, task pending
        Carbon::setTestNow($today->copy()->subDays(2)->setTime(9, 0));
        $b = $this->book('website', $g(1), $villa('G2'), $today->copy()->subDays(2), $today->copy());
        $this->prePay($b, 'website');
        Carbon::setTestNow($today->copy()->subDays(2)->setTime(14, 0));
        $this->doCheckIn($b);
        Carbon::setTestNow($today->copy()->setTime(8, 40));
        $this->doCheckOut($b);
        $task = HkTask::where('villa_id', $villa('G2')->id)->latest('id')->first();
        $this->hk->assign($task, Employee::where('email', 'housekeeping@vaasalvilla.test')->first());

        // P3 cleaned, awaiting inspection
        Carbon::setTestNow($today->copy()->setTime(9, 15));
        $p3 = $villa('P3');
        $p3->update(['hk_status' => 'dirty']);
        $t = $this->hk->createTask($p3, 'deep', $today, null, 'normal', Employee::where('email', 'housekeeping2@vaasalvilla.test')->value('id'), 'Monthly deep clean');
        $this->hk->start($t);
        foreach ($t->items as $it) { $this->hk->toggleItem($t, $it->id, true); }
        Carbon::setTestNow($today->copy()->setTime(10, 20));
        $this->hk->requestInspection($t, [1 => ['out' => 4, 'in' => 4]]);

        // Stay-over service tasks for in-house villas
        Carbon::setTestNow($today->copy()->setTime(7, 0));
        $this->hk->generateStayovers($today);

        // ---------------- Arrivals today ----------------
        Carbon::setTestNow($today->copy()->subDays(12)->setTime(11, 0));
        $a1 = $this->book('phone', $g(9), $villa('P1'), $today, $today->copy()->addDays(3), ['special_requests' => 'Anniversary — flowers on arrival please.', 'arrival_time' => '15:00']);
        $this->prePay($a1, 'phone');
        Carbon::setTestNow($today->copy()->subDays(5)->setTime(16, 0));
        app(OtaReservationService::class)->ingest(['event_id' => 'bdc-evt-'.mt_rand(10000, 99999), 'action' => 'new', 'channel_code' => 'booking_com',
            'external_ref' => '4412'.mt_rand(100000, 999999), 'room_code' => 'CHX-OCEAN_POOL_VILLA', 'rate_code' => 'CHX-BAR', 'arrival' => $today->toDateString(),
            'departure' => $today->copy()->addDays(4)->toDateString(), 'adults' => 2, 'children' => 0, 'amount' => 312000,
            'guest' => ['first_name' => 'Hiroshi', 'last_name' => 'Tanaka', 'email' => 'h.tanaka@example.test', 'country' => 'Japan'], 'notes' => 'Late arrival ~21:00'], 'channex');

        // OTA overbooking attempt for the same villa type → conflict queue (never dropped)
        Carbon::setTestNow($today->copy()->subDay()->setTime(22, 10));
        app(OtaReservationService::class)->ingest(['event_id' => 'agoda-evt-'.mt_rand(10000, 99999), 'action' => 'new', 'channel_code' => 'agoda',
            'external_ref' => 'AG-'.mt_rand(1000000, 9999999), 'room_code' => 'CHX-OCEAN_POOL_VILLA', 'arrival' => $today->toDateString(),
            'departure' => $today->copy()->addDays(2)->toDateString(), 'adults' => 2, 'children' => 0, 'amount' => 158000,
            'guest' => ['first_name' => 'Laura', 'last_name' => 'Gómez', 'email' => 'laura.gomez@example.test', 'country' => 'Spain']], 'channex');

        // ---------------- Future bookings ----------------
        Carbon::setTestNow($today->copy()->subDays(3)->setTime(10, 0));
        $f1 = $this->book('website', $g(12), $villa('P3'), $today->copy()->addDays(5), $today->copy()->addDays(10), ['promo_code' => 'STAY4', 'rate_plan_code' => 'BB']);
        $this->prePay($f1, 'website');
        Carbon::setTestNow($today->copy()->subDays(1)->setTime(10, 0));
        $f2 = $this->bookings->create(['source' => 'tour_operator', 'channel_code' => 'tour_operator', 'guest' => ['first_name' => 'Klaus', 'last_name' => 'Weber', 'email' => 'k.weber@example.test', 'country' => 'Germany'],
            'tour_operator_id' => TourOperator::where('company_name', 'like', 'EuroAsia%')->value('id'), 'group_name' => 'EuroAsia Northern Heritage Tour',
            'arrival' => $today->copy()->addDays(20)->toDateString(), 'departure' => $today->copy()->addDays(24)->toDateString(),
            'villas' => [['villa_id' => $villa('G2')->id, 'adults' => 2], ['villa_id' => $villa('P1')->id, 'adults' => 2], ['villa_id' => $villa('O2')->id, 'adults' => 2]],
            'stay_guests' => [0 => [['first_name' => 'Klaus', 'last_name' => 'Weber', 'nationality' => 'German', 'is_primary' => true], ['first_name' => 'Petra', 'last_name' => 'Weber', 'nationality' => 'German']]],
            'status' => 'confirmed']);
        app(OperatorService::class)->recordDeposit($f2, round((float) $f2->grand_total * 0.3, -2), 'bank_transfer', 'SWIFT 7781');
        Carbon::setTestNow($today->copy()->setTime(9, 30));
        $f3 = $this->book('phone', $g(13), $villa('F1'), $today->copy()->addDays(14), $today->copy()->addDays(18), ['status' => 'tentative']);
        $f4 = $this->book('email', $g(14), $villa('O1'), $today->copy()->addDays(30), $today->copy()->addDays(33));
        $this->prePay($f4, 'email');
        $f5 = $this->book('website', $g(15), $villa('G1'), $today->copy()->addDays(8), $today->copy()->addDays(11));
        $this->bookings->cancel($f5, 'Guest changed travel plans', 0);

        // Sunrise Tours: outstanding invoice from an earlier stay (partially paid)
        $sunrise = TourOperator::where('company_name', 'like', 'Sunrise%')->first();
        $inv = $sunrise->invoices()->where('type', 'operator_invoice')->first();
        if ($inv) {
            Carbon::setTestNow($today->copy()->subDays(3)->setTime(11, 0));
            app(OperatorService::class)->recordPayment($sunrise, round((float) $inv->balance / 2, -2), 'bank_transfer', 'CB-TRF-99812', $inv);
        }

        // ---------------- Restaurant right now ----------------
        Carbon::setTestNow($today->copy()->setTime(12, 5));
        Auth::login($cashier);
        $this->ensureShift($cashier, $today, 12);
        $rest = Outlet::where('code', 'REST')->first();
        $o = $this->orders->open($rest, ['pos_table_id' => PosTable::where('outlet_id', $rest->id)->where('name', 'T3')->value('id'), 'covers' => 2]);
        $this->addRandom($o, 3);
        $this->orders->fire($o);
        Carbon::setTestNow($today->copy()->setTime(12, 25));
        $o2 = $this->orders->open($rest, ['pos_table_id' => PosTable::where('outlet_id', $rest->id)->where('name', 'T6')->value('id'), 'covers' => 4, 'guest_name' => 'Table of four']);
        $this->addRandom($o2, 4);
        $this->orders->fire($o2);
        $o2->kots()->first()?->update(['status' => 'preparing', 'started_at' => now()]);
        $this->addRandom($o2, 1); // new unsent item
        Carbon::setTestNow($today->copy()->setTime(12, 40));
        $o3 = $this->orders->open(Outlet::where('code', 'IRD')->first(), ['type' => 'room_service', 'booking_id' => $current['O1']->id]);
        $this->addRandom($o3, 2);
        $this->orders->fire($o3);
        Carbon::setTestNow($today->copy()->setTime(12, 55));

        // ---------------- Maintenance ----------------
        Auth::login(User::where('email', 'housekeeping@vaasalvilla.test')->first());
        $m = app(MaintenanceService::class);
        Carbon::setTestNow($today->copy()->subDays(9)->setTime(10, 0));
        $t1 = $m->report(['villa_id' => $villa('G1')->id, 'title' => 'Bathroom tap dripping', 'category' => 'plumbing', 'severity' => 'low', 'description' => 'Hot tap in outdoor shower drips constantly.']);
        Auth::login(User::where('email', 'maintenance@vaasalvilla.test')->first());
        Carbon::setTestNow($today->copy()->subDays(9)->setTime(15, 30));
        $m->updateStatus($t1, 'resolved', 'Replaced washer.', 850);
        Carbon::setTestNow($today->copy()->setTime(8, 10));
        Auth::login(User::where('email', 'housekeeping2@vaasalvilla.test')->first());
        $m->report(['villa_id' => $villa('P3')->id, 'title' => 'AC not cooling in bedroom', 'category' => 'ac', 'severity' => 'medium', 'description' => 'Reported during deep clean; unit runs but air is warm.',
            'assigned_to' => Employee::where('email', 'maintenance@vaasalvilla.test')->value('id')]);
        $m->report(['villa_id' => $villa('O2')->id, 'title' => 'Pool pump noisy', 'category' => 'pool', 'severity' => 'high', 'description' => 'Rattling from pump housing. Check before OTA guest arrives tonight.']);

        // ---------------- Lost & found, enquiries, lost card ----------------
        \App\Models\LostFoundItem::create(['item_no' => 'LF-26-000001', 'villa_id' => $villa('O2')->id, 'found_location' => 'Bedside drawer', 'description' => 'Kindle e-reader, black cover',
            'category' => 'electronics', 'found_by' => Employee::where('email', 'housekeeping@vaasalvilla.test')->value('id'), 'found_at' => $today->copy()->subDays(8)->setTime(11, 30),
            'storage_location' => 'Front office safe', 'status' => 'stored']);
        \App\Models\DocumentSequence::updateOrCreate(['type' => 'lost_found', 'year' => (int) $today->format('Y')], ['prefix' => 'LF', 'next_number' => 2]);

        Enquiry::create(['name' => 'Grace Liu', 'email' => 'grace.liu@example.test', 'phone' => '+65 9123 4567', 'subject' => 'Wedding party of 14',
            'message' => 'We are planning a small wedding in February. Could we book all villas for 3 nights and the restaurant for a private dinner?', 'arrival' => $today->copy()->addMonths(4), 'departure' => $today->copy()->addMonths(4)->addDays(3), 'guests' => 14]);
        Enquiry::create(['name' => 'Mohamed Faiz', 'email' => 'm.faiz@example.test', 'subject' => 'Airport transfer price', 'message' => 'How much is the transfer from BIA for 3 people with luggage?', 'status' => 'replied']);

        Auth::login($admin);
        Carbon::setTestNow($today->copy()->setTime(9, 5));
        $lostCard = \App\Models\KeyCardAssignment::where('booking_id', $current['P2']->id)->where('status', 'active')->first()?->card;
        if ($lostCard) {
            app(KeyCardService::class)->reportLost($lostCard, $this->nextCard());
        }

        Carbon::setTestNow();
        Auth::logout();
    }

    // ------------------------------------------------------------------ helpers

    private function book(string $source, array $guest, Villa $villa, Carbon $arrival, Carbon $departure, array $extra = []): Booking
    {
        $data = [
            'source' => $source,
            'channel_code' => match ($source) { 'ota' => ['booking_com', 'agoda', 'airbnb', 'expedia'][mt_rand(0, 3)], default => $source },
            'guest' => $guest, 'arrival' => $arrival->toDateString(), 'departure' => $departure->toDateString(),
            'villas' => [['villa_id' => $villa->id, 'adults' => min(2, $villa->type->max_adults), 'children' => $villa->type->max_children > 1 ? mt_rand(0, 1) : 0]],
            'status' => $extra['status'] ?? 'confirmed', 'ignore_rules' => true,
            'rate_plan_id' => \App\Models\RatePlan::where('code', $extra['rate_plan_code'] ?? ['RO', 'BB', 'BB', 'NR'][mt_rand(0, 3)])->value('id'),
        ] + $extra;
        if ($source === 'ota') {
            $data['external_ref'] = strtoupper(substr($data['channel_code'], 0, 3)).'-'.mt_rand(1000000, 9999999);
            $data['payment_mode'] = 'pay_at_property';
        }
        if ($source === 'tour_operator') {
            $data['tour_operator_id'] = TourOperator::where('status', 'active')->inRandomOrder()->value('id');
            $data['channel_code'] = 'tour_operator';
        }
        unset($data['rate_plan_code']);
        return $this->bookings->create($data);
    }

    private function prePay(Booking $b, string $source): void
    {
        if (in_array($source, ['ota', 'tour_operator'], true)) return;
        $amount = $source === 'website' ? (float) $b->deposit_due : round((float) $b->grand_total * 0.3, -2);
        if ($amount <= 0) return;
        $p = $this->folios->recordPayment($this->folios->open($b), $source === 'website' ? 'online' : 'bank_transfer', $amount, 'deposit', 'Advance deposit',
            $source === 'website' ? 'sandbox' : null, $source === 'website' ? 'sbx_'.bin2hex(random_bytes(6)) : null);
        app(InvoiceService::class)->issueReceipt($p);
    }

    private function doCheckIn(Booking $b): void
    {
        foreach ($b->activeVillas()->with('villa')->get() as $bv) {
            if ($bv->villa->hk_status !== 'ready') $bv->villa->update(['hk_status' => 'ready']);
        }
        $cards = [];
        foreach ($b->activeVillas as $bv) {
            $cards[$bv->id] = $this->nextCard();
        }
        $this->checkIn->checkIn($b->fresh(), ['id_type' => 'passport', 'id_number' => strtoupper(substr($b->guest->country ?? 'X', 0, 1)).mt_rand(1000000, 9999999),
            'id_expiry' => now()->addYears(4)->toDateString(), 'card_uids' => $cards]);
    }

    private function doCheckOut(Booking $b): void
    {
        $b = $b->fresh(['folios']);
        app(RoomChargeService::class)->postNights($b, $b->departure);
        $payments = [];
        foreach ($b->folios as $f) {
            if ($f->payer_type === 'operator') continue;
            $bal = $f->balance();
            if ($bal > 0) $payments[$f->id] = ['method' => ['card', 'cash', 'card', 'bank_transfer'][mt_rand(0, 3)], 'amount' => $bal];
        }
        $this->checkOut->checkOut($b, ['payments' => $payments]);
    }

    private function cleanVilla(Booking $b, Carbon $day): void
    {
        $staff = Employee::whereIn('email', ['housekeeping@vaasalvilla.test', 'housekeeping2@vaasalvilla.test'])->get();
        foreach (HkTask::where('booking_id', $b->id)->where('status', 'pending')->get() as $task) {
            $this->hk->assign($task, $staff->random());
            Carbon::setTestNow($day->copy()->setTime(11, mt_rand(0, 40)));
            $this->hk->start($task);
            foreach ($task->items as $it) { $this->hk->toggleItem($task, $it->id, true); }
            Carbon::setTestNow(now()->addMinutes(mt_rand(38, 75)));
            $this->hk->requestInspection($task, [1 => ['out' => 4, 'in' => 4], 5 => ['out' => 1, 'in' => 1]]);
            if (mt_rand(0, 6) === 0) {
                Carbon::setTestNow(now()->addMinutes(10));
                $this->hk->reject($task, 'Pool deck not swept; replace hand towel.');
                Carbon::setTestNow(now()->addMinutes(15));
                $this->hk->requestInspection($task);
            }
            Carbon::setTestNow(now()->addMinutes(12));
            $this->hk->approve($task, 'Good');
        }
    }

    private function ensureShift(User $cashier, Carbon $day, int $hour = 11): void
    {
        $outlets = Outlet::whereIn('code', ['REST', 'IRD'])->get();
        foreach ($outlets as $outlet) {
            if (! $this->shifts->current($cashier, $outlet->id)) {
                $at = now();
                Carbon::setTestNow($day->copy()->setTime($hour, 0));
                $this->shifts->open($outlet, $cashier, $outlet->code === 'REST' ? 20000 : 0);
                Carbon::setTestNow($at);
            }
        }
    }

    private function closeShift(User $cashier, Carbon $day): void
    {
        foreach (\App\Models\PosShift::where('user_id', $cashier->id)->where('status', 'open')->get() as $shift) {
            Carbon::setTestNow($day->copy()->setTime(23, 15));
            // Small realistic variances, never a negative physical count.
            $expected = $shift->expectedCash();
            $this->shifts->close($shift, max(0, $expected + ($expected > 0 ? [0, 0, 0, -100, 50][mt_rand(0, 4)] : 0)));
            // Historical demo shifts are already reviewed; only today's closings wait for a manager.
            $shift->refresh()->update(['review_status' => 'approved', 'reviewed_at' => $shift->closed_at, 'review_notes' => 'Demo data — reviewed.']);
            \App\Models\AppNotification::where('type', 'pos.shift_closed')->delete();
        }
    }

    private function posOrder(?Booking $booking, string $method, int $items): void
    {
        $outlet = Outlet::where('code', $booking && mt_rand(0, 2) === 0 ? 'IRD' : 'REST')->first();
        if ($outlet->code === 'IRD') {
            $o = $this->orders->open($outlet, ['type' => 'room_service', 'booking_id' => $booking->id]);
        } else {
            $table = PosTable::where('outlet_id', $outlet->id)->whereDoesntHave('openOrder')->inRandomOrder()->first();
            $o = $this->orders->open($outlet, ['pos_table_id' => $table->id, 'covers' => mt_rand(1, 4), 'guest_name' => $booking?->guest->fullName()]);
        }
        $this->addRandom($o, $items);
        $this->orders->fire($o);
        $o->kots()->update(['status' => 'served', 'ready_at' => now()->addMinutes(14), 'served_at' => now()->addMinutes(16)]);
        $o->items()->update(['status' => 'served']);
        if (mt_rand(0, 5) === 0) $this->orders->applyDiscount($o, 'percent', 10, 'Returning guest');
        $o = $this->orders->recalc($o);
        $tender = ['method' => $booking ? $method : ($method === 'room_charge' ? 'cash' : $method), 'amount' => (float) $o->total];
        if ($tender['method'] === 'room_charge') $tender['booking_id'] = $booking->id;
        if ($tender['method'] === 'cash') $tender['tendered'] = ceil($o->total / 1000) * 1000;
        $this->billing->pay($o, [$tender]);
    }

    private function addRandom($order, int $count): void
    {
        $items = MenuItem::with('modifierGroups.modifiers')->where('is_active', true)->inRandomOrder()->limit($count)->get();
        foreach ($items as $item) {
            $mods = [];
            foreach ($item->modifierGroups as $grp) {
                if ($grp->min_select > 0) $mods[] = $grp->modifiers->random()->id;
            }
            $this->orders->addItem($order, $item, mt_rand(1, 2), $mods);
        }
    }

    private function nextCard(): string
    {
        // Rotate through available stock; checkouts return cards to "available".
        for ($i = 0; $i < count($this->cards); $i++) {
            $uid = $this->cards[$this->cardIdx++ % count($this->cards)];
            if (KeyCard::where('uid', $uid)->value('status') === 'available') return $uid;
        }
        return 'DEMO'.strtoupper(bin2hex(random_bytes(4)));
    }
}
