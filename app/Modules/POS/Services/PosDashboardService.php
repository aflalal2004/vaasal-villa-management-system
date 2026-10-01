<?php

namespace App\Modules\POS\Services;

use App\Models\Kot;
use App\Models\MenuItem;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosPayment;
use App\Models\PosTable;
use App\Models\StockItem;
use App\Models\TableReservation;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/** Restaurant POS dashboard. Every figure is a live query on POS tables — nothing is estimated or mocked. */
class PosDashboardService
{
    /** Orders that count as sales (closed, not voided/merged). */
    public const SOLD = ['paid', 'charged_to_room', 'refunded'];

    public function build(): array
    {
        $today = now()->startOfDay();
        $soldToday = PosOrder::whereIn('status', self::SOLD)->where('closed_at', '>=', $today);
        $refundsToday = abs((float) PosPayment::where('type', 'refund')->where('created_at', '>=', $today)->sum('amount'));

        $activeTables = PosTable::where('is_active', true)->whereHas('outlet', fn ($q) => $q->where('is_active', true));
        $occupied = PosOrder::whereIn('status', ['open', 'billed'])->whereNotNull('pos_table_id')->distinct()->count('pos_table_id');

        $from = now()->subDays(6)->startOfDay();
        $daily = PosOrder::whereIn('status', self::SOLD)->where('closed_at', '>=', $from)
            ->selectRaw('DATE(closed_at) d, SUM(total) t, COUNT(*) n')->groupBy('d')->get()->keyBy('d');
        $week = collect(CarbonPeriod::create($from, now()->startOfDay()))->map(fn ($day) => [
            'label' => $day->isToday() ? 'Today' : $day->format('D'),
            'date' => $day->toDateString(),
            'total' => round((float) ($daily[$day->toDateString()]->t ?? 0), 2),
            'orders' => (int) ($daily[$day->toDateString()]->n ?? 0),
        ])->values();

        $popular = PosOrderItem::query()->where('pos_order_items.status', '!=', 'void')
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_order_items.pos_order_id')
            ->whereIn('pos_orders.status', self::SOLD)->where('pos_orders.closed_at', '>=', now()->subDays(30))
            ->selectRaw('menu_item_id, SUM(quantity) qty, SUM(line_total) revenue')->groupBy('menu_item_id')->orderByDesc('qty')->limit(4)->get();
        $items = MenuItem::with('category')->whereIn('id', $popular->pluck('menu_item_id'))->get()->keyBy('id');

        return [
            'sales_today' => round((float) (clone $soldToday)->sum('total'), 2),
            'refunds_today' => round($refundsToday, 2),
            'orders_today' => PosOrder::where('opened_at', '>=', $today)->whereNotIn('status', ['void', 'merged'])->count(),
            'open_checks' => PosOrder::whereIn('status', ['open', 'billed'])->count(),
            'tables_total' => (clone $activeTables)->count(),
            'tables_occupied' => $occupied,
            'pending_kot' => Kot::whereIn('status', ['new', 'preparing'])->count(),
            'room_charges_today' => PosPayment::where('method', 'room_charge')->where('type', 'payment')->where('created_at', '>=', $today)->count(),
            'room_charges_amount' => round((float) PosPayment::where('method', 'room_charge')->where('created_at', '>=', $today)->sum('amount'), 2),
            'week' => $week,
            'week_max' => max(1, (float) $week->max('total')),
            'popular' => $popular->map(fn ($p) => ['item' => $items[$p->menu_item_id] ?? null, 'qty' => (float) $p->qty, 'revenue' => (float) $p->revenue])->filter(fn ($p) => $p['item'])->values(),
            'recent' => PosOrder::with(['table', 'villa', 'items' => fn ($q) => $q->where('status', '!=', 'void')->with('menuItem')])
                ->whereNotIn('status', ['merged'])->latest('opened_at')->limit(6)->get(),
            'reservations' => TableReservation::with('table')->whereIn('status', TableReservation::ACTIVE)->where('reserved_for', '>=', now()->subHour())
                ->orderBy('reserved_for')->limit(5)->get(),
            'low_stock' => StockItem::low()->orderByRaw('current_qty / NULLIF(reorder_level, 0)')->limit(5)->get(),
            'low_stock_count' => StockItem::low()->count(),
            'kot_by_status' => Kot::where('created_at', '>=', $today)->select('status', DB::raw('COUNT(*) n'))->groupBy('status')->pluck('n', 'status'),
        ];
    }
}
