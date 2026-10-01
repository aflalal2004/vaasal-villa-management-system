<?php

namespace App\Modules\FrontDesk\Services;

use App\Models\Booking;
use App\Models\Setting;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Housekeeping\Services\HousekeepingService;
use App\Modules\KeyCards\Services\KeyCardService;
use App\Modules\Staff\Services\AttendanceService;
use Illuminate\Support\Carbon;

/**
 * Daily close: posts tonight's room charge for every in-house stay, flags no-shows,
 * expires holds and cards, creates stay-over tasks, marks absences, records the run.
 */
class NightAuditService
{
    public function __construct(
        private RoomChargeService $roomCharges,
        private BookingService $bookings,
        private HousekeepingService $housekeeping,
        private KeyCardService $cards,
        private AttendanceService $attendance,
    ) {}

    public function run(?Carbon $businessDate = null): array
    {
        $date = ($businessDate ?? now())->copy()->startOfDay();
        $report = ['business_date' => $date->toDateString(), 'room_nights' => 0, 'no_shows' => 0, 'holds_expired' => 0, 'cards_expired' => 0, 'stayovers' => 0, 'absences' => 0];

        Booking::where('status', 'checked_in')->each(function (Booking $b) use ($date, &$report) {
            $report['room_nights'] += $this->roomCharges->postNights($b, $date->copy()->addDay());
        });

        Booking::where('status', 'confirmed')->where('arrival', '<', $date->toDateString())->each(function (Booking $b) use (&$report) {
            $this->bookings->markNoShow($b);
            $report['no_shows']++;
        });

        $report['holds_expired'] = $this->bookings->expireHolds();
        $report['cards_expired'] = $this->cards->expireDue();
        $report['stayovers'] = $this->housekeeping->generateStayovers($date->copy()->addDay());
        $report['absences'] = $this->attendance->markAbsences($date->copy()->subDay());

        Setting::put('night_audit_last', json_encode($report + ['run_at' => now()->toDateTimeString()]));
        return $report;
    }
}
