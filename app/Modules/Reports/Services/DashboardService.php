<?php

namespace App\Modules\Reports\Services;

use App\Models\AccessLog;
use App\Models\AttendanceRecord;
use App\Models\Booking;
use App\Models\BookingConflict;
use App\Models\Employee;
use App\Models\Folio;
use App\Models\FolioLine;
use App\Models\HkTask;
use App\Models\InventoryNight;
use App\Models\Invoice;
use App\Models\KeyCardAssignment;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\StockItem;
use App\Models\Villa;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/** Central operational dashboard figures. */
class DashboardService
{
    public function build(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $villas = Villa::where('is_active', true)->get();
        $villaCount = $villas->count();

        $soldToday = InventoryNight::where('stay_date', $today)->whereNotNull('booking_villa_id')->count();
        $blockedToday = InventoryNight::where('stay_date', $today)->whereNotNull('villa_block_id')->count();

        $trend = [];
        $sold = InventoryNight::whereNotNull('booking_villa_id')->whereBetween('stay_date', [now()->subDays(6)->toDateString(), now()->addDays(13)->toDateString()])
            ->select('stay_date', DB::raw('COUNT(*) c'))->groupBy('stay_date')->pluck('c', 'stay_date');
        foreach (CarbonPeriod::create(now()->subDays(6), now()->addDays(13)) as $d) {
            $trend[$d->format('d M')] = $villaCount ? round(((int) ($sold[$d->toDateString()] ?? 0)) / $villaCount * 100) : 0;
        }

        $revenueMtd = FolioLine::whereBetween('business_date', [$monthStart, $today])->select('department', DB::raw('SUM(amount) net'))->groupBy('department')->pluck('net', 'department');
        $posDirectMtd = (float) PosOrder::whereIn('status', ['paid'])->where('closed_at', '>=', $monthStart)->sum(DB::raw('subtotal - discount_amount'));
        $revByDept = collect($revenueMtd)->mapWithKeys(fn ($v, $k) => [config('vaasal.departments.'.$k, $k) => round((float) $v, 2)]);
        if ($posDirectMtd > 0) {
            $revByDept['Restaurant (direct)'] = round(($revByDept['Restaurant (direct)'] ?? 0) + $posDirectMtd, 2);
        }

        $openFolioBalance = Folio::where('status', 'open')->get()->sum(fn ($f) => max(0, $f->balance()));
        $cityLedger = (float) Invoice::where('type', 'operator_invoice')->whereIn('status', ['issued', 'partially_paid'])->sum('balance');

        $employeesActive = Employee::where('status', 'active')->count();
        $att = AttendanceRecord::where('work_date', $today)->get();

        return [
            'arrivals' => Booking::with(['guest', 'activeVillas.villa'])->where('arrival', $today)->whereIn('status', ['confirmed', 'tentative'])->get(),
            'departures' => Booking::with(['guest', 'activeVillas.villa'])->where('departure', $today)->where('status', 'checked_in')->get(),
            'in_house' => Booking::where('status', 'checked_in')->count(),
            'checked_in_today' => Booking::whereDate('checked_in_at', $today)->count(),
            'checked_out_today' => Booking::whereDate('checked_out_at', $today)->count(),
            'villa_count' => $villaCount,
            'available_tonight' => max(0, $villaCount - $soldToday - $blockedToday),
            'occupancy_today' => $villaCount ? round($soldToday / max(1, $villaCount - $blockedToday) * 100) : 0,
            'trend' => $trend,
            'revenue_today' => round((float) FolioLine::where('business_date', $today)->sum('amount') + (float) PosOrder::where('status', 'paid')->whereDate('closed_at', $today)->sum(DB::raw('subtotal - discount_amount')), 2),
            'revenue_mtd' => round($revByDept->sum(), 2),
            'revenue_by_dept' => $revByDept->sortDesc()->all(),
            'payments_today' => round((float) Payment::where('status', 'completed')->where('method', '!=', 'city_ledger')->whereDate('paid_at', $today)->sum('amount')
                + (float) PosPayment::whereDate('created_at', $today)->where('method', '!=', 'room_charge')->sum('amount'), 2),
            'outstanding_guest' => round($openFolioBalance, 2),
            'outstanding_city' => round($cityLedger, 2),
            'hk' => $villas->groupBy('hk_status')->map->count(),
            'hk_open_tasks' => HkTask::whereIn('status', \App\Models\HkTask::OPEN)->count(),
            'hk_inspections' => HkTask::where('status', 'inspection')->count(),
            'out_of_order' => $villas->where('maintenance_status', 'out_of_order')->count(),
            'open_tickets' => MaintenanceTicket::whereNotIn('status', ['resolved', 'closed'])->count(),
            'staff_active' => $employeesActive,
            'staff_present' => $att->whereIn('status', ['present', 'late', 'incomplete'])->count(),
            'staff_late' => $att->where('late_minutes', '>', 0)->count(),
            'staff_on_duty' => $att->whereNotNull('clock_in')->whereNull('clock_out')->count(),
            'pos_sales_today' => round((float) PosOrder::whereIn('status', ['paid', 'charged_to_room'])->whereDate('closed_at', $today)->sum('total'), 2),
            'pos_open_checks' => PosOrder::whereIn('status', ['open', 'billed'])->count(),
            'low_stock' => StockItem::low()->orderBy('name')->limit(6)->get(),
            'low_stock_count' => StockItem::low()->count(),
            'operator_bookings_mtd' => Booking::whereNotNull('tour_operator_id')->where('created_at', '>=', $monthStart)->count(),
            'ota_bookings_mtd' => Booking::where('source', 'ota')->where('created_at', '>=', $monthStart)->count(),
            'conflicts_open' => BookingConflict::where('status', 'open')->count(),
            'cards_active' => KeyCardAssignment::where('status', 'active')->count(),
            'cards_today' => AccessLog::whereDate('occurred_at', $today)->select('event', DB::raw('COUNT(*) c'))->groupBy('event')->pluck('c', 'event'),
            'recent_bookings' => Booking::with(['guest', 'channel'])->latest()->limit(6)->get(),
            // Unified villa status board + headline counts
            'board' => $this->villaBoard($villas),
            'occupied_villas' => $villas->where('occupancy_status', 'occupied')->count(),
            'needs_cleaning' => $villas->whereIn('hk_status', ['dirty', 'cleaning', 'inspection'])->count(),
            'pending_bookings' => Booking::whereIn('status', ['hold', 'tentative', 'pending'])->count(),
            'recent_payments' => Payment::with(['booking.guest'])->where('status', 'completed')->where('method', '!=', 'city_ledger')->latest('paid_at')->limit(6)->get(),
            // POS cashier status (open drawers)
            'cashiers' => \App\Models\PosShift::with(['user', 'outlet'])->where('status', 'open')->latest('opened_at')->get()
                ->map(fn ($s) => ['shift' => $s, 'expected' => $s->expectedCash()]),
            'shifts_to_review' => \App\Models\PosShift::where('status', 'closed')->where('review_status', 'pending')->count(),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, array{villa: Villa, status: string, guest: ?string, task: ?HkTask}> */
    public function villaBoard($villas = null)
    {
        $villas ??= Villa::where('is_active', true)->get();
        $villas->load(['type', 'currentStay.booking.guest']);
        $today = now()->toDateString();
        $arrivals = \App\Models\BookingVilla::with('booking.guest')->where('status', 'active')->where('arrival', $today)
            ->whereHas('booking', fn ($q) => $q->where('status', 'confirmed'))->get()->keyBy('villa_id');
        $tasks = HkTask::with('assignee')->whereIn('status', HkTask::OPEN)->get()->keyBy('villa_id');
        return $villas->sortBy('sort_order')->values()->map(fn (Villa $v) => [
            'villa' => $v,
            'status' => $v->boardStatus($arrivals->has($v->id)),
            'guest' => $v->currentStay?->booking?->guest?->fullName() ?? $arrivals->get($v->id)?->booking?->guest?->fullName(),
            'task' => $tasks->get($v->id),
        ]);
    }
}
