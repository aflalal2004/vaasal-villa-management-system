<?php

namespace App\Modules\POS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosTable;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\POS\Services\PosBillingService;
use App\Modules\POS\Services\PosOrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** JSON endpoints behind the POS terminal (session auth + CSRF; permissions via route middleware). */
class PosApiController extends Controller
{
    public function __construct(private PosOrderService $orders, private PosBillingService $billing) {}

    public function tables(Request $request)
    {
        $outlet = Outlet::findOrFail($request->query('outlet'));
        $open = PosOrder::with('waiter')->where('outlet_id', $outlet->id)->whereIn('status', ['open', 'billed'])->get();
        $reserved = app(\App\Modules\POS\Services\ReservationService::class)->upcomingByTable($outlet);
        return response()->json([
            'tables' => $outlet->tables()->where('is_active', true)->get()->map(function ($t) use ($open, $reserved) {
                $o = $open->firstWhere('pos_table_id', $t->id);
                $r = $reserved->get($t->id);
                return ['id' => $t->id, 'name' => $t->name, 'area' => $t->area, 'seats' => $t->seats,
                    'reservation' => $r ? ['time' => $r->reserved_for->format('H:i'), 'guest' => $r->guest_name, 'party' => $r->party_size] : null,
                    'order' => $o ? ['id' => $o->id, 'status' => $o->status, 'total' => (float) $o->total, 'covers' => $o->covers, 'waiter' => $o->waiter?->name, 'since' => $o->opened_at->format('H:i')] : null];
            }),
            'other' => $open->whereNull('pos_table_id')->values()->map(fn ($o) => ['id' => $o->id, 'order_no' => $o->order_no, 'type' => $o->type, 'guest' => $o->guest_name, 'total' => (float) $o->total, 'status' => $o->status]),
        ]);
    }

    public function menu(Request $request)
    {
        $outletId = (int) $request->query('outlet');
        $cats = MenuCategory::with(['items' => fn ($q) => $q->where('is_active', true), 'items.modifierGroups.modifiers' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)->where(fn ($q) => $q->whereNull('outlet_id')->orWhere('outlet_id', $outletId))->orderBy('sort_order')->get();
        return response()->json(['categories' => $cats->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'color' => $c->color,
            'items' => $c->items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'price' => (float) $i->price, 'image' => $i->imageUrl(), 'available' => $i->is_available, 'description' => $i->description,
                'groups' => $i->modifierGroups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'min' => $g->min_select, 'max' => $g->max_select,
                    'options' => $g->modifiers->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'price' => (float) $m->price])])]),
        ])]);
    }

    public function inHouse()
    {
        return response()->json(['guests' => Booking::with(['guest', 'activeVillas.villa'])->where('status', 'checked_in')->get()->map(fn ($b) => [
            'booking_id' => $b->id, 'reference' => $b->reference, 'guest' => $b->guest->fullName(), 'villas' => $b->activeVillas->pluck('villa.code')->implode(', '),
            'blocked' => $b->guest->is_blacklisted,
        ])]);
    }

    public function open(Request $request)
    {
        $data = $request->validate([
            'outlet_id' => ['required', 'exists:outlets,id'], 'pos_table_id' => ['nullable', 'exists:pos_tables,id'],
            'type' => ['required', Rule::in(['dine_in', 'takeaway', 'room_service'])], 'covers' => ['nullable', 'integer', 'min:1', 'max:40'],
            'booking_id' => ['nullable', 'required_if:type,room_service', 'exists:bookings,id'], 'guest_name' => ['nullable', 'string', 'max:120'],
        ]);
        $order = $this->orders->open(Outlet::findOrFail($data['outlet_id']), $data);
        return response()->json($this->serialize($order));
    }

    public function show(PosOrder $order)
    {
        return response()->json($this->serialize($order));
    }

    public function addItem(Request $request, PosOrder $order)
    {
        $data = $request->validate(['menu_item_id' => ['required', 'exists:menu_items,id'], 'qty' => ['nullable', 'numeric', 'min:0.5', 'max:99'],
            'modifiers' => ['nullable', 'array'], 'modifiers.*' => ['integer'], 'notes' => ['nullable', 'string', 'max:200'], 'seat' => ['nullable', 'integer', 'min:1', 'max:40']]);
        $this->orders->addItem($order, MenuItem::findOrFail($data['menu_item_id']), (float) ($data['qty'] ?? 1), $data['modifiers'] ?? [], $data['notes'] ?? null, $data['seat'] ?? null);
        return response()->json($this->serialize($order));
    }

    public function updateItem(Request $request, PosOrder $order, PosOrderItem $item)
    {
        abort_unless($item->pos_order_id === $order->id, 404);
        $data = $request->validate(['qty' => ['required', 'numeric', 'min:0', 'max:99']]);
        $this->orders->updateQuantity($item, (float) $data['qty']);
        return response()->json($this->serialize($order));
    }

    public function removeItem(Request $request, PosOrder $order, PosOrderItem $item)
    {
        abort_unless($item->pos_order_id === $order->id, 404);
        if ($item->status !== 'pending' && ! $request->user()->hasPermission('pos.void')) {
            throw new BusinessRuleException('Only a manager can void items already sent to the kitchen.');
        }
        $this->orders->voidItem($item, $request->input('reason'));
        return response()->json($this->serialize($order));
    }

    public function fire(PosOrder $order)
    {
        $kots = $this->orders->fire($order);
        return response()->json($this->serialize($order) + ['kots' => collect($kots)->map(fn ($k) => ['kot_no' => $k->kot_no, 'station' => $k->station, 'print_url' => route('pos.kot.print', [$order, $k])])]);
    }

    public function transfer(Request $request, PosOrder $order)
    {
        $data = $request->validate(['pos_table_id' => ['required', 'exists:pos_tables,id']]);
        $this->orders->transfer($order, PosTable::findOrFail($data['pos_table_id']));
        return response()->json($this->serialize($order));
    }

    public function merge(Request $request, PosOrder $order)
    {
        $data = $request->validate(['source_order_id' => ['required', 'exists:pos_orders,id']]);
        $this->orders->merge($order, PosOrder::findOrFail($data['source_order_id']));
        return response()->json($this->serialize($order));
    }

    public function split(Request $request, PosOrder $order)
    {
        $data = $request->validate(['lines' => ['required', 'array'], 'lines.*' => ['numeric', 'min:0']]);
        $new = $this->orders->split($order, $data['lines']);
        return response()->json($this->serialize($order) + ['new_order_id' => $new->id, 'new_order_no' => $new->order_no]);
    }

    public function discount(Request $request, PosOrder $order)
    {
        $data = $request->validate(['type' => ['nullable', Rule::in(['percent', 'fixed'])], 'value' => ['required', 'numeric', 'min:0'], 'reason' => ['nullable', 'string', 'max:200']]);
        $this->orders->applyDiscount($order, $data['type'] ?? 'percent', (float) $data['value'], $data['reason'] ?? null);
        return response()->json($this->serialize($order));
    }

    public function void(Request $request, PosOrder $order)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $this->orders->voidOrder($order, $data['reason']);
        return response()->json($this->serialize($order->fresh()));
    }

    public function printBill(PosOrder $order)
    {
        $this->billing->printBill($order);
        return response()->json($this->serialize($order) + ['print_url' => route('pos.bill.print', $order)]);
    }

    public function pay(Request $request, PosOrder $order)
    {
        $data = $request->validate([
            'tenders' => ['required', 'array', 'min:1', 'max:6'],
            'tenders.*.method' => ['required', Rule::in(array_keys(\App\Models\PosPayment::METHODS))],
            'tenders.*.amount' => ['required', 'numeric', 'min:0.01'],
            'tenders.*.tendered' => ['nullable', 'numeric', 'min:0'],
            'tenders.*.reference' => ['nullable', 'string', 'max:120'],
            'tenders.*.booking_id' => ['nullable', 'exists:bookings,id'],
        ]);
        $order = $this->billing->pay($order, $data['tenders']);
        return response()->json($this->serialize($order) + ['receipt_url' => route('pos.receipt', $order),
            'change' => (float) $order->payments()->where('method', 'cash')->sum('change_due')]);
    }

    public function notes(Request $request, PosOrder $order)
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500'], 'covers' => ['nullable', 'integer', 'min:1', 'max:40'], 'guest_name' => ['nullable', 'string', 'max:120']]);
        $order->update(array_filter($data, fn ($v) => $v !== null));
        return response()->json($this->serialize($order));
    }

    private function serialize(PosOrder $order): array
    {
        $order->refresh()->load(['items.kot', 'payments', 'table', 'outlet', 'booking.guest', 'villa', 'waiter']);
        return [
            'id' => $order->id, 'order_no' => $order->order_no, 'invoice_no' => $order->invoice_no, 'status' => $order->status, 'status_label' => $order->statusLabel(),
            'editable' => $order->isEditable(), 'type' => $order->type, 'covers' => $order->covers, 'notes' => $order->notes,
            'table' => $order->table ? ['id' => $order->table->id, 'name' => $order->table->name] : null,
            'outlet' => ['id' => $order->outlet->id, 'name' => $order->outlet->name, 'tax_pct' => (float) $order->outlet->tax_pct, 'service_pct' => (float) $order->outlet->service_charge_pct],
            'guest_name' => $order->guest_name, 'booking' => $order->booking ? ['id' => $order->booking->id, 'reference' => $order->booking->reference, 'guest' => $order->booking->guest->fullName(), 'villa' => $order->villa?->code] : null,
            'waiter' => $order->waiter?->name, 'opened_at' => $order->opened_at->format('H:i'),
            'items' => $order->items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'qty' => (float) $i->quantity, 'unit_price' => (float) $i->unit_price,
                'modifiers' => $i->modifiers ?? [], 'line_total' => (float) $i->line_total, 'notes' => $i->notes, 'status' => $i->status, 'kot' => $i->kot?->kot_no, 'kot_status' => $i->kot?->status])->values(),
            'subtotal' => (float) $order->subtotal, 'discount_type' => $order->discount_type, 'discount_value' => (float) $order->discount_value, 'discount' => (float) $order->discount_amount,
            'service' => (float) $order->service_charge, 'tax' => (float) $order->tax_amount, 'total' => (float) $order->total, 'paid' => (float) $order->paid_amount, 'balance' => $order->balance(),
            'payments' => $order->payments->map(fn ($p) => ['method' => $p->method, 'amount' => (float) $p->amount, 'type' => $p->type]),
        ];
    }
}
