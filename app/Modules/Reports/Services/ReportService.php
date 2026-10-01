<?php

namespace App\Modules\Reports\Services;

use App\Models\AccessLog;
use App\Models\AttendanceRecord;
use App\Models\Booking;
use App\Models\Commission;
use App\Models\Employee;
use App\Models\FolioLine;
use App\Models\HkTask;
use App\Models\InventoryNight;
use App\Models\KeyCardAssignment;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosPayment;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\TourOperator;
use App\Models\Villa;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * All reports return one shape so a single view / CSV exporter renders any of them:
 * [title, description, columns => [key => label], rows => [...], summary => [label => value], chart => [label => value]|null, money => [keys]]
 */
class ReportService
{
    public const TYPES = [
        'bookings' => ['Bookings', 'reports.operational'],
        'occupancy' => ['Occupancy, ADR & RevPAR', 'reports.operational'],
        'revenue' => ['Revenue by department', 'reports.financial'],
        'payments' => ['Payments received', 'reports.financial'],
        'outstanding' => ['Outstanding balances', 'reports.financial'],
        'guests' => ['Guest statistics', 'reports.operational'],
        'channels' => ['OTA & channel production', 'reports.financial'],
        'operators' => ['Tour operator bookings & commission', 'reports.financial'],
        'pos_sales' => ['POS sales', 'reports.financial|pos.reports'],
        'pos_items' => ['POS item sales mix', 'reports.financial|pos.reports'],
        'inventory' => ['Inventory valuation & wastage', 'reports.operational|inventory.view'],
        'housekeeping' => ['Housekeeping performance', 'reports.operational'],
        'attendance' => ['Staff attendance', 'reports.operational|staff.view'],
        'maintenance' => ['Maintenance', 'reports.operational'],
        'keycards' => ['Key card activity', 'reports.operational'],
    ];

    public function run(string $type, Carbon $from, Carbon $to): array
    {
        $method = 'report'.str_replace('_', '', ucwords($type, '_'));
        abort_unless(isset(self::TYPES[$type]) && method_exists($this, $method), 404);
        $data = $this->{$method}($from->copy()->startOfDay(), $to->copy()->endOfDay());
        return $data + ['title' => self::TYPES[$type][0], 'type' => $type, 'from' => $from, 'to' => $to, 'chart' => $data['chart'] ?? null, 'money' => $data['money'] ?? [], 'description' => $data['description'] ?? null];
    }

    private function reportBookings(Carbon $from, Carbon $to): array
    {
        $bookings = Booking::with(['guest', 'channel', 'operator'])->whereBetween('arrival', [$from->toDateString(), $to->toDateString()])->orderBy('arrival')->get();
        return [
            'description' => 'Bookings arriving in the period, all sources.',
            'columns' => ['reference' => 'Ref', 'guest' => 'Guest', 'source' => 'Source', 'channel' => 'Channel', 'arrival' => 'Arrival', 'nights' => 'Nights', 'status' => 'Status', 'total' => 'Total'],
            'rows' => $bookings->map(fn ($b) => ['reference' => $b->reference, 'guest' => $b->guest->fullName(), 'source' => $b->sourceLabel(),
                'channel' => $b->operator?->company_name ?? $b->channel->name, 'arrival' => $b->arrival->format('Y-m-d'), 'nights' => $b->nights(),
                'status' => $b->statusLabel(), 'total' => (float) $b->grand_total])->all(),
            'summary' => [
                'Bookings' => $bookings->count(),
                'Room nights' => $bookings->whereNotIn('status', ['cancelled', 'expired'])->sum(fn ($b) => $b->nights() * max(1, $b->villas->count())),
                'Cancelled' => $bookings->where('status', 'cancelled')->count(),
                'Booked value' => money($bookings->whereNotIn('status', ['cancelled', 'expired'])->sum('grand_total')),
            ],
            'chart' => $bookings->groupBy(fn ($b) => $b->sourceLabel())->map->count()->all(),
            'money' => ['total'],
        ];
    }

    private function reportOccupancy(Carbon $from, Carbon $to): array
    {
        $villaCount = Villa::where('is_active', true)->count();
        $nights = InventoryNight::whereNotNull('booking_villa_id')->whereBetween('stay_date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('bookingVilla.booking', fn ($q) => $q->whereIn('status', ['confirmed', 'checked_in', 'checked_out']))
            ->select('stay_date', DB::raw('COUNT(*) as sold'))->groupBy('stay_date')->pluck('sold', 'stay_date');
        $blocked = InventoryNight::whereNotNull('villa_block_id')->whereBetween('stay_date', [$from->toDateString(), $to->toDateString()])
            ->select('stay_date', DB::raw('COUNT(*) as c'))->groupBy('stay_date')->pluck('c', 'stay_date');
        $revenue = FolioLine::where('department', 'room')->whereBetween('business_date', [$from->toDateString(), $to->toDateString()])
            ->select('business_date', DB::raw('SUM(amount) as rev'))->groupBy('business_date')->pluck('rev', 'business_date');

        $rows = [];
        $chart = [];
        $tSold = $tAvail = $tRev = 0;
        foreach (CarbonPeriod::create($from, $to->copy()->startOfDay()) as $d) {
            $k = $d->toDateString();
            $sold = (int) ($nights[$k] ?? 0);
            $avail = max(0, $villaCount - (int) ($blocked[$k] ?? 0));
            $rev = (float) ($revenue[$k] ?? 0);
            $rows[] = ['date' => $k, 'available' => $avail, 'sold' => $sold, 'occupancy' => $avail ? round($sold / $avail * 100, 1).'%' : '—',
                'adr' => $sold ? round($rev / $sold, 2) : 0, 'revpar' => $avail ? round($rev / $avail, 2) : 0, 'revenue' => $rev];
            $chart[$d->format('d M')] = $avail ? round($sold / $avail * 100, 1) : 0;
            $tSold += $sold; $tAvail += $avail; $tRev += $rev;
        }
        return [
            'description' => 'Sold villa-nights vs sellable nights (out-of-order blocks excluded). ADR and RevPAR use posted room revenue (net).',
            'columns' => ['date' => 'Date', 'available' => 'Available', 'sold' => 'Sold', 'occupancy' => 'Occupancy', 'adr' => 'ADR', 'revpar' => 'RevPAR', 'revenue' => 'Room revenue'],
            'rows' => $rows,
            'summary' => ['Occupancy' => $tAvail ? round($tSold / $tAvail * 100, 1).'%' : '—', 'Nights sold' => $tSold,
                'ADR' => money($tSold ? $tRev / $tSold : 0), 'RevPAR' => money($tAvail ? $tRev / $tAvail : 0), 'Room revenue' => money($tRev)],
            'chart' => count($chart) <= 62 ? $chart : null,
            'money' => ['adr', 'revpar', 'revenue'],
        ];
    }

    private function reportRevenue(Carbon $from, Carbon $to): array
    {
        $lines = FolioLine::whereBetween('business_date', [$from->toDateString(), $to->toDateString()])
            ->select('department', DB::raw('SUM(amount) net'), DB::raw('SUM(service_amount) service'), DB::raw('SUM(tax_amount) tax'), DB::raw('SUM(total) total'))
            ->groupBy('department')->get();
        // Restaurant sales paid directly (not charged to a folio) are also revenue.
        $pos = PosOrder::whereIn('status', ['paid', 'refunded'])->whereBetween('closed_at', [$from, $to])
            ->select(DB::raw('SUM(subtotal - discount_amount) net'), DB::raw('SUM(service_charge) service'), DB::raw('SUM(tax_amount) tax'), DB::raw('SUM(total - refunded_amount) total'))->first();

        $rows = $lines->map(fn ($l) => ['department' => config('vaasal.departments.'.$l->department, $l->department).' (folio)', 'net' => (float) $l->net,
            'service' => (float) $l->service, 'tax' => (float) $l->tax, 'total' => (float) $l->total])->all();
        if ($pos && (float) $pos->total != 0) {
            $rows[] = ['department' => 'Restaurant & bar (direct POS)', 'net' => (float) $pos->net, 'service' => (float) $pos->service, 'tax' => (float) $pos->tax, 'total' => (float) $pos->total];
        }
        $sum = collect($rows);
        return [
            'description' => 'Folio postings by business date plus POS checks settled directly (cash/card).',
            'columns' => ['department' => 'Department', 'net' => 'Net', 'service' => 'Service charge', 'tax' => 'Tax', 'total' => 'Gross'],
            'rows' => $rows,
            'summary' => ['Net revenue' => money($sum->sum('net')), 'Service charge' => money($sum->sum('service')), 'Tax' => money($sum->sum('tax')), 'Gross' => money($sum->sum('total'))],
            'chart' => $sum->mapWithKeys(fn ($r) => [$r['department'] => round($r['net'], 2)])->all(),
            'money' => ['net', 'service', 'tax', 'total'],
        ];
    }

    private function reportPayments(Carbon $from, Carbon $to): array
    {
        $payments = Payment::with(['booking.guest', 'operator'])->where('status', 'completed')->where('method', '!=', 'city_ledger')
            ->whereBetween('paid_at', [$from, $to])->orderBy('paid_at')->get();
        $pos = PosPayment::whereBetween('created_at', [$from, $to])->where('method', '!=', 'room_charge')->get();
        $rows = $payments->map(fn ($p) => ['date' => $p->paid_at->format('Y-m-d H:i'), 'reference' => $p->reference,
            'payer' => $p->operator?->company_name ?? $p->booking?->guest?->fullName() ?? '—', 'booking' => $p->booking?->reference ?? '—',
            'type' => label($p->type), 'method' => $p->methodLabel(), 'amount' => (float) $p->amount])->all();
        $byMethod = $payments->groupBy(fn ($p) => $p->methodLabel())->map(fn ($g) => round($g->sum('amount'), 2));
        foreach ($pos->groupBy('method') as $m => $g) {
            $byMethod['POS '.$m] = round($g->sum('amount'), 2);
        }
        return [
            'description' => 'Money received (front office, operators, online) plus POS tenders. City-ledger transfers are excluded.',
            'columns' => ['date' => 'Date', 'reference' => 'Ref', 'payer' => 'Payer', 'booking' => 'Booking', 'type' => 'Type', 'method' => 'Method', 'amount' => 'Amount'],
            'rows' => $rows,
            'summary' => ['Front office & operators' => money($payments->sum('amount')), 'POS (cash/card/online)' => money($pos->sum('amount')),
                'Total received' => money($payments->sum('amount') + $pos->sum('amount'))],
            'chart' => $byMethod->all(),
            'money' => ['amount'],
        ];
    }

    private function reportOutstanding(Carbon $from, Carbon $to): array
    {
        $rows = [];
        foreach (\App\Models\Folio::with(['booking.guest', 'operator'])->where('status', 'open')->get() as $f) {
            $bal = $f->balance();
            if (abs($bal) < 0.01) continue;
            $rows[] = ['account' => $f->payerName(), 'type' => 'In-house folio '.$f->folio_no, 'booking' => $f->booking->reference, 'due' => '—', 'balance' => $bal];
        }
        foreach (\App\Models\Invoice::with('operator')->where('type', 'operator_invoice')->whereIn('status', ['issued', 'partially_paid'])->get() as $i) {
            $rows[] = ['account' => $i->operator?->company_name ?? $i->bill_to_name, 'type' => 'City ledger '.$i->number, 'booking' => $i->booking?->reference ?? '—',
                'due' => $i->due_date?->format('Y-m-d') ?? '—', 'balance' => (float) $i->balance];
        }
        $c = collect($rows);
        return [
            'description' => 'Open guest folios and unpaid tour-operator invoices as of now (date filter not applied).',
            'columns' => ['account' => 'Account', 'type' => 'Type', 'booking' => 'Booking', 'due' => 'Due', 'balance' => 'Balance'],
            'rows' => $c->sortByDesc('balance')->values()->all(),
            'summary' => ['Guest ledger' => money($c->filter(fn ($r) => str_starts_with($r['type'], 'In-house'))->sum('balance')),
                'City ledger' => money($c->filter(fn ($r) => str_starts_with($r['type'], 'City'))->sum('balance')), 'Total outstanding' => money($c->sum('balance'))],
            'money' => ['balance'],
        ];
    }

    private function reportGuests(Carbon $from, Carbon $to): array
    {
        $bookings = Booking::with('guest')->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])->whereBetween('arrival', [$from->toDateString(), $to->toDateString()])->get();
        $byCountry = $bookings->groupBy(fn ($b) => $b->guest->nationality ?: ($b->guest->country ?: 'Unknown'));
        $returning = $bookings->filter(fn ($b) => Booking::where('guest_id', $b->guest_id)->where('arrival', '<', $b->arrival)->whereIn('status', ['checked_out'])->exists())->count();
        $rows = $byCountry->map(fn ($g, $country) => ['country' => $country, 'bookings' => $g->count(), 'guests' => $g->sum(fn ($b) => $b->adults + $b->children),
            'nights' => $g->sum(fn ($b) => $b->nights()), 'value' => (float) $g->sum('grand_total')])->sortByDesc('bookings')->values()->all();
        return [
            'description' => 'Stays arriving in the period by nationality.',
            'columns' => ['country' => 'Nationality / country', 'bookings' => 'Bookings', 'guests' => 'Guests', 'nights' => 'Nights', 'value' => 'Value'],
            'rows' => $rows,
            'summary' => ['Stays' => $bookings->count(), 'Guests' => $bookings->sum(fn ($b) => $b->adults + $b->children), 'Returning guests' => $returning,
                'Avg. length of stay' => $bookings->count() ? round($bookings->avg(fn ($b) => $b->nights()), 1).' nights' : '—',
                'Avg. party size' => $bookings->count() ? round($bookings->avg(fn ($b) => $b->adults + $b->children), 1) : '—'],
            'chart' => collect($rows)->take(8)->mapWithKeys(fn ($r) => [$r['country'] => $r['bookings']])->all(),
            'money' => ['value'],
        ];
    }

    private function reportChannels(Carbon $from, Carbon $to): array
    {
        $bookings = Booking::with('channel')->whereNotIn('status', ['cancelled', 'expired', 'hold'])->whereBetween('arrival', [$from->toDateString(), $to->toDateString()])->get();
        $rows = $bookings->groupBy('channel_id')->map(function ($g) {
            $ch = $g->first()->channel;
            return ['channel' => $ch->name, 'type' => ucfirst($ch->type), 'bookings' => $g->count(), 'nights' => $g->sum(fn ($b) => $b->nights()),
                'revenue' => (float) $g->sum('grand_total'), 'commission' => (float) $g->sum('commission_amount')];
        })->sortByDesc('revenue')->values();
        $direct = $rows->where('type', 'Direct')->sum('revenue');
        return [
            'description' => 'Production by booking channel. OTA bookings arrive through the channel manager.',
            'columns' => ['channel' => 'Channel', 'type' => 'Type', 'bookings' => 'Bookings', 'nights' => 'Nights', 'revenue' => 'Revenue', 'commission' => 'Commission'],
            'rows' => $rows->all(),
            'summary' => ['OTA bookings' => $rows->where('type', 'Ota')->sum('bookings'), 'Direct share' => $rows->sum('revenue') ? round($direct / $rows->sum('revenue') * 100, 1).'%' : '—',
                'Commission cost' => money($rows->sum('commission'))],
            'chart' => $rows->mapWithKeys(fn ($r) => [$r['channel'] => $r['revenue']])->all(),
            'money' => ['revenue', 'commission'],
        ];
    }

    private function reportOperators(Carbon $from, Carbon $to): array
    {
        $rows = TourOperator::withTrashed()->get()->map(function ($op) use ($from, $to) {
            $b = $op->bookings()->whereNotIn('status', ['cancelled', 'expired'])->whereBetween('arrival', [$from->toDateString(), $to->toDateString()])->get();
            $comm = Commission::where('tour_operator_id', $op->id)->whereIn('booking_id', $b->pluck('id'))->get();
            return ['operator' => $op->company_name, 'status' => ucfirst($op->status), 'bookings' => $b->count(), 'nights' => $b->sum(fn ($x) => $x->nights()),
                'revenue' => (float) $b->sum('grand_total'), 'commission' => (float) $b->sum('commission_amount'),
                'commission_settled' => (float) $comm->where('status', 'settled')->sum('amount'), 'outstanding' => $op->outstanding()];
        })->filter(fn ($r) => $r['bookings'] > 0 || $r['outstanding'] > 0)->values();
        return [
            'description' => 'Tour operator production, commission and receivables.',
            'columns' => ['operator' => 'Operator', 'status' => 'Status', 'bookings' => 'Bookings', 'nights' => 'Nights', 'revenue' => 'Revenue',
                'commission' => 'Commission', 'commission_settled' => 'Comm. settled', 'outstanding' => 'Outstanding'],
            'rows' => $rows->all(),
            'summary' => ['Operator bookings' => $rows->sum('bookings'), 'Revenue' => money($rows->sum('revenue')), 'Commission' => money($rows->sum('commission')),
                'Outstanding' => money($rows->sum('outstanding'))],
            'chart' => $rows->mapWithKeys(fn ($r) => [$r['operator'] => $r['revenue']])->all(),
            'money' => ['revenue', 'commission', 'commission_settled', 'outstanding'],
        ];
    }

    private function reportPosSales(Carbon $from, Carbon $to): array
    {
        $orders = PosOrder::with(['outlet', 'waiter'])->whereIn('status', ['paid', 'charged_to_room', 'refunded'])->whereBetween('closed_at', [$from, $to])->get();
        $rows = $orders->groupBy(fn ($o) => $o->closed_at->toDateString())->map(fn ($g, $d) => [
            'date' => $d, 'checks' => $g->count(), 'covers' => $g->sum('covers'), 'gross' => (float) $g->sum('subtotal'), 'discounts' => (float) $g->sum('discount_amount'),
            'service' => (float) $g->sum('service_charge'), 'tax' => (float) $g->sum('tax_amount'), 'total' => (float) $g->sum('total'), 'refunds' => (float) $g->sum('refunded_amount'),
        ])->sortKeys()->values();
        $voids = PosOrderItem::where('status', 'void')->whereBetween('updated_at', [$from, $to])->sum('line_total');
        $hour = $orders->groupBy(fn ($o) => $o->opened_at->format('H').':00')->map->count()->sortKeys();
        return [
            'description' => 'Closed checks by day across all outlets.',
            'columns' => ['date' => 'Date', 'checks' => 'Checks', 'covers' => 'Covers', 'gross' => 'Gross', 'discounts' => 'Discounts', 'service' => 'Service', 'tax' => 'Tax', 'total' => 'Total', 'refunds' => 'Refunds'],
            'rows' => $rows->all(),
            'summary' => ['Net sales' => money($orders->sum('total') - $orders->sum('refunded_amount')), 'Checks' => $orders->count(),
                'Average check' => money($orders->count() ? $orders->avg('total') : 0), 'Charged to villas' => money($orders->where('status', 'charged_to_room')->sum('total')),
                'Discounts' => money($orders->sum('discount_amount')), 'Voided items' => money($voids)],
            'chart' => $hour->all(),
            'money' => ['gross', 'discounts', 'service', 'tax', 'total', 'refunds'],
        ];
    }

    private function reportPosItems(Carbon $from, Carbon $to): array
    {
        $items = PosOrderItem::with('menuItem.category')->where('status', '!=', 'void')
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['paid', 'charged_to_room', 'refunded'])->whereBetween('closed_at', [$from, $to]))->get();
        $rows = $items->groupBy('menu_item_id')->map(fn ($g) => [
            'item' => $g->first()->name, 'category' => $g->first()->menuItem?->category?->name ?? '—', 'qty' => (float) $g->sum('quantity'),
            'sales' => (float) $g->sum('line_total'), 'cost' => round((float) $g->sum(fn ($i) => (float) ($i->menuItem->cost ?? 0) * (float) $i->quantity), 2),
        ])->map(fn ($r) => $r + ['margin' => $r['sales'] > 0 ? round(($r['sales'] - $r['cost']) / $r['sales'] * 100, 1).'%' : '—'])->sortByDesc('sales')->values();
        return [
            'description' => 'Item sales mix and theoretical food cost.',
            'columns' => ['item' => 'Item', 'category' => 'Category', 'qty' => 'Qty', 'sales' => 'Sales', 'cost' => 'Theoretical cost', 'margin' => 'Margin'],
            'rows' => $rows->all(),
            'summary' => ['Items sold' => $rows->sum('qty'), 'Sales' => money($rows->sum('sales')),
                'Food cost %' => $rows->sum('sales') ? round($rows->sum('cost') / $rows->sum('sales') * 100, 1).'%' : '—'],
            'chart' => $rows->take(10)->mapWithKeys(fn ($r) => [$r['item'] => $r['sales']])->all(),
            'money' => ['sales', 'cost'],
        ];
    }

    private function reportInventory(Carbon $from, Carbon $to): array
    {
        $items = StockItem::with('supplier')->orderBy('category')->orderBy('name')->get();
        $moves = StockMovement::whereBetween('created_at', [$from, $to])->get()->groupBy('stock_item_id');
        $rows = $items->map(function ($i) use ($moves) {
            $m = $moves->get($i->id, collect());
            return ['sku' => $i->sku, 'item' => $i->name, 'category' => $i->category, 'on_hand' => $i->current_qty.' '.$i->unit, 'reorder' => $i->reorder_level,
                'value' => round($i->current_qty * (float) $i->unit_cost, 2), 'in' => (float) $m->where('type', 'in')->sum('quantity'),
                'used' => abs((float) $m->whereIn('type', ['sale', 'out'])->sum('quantity')), 'waste' => abs((float) $m->where('type', 'waste')->sum('quantity')),
                'status' => $i->isLow() ? 'LOW' : 'OK'];
        });
        $wasteValue = StockMovement::where('type', 'waste')->whereBetween('created_at', [$from, $to])->get()->sum(fn ($m) => abs($m->quantity) * (float) $m->unit_cost);
        return [
            'description' => 'Current stock valuation (weighted average cost) with movements in the period.',
            'columns' => ['sku' => 'SKU', 'item' => 'Item', 'category' => 'Category', 'on_hand' => 'On hand', 'reorder' => 'Reorder at', 'value' => 'Value',
                'in' => 'Received', 'used' => 'Used', 'waste' => 'Wasted', 'status' => 'Status'],
            'rows' => $rows->all(),
            'summary' => ['Stock value' => money($rows->sum('value')), 'Low-stock items' => $rows->where('status', 'LOW')->count(), 'Wastage value' => money($wasteValue)],
            'chart' => $rows->groupBy('category')->map(fn ($g) => round($g->sum('value'), 2))->all(),
            'money' => ['value'],
        ];
    }

    private function reportHousekeeping(Carbon $from, Carbon $to): array
    {
        $tasks = HkTask::with('assignee')->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])->get();
        $rows = $tasks->groupBy(fn ($t) => $t->assignee?->fullName() ?? 'Unassigned')->map(function ($g, $name) {
            $done = $g->where('status', 'completed');
            $durations = $done->map->durationMinutes()->filter();
            $inspected = $g->whereNotNull('inspection_result');
            return ['staff' => $name, 'tasks' => $g->count(), 'completed' => $done->count(), 'avg_minutes' => $durations->count() ? round($durations->avg()) : 0,
                'rejections' => (int) $g->sum('rejection_count'), 'pass_rate' => $inspected->count() ? round($done->count() / max(1, $done->count() + $g->sum('rejection_count')) * 100).'%' : '—'];
        })->values();
        return [
            'description' => 'Tasks scheduled in the period by housekeeper; average clean time from start to inspection request.',
            'columns' => ['staff' => 'Staff', 'tasks' => 'Tasks', 'completed' => 'Completed', 'avg_minutes' => 'Avg minutes', 'rejections' => 'Inspection fails', 'pass_rate' => 'First-time pass'],
            'rows' => $rows->all(),
            'summary' => ['Tasks' => $tasks->count(), 'Completed' => $tasks->where('status', 'completed')->count(), 'Open' => $tasks->whereIn('status', \App\Models\HkTask::OPEN)->count()],
            'chart' => $rows->mapWithKeys(fn ($r) => [$r['staff'] => $r['completed']])->all(),
        ];
    }

    private function reportAttendance(Carbon $from, Carbon $to): array
    {
        $records = AttendanceRecord::whereBetween('work_date', [$from->toDateString(), $to->toDateString()])->get()->groupBy('employee_id');
        $svc = app(\App\Modules\Staff\Services\AttendanceService::class);
        $rows = Employee::with('department')->where('status', '!=', 'terminated')->orderBy('first_name')->get()->map(function ($e) use ($records, $svc) {
            $s = $svc->summary($records->get($e->id, collect()));
            return ['employee' => $e->fullName(), 'department' => $e->department->name, 'days' => $s['days'], 'late_days' => $s['late_days'], 'absent' => $s['absent'],
                'leave' => $s['on_leave'], 'worked' => minutes_hm($s['worked']), 'overtime' => minutes_hm($s['overtime']), 'late' => minutes_hm($s['late'])];
        });
        return [
            'description' => 'Time card summary per employee.',
            'columns' => ['employee' => 'Employee', 'department' => 'Department', 'days' => 'Days worked', 'late_days' => 'Late days', 'absent' => 'Absent',
                'leave' => 'On leave', 'worked' => 'Hours', 'overtime' => 'Overtime', 'late' => 'Late time'],
            'rows' => $rows->all(),
            'summary' => ['Employees' => $rows->count(), 'Late arrivals' => $rows->sum('late_days'), 'Absences' => $rows->sum('absent')],
            'chart' => $rows->groupBy('department')->map(fn ($g) => $g->sum('days'))->all(),
        ];
    }

    private function reportMaintenance(Carbon $from, Carbon $to): array
    {
        $tickets = MaintenanceTicket::with(['villa', 'assignee'])->whereBetween('created_at', [$from, $to])->latest()->get();
        $resolved = $tickets->whereNotNull('resolved_at');
        return [
            'description' => 'Tickets reported in the period.',
            'columns' => ['ticket' => 'Ticket', 'villa' => 'Villa', 'title' => 'Issue', 'category' => 'Category', 'severity' => 'Severity', 'status' => 'Status', 'hours' => 'Hours to resolve', 'cost' => 'Cost'],
            'rows' => $tickets->map(fn ($t) => ['ticket' => $t->ticket_no, 'villa' => $t->villa?->code ?? $t->location, 'title' => $t->title,
                'category' => MaintenanceTicket::CATEGORIES[$t->category] ?? $t->category, 'severity' => ucfirst($t->severity), 'status' => MaintenanceTicket::STATUSES[$t->status],
                'hours' => $t->resolved_at ? round($t->created_at->diffInMinutes($t->resolved_at) / 60, 1) : '—', 'cost' => (float) $t->cost])->all(),
            'summary' => ['Tickets' => $tickets->count(), 'Open' => $tickets->whereNotIn('status', ['resolved', 'closed'])->count(),
                'Mean time to repair' => $resolved->count() ? round($resolved->avg(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at)) / 60, 1).' h' : '—',
                'Cost' => money($tickets->sum('cost'))],
            'chart' => $tickets->groupBy(fn ($t) => MaintenanceTicket::CATEGORIES[$t->category] ?? $t->category)->map->count()->all(),
            'money' => ['cost'],
        ];
    }

    private function reportKeycards(Carbon $from, Carbon $to): array
    {
        $logs = AccessLog::with(['villa', 'card'])->whereBetween('occurred_at', [$from, $to])->latest('occurred_at')->limit(1000)->get();
        return [
            'description' => 'Card issue/revoke events and lock access events (latest 1000).',
            'columns' => ['time' => 'Time', 'event' => 'Event', 'card' => 'Card UID', 'villa' => 'Villa', 'holder' => 'Holder', 'source' => 'Source'],
            'rows' => $logs->map(fn ($l) => ['time' => $l->occurred_at->format('Y-m-d H:i'), 'event' => label($l->event), 'card' => $l->card_uid,
                'villa' => $l->villa?->code ?? $l->lock_ref, 'holder' => $l->details['holder'] ?? '—', 'source' => $l->source])->all(),
            'summary' => ['Cards issued' => $logs->where('event', 'issued')->count(), 'Revoked' => $logs->where('event', 'revoked')->count(),
                'Denied at door' => $logs->where('event', 'denied')->count(), 'Active cards now' => KeyCardAssignment::where('status', 'active')->count()],
            'chart' => $logs->groupBy(fn ($l) => label($l->event))->map->count()->all(),
        ];
    }
}
