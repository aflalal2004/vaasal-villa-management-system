<?php

namespace App\Modules\POS\Services;

use App\Models\Booking;
use App\Models\Kot;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\Outlet;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosTable;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\DocumentNumberService;
use Illuminate\Support\Facades\DB;

/**
 * Restaurant order lifecycle: open, add/edit items with modifiers, fire KOTs to stations,
 * table transfer, merge and split checks, discounts, void. Totals are recalculated server-side.
 */
class PosOrderService
{
    public function open(Outlet $outlet, array $data): PosOrder
    {
        $type = $data['type'] ?? 'dine_in';
        $table = ! empty($data['pos_table_id']) ? PosTable::where('outlet_id', $outlet->id)->findOrFail($data['pos_table_id']) : null;
        if ($type === 'dine_in' && ! $table) {
            throw new BusinessRuleException('Select a table for dine-in orders.');
        }
        if ($table && $table->openOrder) {
            return $table->openOrder;
        }
        $booking = null;
        if ($type === 'room_service') {
            $booking = Booking::where('status', 'checked_in')->findOrFail($data['booking_id'] ?? 0);
        }

        $order = PosOrder::create([
            'outlet_id' => $outlet->id,
            'order_no' => DocumentNumberService::next('pos_order'),
            'pos_table_id' => $table?->id,
            'type' => $type,
            'waiter_id' => auth()->id(),
            'covers' => max(1, (int) ($data['covers'] ?? 1)),
            'booking_id' => $booking?->id,
            'villa_id' => $booking?->activeVillas()->first()?->villa_id,
            'guest_name' => $data['guest_name'] ?? $booking?->guest->fullName(),
            'opened_at' => now(),
        ]);
        AuditService::log('pos', 'order_opened', $order, "{$order->order_no} ".($table ? 'table '.$table->name : $type));
        return $order;
    }

    /** @param int[] $modifierIds */
    public function addItem(PosOrder $order, MenuItem $item, float $qty = 1, array $modifierIds = [], ?string $notes = null, ?int $seat = null): PosOrderItem
    {
        $this->assertEditable($order);
        if (! $item->is_active || ! $item->is_available) {
            throw new BusinessRuleException("{$item->name} is not available right now.");
        }
        if ($qty <= 0) throw new BusinessRuleException('Quantity must be at least 1.');

        $item->loadMissing('modifierGroups.modifiers', 'category');
        $mods = Modifier::whereIn('id', $modifierIds)->where('is_active', true)->get();
        foreach ($item->modifierGroups as $group) {
            $chosen = $mods->where('modifier_group_id', $group->id)->count();
            if ($chosen < $group->min_select) throw new BusinessRuleException("Choose at least {$group->min_select} option(s) for {$group->name}.");
            if ($group->max_select > 0 && $chosen > $group->max_select) throw new BusinessRuleException("Choose at most {$group->max_select} option(s) for {$group->name}.");
        }
        $allowedGroupIds = $item->modifierGroups->pluck('id');
        if ($mods->reject(fn ($m) => $allowedGroupIds->contains($m->modifier_group_id))->isNotEmpty()) {
            throw new BusinessRuleException('One of the selected options does not apply to this item.');
        }

        $modTotal = (float) $mods->sum('price');

        // Tapping the same item again (same options, no note, not yet sent) increases the quantity.
        $modSnapshot = $mods->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'price' => (float) $m->price])->values()->all();
        if (! $notes && ! $seat) {
            $same = $order->items()->where('menu_item_id', $item->id)->where('status', 'pending')->whereNull('notes')->whereNull('seat')->get()
                ->first(fn ($l) => collect($l->modifiers ?? [])->pluck('id')->sort()->values()->all() === $mods->pluck('id')->sort()->values()->all());
            if ($same) {
                $newQty = (float) $same->quantity + $qty;
                $same->update(['quantity' => $newQty, 'line_total' => round(((float) $same->unit_price + (float) $same->modifiers_total) * $newQty, 2)]);
                $this->recalc($order);
                return $same;
            }
        }

        $line = $order->items()->create([
            'menu_item_id' => $item->id,
            'name' => $item->name,
            'quantity' => $qty,
            'unit_price' => $item->price,
            'modifiers' => $modSnapshot,
            'modifiers_total' => $modTotal,
            'line_total' => round(($item->price + $modTotal) * $qty, 2),
            'notes' => $notes,
            'seat' => $seat,
            'station' => $item->effectiveStation(),
        ]);
        $this->recalc($order);
        return $line;
    }

    public function updateQuantity(PosOrderItem $line, float $qty): void
    {
        $order = $line->order;
        $this->assertEditable($order);
        if ($line->status !== 'pending') {
            throw new BusinessRuleException('Items already sent to the kitchen cannot be changed. Void and re-order instead.');
        }
        if ($qty <= 0) {
            $line->delete();
        } else {
            $line->update(['quantity' => $qty, 'line_total' => round(((float) $line->unit_price + (float) $line->modifiers_total) * $qty, 2)]);
        }
        $this->recalc($order);
    }

    /** Remove a pending item, or void a fired item (requires pos.void permission, checked by controller). */
    public function voidItem(PosOrderItem $line, ?string $reason): void
    {
        $order = $line->order;
        $this->assertEditable($order);
        if ($line->status === 'pending') {
            $line->delete();
        } else {
            if (! $reason) throw new BusinessRuleException('A reason is required to void an item already sent to the kitchen.');
            $line->update(['status' => 'void', 'void_reason' => $reason, 'voided_by' => auth()->id()]);
            AuditService::log('pos', 'item_voided', $line, "{$order->order_no}: {$line->name} ×{$line->quantity} — {$reason}");
        }
        $this->recalc($order);
    }

    /** Send pending items to their stations: one KOT per station. */
    public function fire(PosOrder $order): array
    {
        $this->assertEditable($order);
        $pending = $order->items()->where('status', 'pending')->get();
        if ($pending->isEmpty()) {
            throw new BusinessRuleException('There are no new items to send to the kitchen.');
        }
        return DB::transaction(function () use ($order, $pending) {
            $kots = [];
            foreach ($pending->groupBy('station') as $station => $items) {
                $kot = Kot::create(['pos_order_id' => $order->id, 'kot_no' => DocumentNumberService::next('kot'), 'station' => $station]);
                PosOrderItem::whereIn('id', $items->pluck('id'))->update(['kot_id' => $kot->id, 'status' => 'fired', 'fired_at' => now()]);
                $kots[] = $kot;
            }
            AuditService::log('pos', 'kot_fired', $order, $order->order_no.': '.count($kots).' KOT(s)');
            return $kots;
        });
    }

    public function transfer(PosOrder $order, PosTable $to): PosOrder
    {
        $this->assertEditable($order);
        if ($to->outlet_id !== $order->outlet_id) throw new BusinessRuleException('Tables must be in the same outlet.');
        if ($to->openOrder && $to->openOrder->id !== $order->id) {
            throw new BusinessRuleException("Table {$to->name} already has an open check. Use Merge instead.");
        }
        $from = $order->table?->name;
        $order->update(['pos_table_id' => $to->id]);
        AuditService::log('pos', 'table_transfer', $order, "{$order->order_no}: {$from} → {$to->name}");
        return $order;
    }

    /** Merge $source into $target (e.g. two tables joined). */
    public function merge(PosOrder $target, PosOrder $source): PosOrder
    {
        $this->assertEditable($target);
        $this->assertEditable($source);
        if ($target->id === $source->id) throw new BusinessRuleException('Choose a different check to merge.');
        if ((float) $source->paid_amount > 0) throw new BusinessRuleException('A check with payments cannot be merged.');
        DB::transaction(function () use ($target, $source) {
            $source->items()->update(['pos_order_id' => $target->id]);
            $source->kots()->update(['pos_order_id' => $target->id]);
            $source->update(['status' => 'merged', 'merged_into_id' => $target->id, 'closed_at' => now(), 'subtotal' => 0, 'total' => 0]);
            $target->update(['covers' => $target->covers + $source->covers]);
            $this->recalc($target);
            AuditService::log('pos', 'merged', $target, "{$source->order_no} merged into {$target->order_no}");
        });
        return $target->fresh();
    }

    /**
     * Split selected lines (optionally partial quantities) into a new check on the same table.
     * @param array $lines [item_id => qty_to_move]
     */
    public function split(PosOrder $order, array $lines): PosOrder
    {
        $this->assertEditable($order);
        if ((float) $order->paid_amount > 0) throw new BusinessRuleException('Split the check before taking any payment.');
        $lines = array_filter($lines, fn ($q) => (float) $q > 0);
        if (! $lines) throw new BusinessRuleException('Select at least one item to move to the new check.');

        return DB::transaction(function () use ($order, $lines) {
            $new = PosOrder::create([
                'outlet_id' => $order->outlet_id, 'order_no' => DocumentNumberService::next('pos_order'), 'pos_table_id' => $order->pos_table_id,
                'type' => $order->type, 'waiter_id' => $order->waiter_id, 'covers' => 1, 'booking_id' => $order->booking_id,
                'villa_id' => $order->villa_id, 'guest_name' => $order->guest_name, 'opened_at' => now(),
            ]);
            $movedAll = true;
            foreach ($order->items()->where('status', '!=', 'void')->get() as $line) {
                $qty = (float) ($lines[$line->id] ?? 0);
                if ($qty <= 0) { $movedAll = false; continue; }
                $qty = min($qty, (float) $line->quantity);
                if ($qty >= (float) $line->quantity) {
                    $line->update(['pos_order_id' => $new->id]);
                } else {
                    $unit = (float) $line->unit_price + (float) $line->modifiers_total;
                    $copy = $line->replicate(['pos_order_id']);
                    $copy->fill(['pos_order_id' => $new->id, 'quantity' => $qty, 'line_total' => round($unit * $qty, 2)])->save();
                    $line->update(['quantity' => (float) $line->quantity - $qty, 'line_total' => round($unit * ((float) $line->quantity - $qty), 2)]);
                    $movedAll = false;
                }
            }
            if ($movedAll) throw new BusinessRuleException('Leave at least one item on the original check.');
            $this->recalc($order);
            $this->recalc($new);
            AuditService::log('pos', 'split', $order, "{$order->order_no} split → {$new->order_no}");
            return $new;
        });
    }

    public function applyDiscount(PosOrder $order, ?string $type, float $value, ?string $reason): PosOrder
    {
        $this->assertEditable($order);
        if ($type && ! in_array($type, ['percent', 'fixed'], true)) throw new BusinessRuleException('Invalid discount type.');
        if ($type === 'percent' && ($value < 0 || $value > 100)) throw new BusinessRuleException('Percentage must be between 0 and 100.');
        if ($type && $value > 0 && ! $reason) throw new BusinessRuleException('Enter a reason for the discount.');
        $order->update(['discount_type' => $value > 0 ? $type : null, 'discount_value' => $value > 0 ? $value : 0, 'discount_reason' => $value > 0 ? $reason : null]);
        $this->recalc($order);
        AuditService::log('pos', 'discount', $order, "{$order->order_no}: {$type} {$value} — {$reason}");
        return $order->fresh();
    }

    public function voidOrder(PosOrder $order, string $reason): void
    {
        $this->assertEditable($order);
        if ((float) $order->paid_amount > 0) throw new BusinessRuleException('Refund the payments before voiding this check.');
        $order->items()->where('status', '!=', 'void')->update(['status' => 'void', 'void_reason' => $reason, 'voided_by' => auth()->id()]);
        $order->kots()->update(['status' => 'cancelled']);
        $order->update(['status' => 'void', 'voided_by' => auth()->id(), 'void_reason' => $reason, 'closed_at' => now()]);
        AuditService::log('pos', 'order_voided', $order, $reason);
    }

    public function recalc(PosOrder $order): PosOrder
    {
        $order->refresh();
        $outlet = $order->outlet;
        $subtotal = round((float) $order->items()->where('status', '!=', 'void')->sum('line_total'), 2);
        $discount = match ($order->discount_type) {
            'percent' => round($subtotal * (float) $order->discount_value / 100, 2),
            'fixed' => min($subtotal, (float) $order->discount_value),
            default => 0,
        };
        $net = $subtotal - $discount;
        $service = round($net * (float) $outlet->service_charge_pct / 100, 2);
        $tax = round(($net + $service) * (float) $outlet->tax_pct / 100, 2);
        $order->update(['subtotal' => $subtotal, 'discount_amount' => $discount, 'service_charge' => $service, 'tax_amount' => $tax,
            'total' => round($net + $service + $tax, 2), 'version' => $order->version + 1]);
        return $order;
    }

    private function assertEditable(PosOrder $order): void
    {
        if (! $order->isEditable()) {
            throw new BusinessRuleException("Check {$order->order_no} is {$order->statusLabel()} and can no longer be changed.");
        }
    }
}
