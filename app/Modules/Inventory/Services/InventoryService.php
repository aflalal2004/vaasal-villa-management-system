<?php

namespace App\Modules\Inventory\Services;

use App\Models\PosOrder;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Stock ledger. Every change is a signed movement with the running balance; the item's
 * current_qty is updated under a row lock. Sales deplete stock through menu recipes.
 */
class InventoryService
{
    public function stockIn(StockItem $item, float $qty, float $unitCost, ?Supplier $supplier = null, ?string $reference = null, ?string $reason = null): StockMovement
    {
        if ($qty <= 0) throw new BusinessRuleException('Quantity must be greater than zero.');
        return DB::transaction(function () use ($item, $qty, $unitCost, $supplier, $reference, $reason) {
            $item = StockItem::lockForUpdate()->findOrFail($item->id);
            // Weighted average cost
            $newQty = $item->current_qty + $qty;
            $avg = $newQty > 0 ? (($item->current_qty * (float) $item->unit_cost) + ($qty * $unitCost)) / $newQty : $unitCost;
            $item->update(['current_qty' => $newQty, 'unit_cost' => round($avg, 2), 'supplier_id' => $supplier?->id ?? $item->supplier_id]);
            return $this->movement($item, 'in', $qty, $unitCost, $supplier, $reference, $reason);
        });
    }

    /** Manual issue (out) or wastage. */
    public function stockOut(StockItem $item, float $qty, string $type = 'out', ?string $reason = null, ?string $reference = null): StockMovement
    {
        if ($qty <= 0) throw new BusinessRuleException('Quantity must be greater than zero.');
        if (! in_array($type, ['out', 'waste'], true)) throw new BusinessRuleException('Invalid movement type.');
        if ($type === 'waste' && ! $reason) throw new BusinessRuleException('A reason is required for wastage.');

        $movement = DB::transaction(function () use ($item, $qty, $type, $reason, $reference) {
            $item = StockItem::lockForUpdate()->findOrFail($item->id);
            if ($qty > $item->current_qty + 0.0005) {
                throw new BusinessRuleException("Only {$item->current_qty} {$item->unit} of {$item->name} in stock.");
            }
            $item->update(['current_qty' => $item->current_qty - $qty]);
            return $this->movement($item, $type, -$qty, (float) $item->unit_cost, null, $reference, $reason);
        });
        $this->alertIfLow($item->fresh());
        return $movement;
    }

    /** Stock count: set the counted quantity and record the variance. */
    public function adjust(StockItem $item, float $countedQty, string $reason): ?StockMovement
    {
        return DB::transaction(function () use ($item, $countedQty, $reason) {
            $item = StockItem::lockForUpdate()->findOrFail($item->id);
            $diff = round($countedQty - $item->current_qty, 3);
            if (abs($diff) < 0.0005) return null;
            $item->update(['current_qty' => $countedQty]);
            AuditService::log('inventory', 'adjusted', $item, "Count {$countedQty} (variance {$diff}): {$reason}");
            return $this->movement($item, 'adjust', $diff, (float) $item->unit_cost, null, 'Stock count', $reason);
        });
    }

    /** Deplete ingredients for a paid order via recipes. Negative stock is allowed but flagged. */
    public function depleteForOrder(PosOrder $order): void
    {
        $order->loadMissing('liveItems.menuItem.recipe');
        $low = [];
        DB::transaction(function () use ($order, &$low) {
            foreach ($order->liveItems as $line) {
                foreach ($line->menuItem->recipe ?? [] as $r) {
                    $item = StockItem::lockForUpdate()->find($r->stock_item_id);
                    if (! $item) continue;
                    $qty = round((float) $r->quantity * (float) $line->quantity, 3);
                    $wasLow = $item->isLow();
                    $item->update(['current_qty' => $item->current_qty - $qty]);
                    $m = $this->movement($item, 'sale', -$qty, (float) $item->unit_cost, null, $order->invoice_no ?? $order->order_no, null);
                    $m->update(['source_type' => 'pos_order', 'source_id' => $order->id]);
                    if (! $wasLow && $item->fresh()->isLow()) $low[] = $item->id;
                }
            }
        });
        foreach (array_unique($low) as $id) {
            NotificationService::lowStock(StockItem::find($id));
        }
    }

    private function movement(StockItem $item, string $type, float $qty, float $unitCost, ?Supplier $supplier, ?string $reference, ?string $reason): StockMovement
    {
        return StockMovement::create([
            'stock_item_id' => $item->id, 'type' => $type, 'quantity' => $qty, 'unit_cost' => $unitCost,
            'balance_after' => $item->current_qty, 'supplier_id' => $supplier?->id, 'reference' => $reference, 'reason' => $reason, 'user_id' => auth()->id(),
        ]);
    }

    private function alertIfLow(StockItem $item): void
    {
        if ($item->isLow()) {
            NotificationService::lowStock($item);
        }
    }
}
