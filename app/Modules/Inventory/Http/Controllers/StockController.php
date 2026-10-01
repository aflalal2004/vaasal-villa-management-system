<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Modules\Core\Services\AuditService;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $items = StockItem::with('supplier')
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->when($request->query('filter') === 'low', fn ($q) => $q->low())
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->query('q').'%')->orWhere('sku', 'like', '%'.$request->query('q').'%')))
            ->orderBy('category')->orderBy('name')->get();
        return view('pos.inventory.index', [
            'items' => $items, 'categories' => StockItem::distinct()->orderBy('category')->pluck('category'),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'value' => StockItem::get()->sum(fn ($i) => $i->current_qty * (float) $i->unit_cost),
            'lowCount' => StockItem::low()->count(),
            'wasteMonth' => StockMovement::where('type', 'waste')->where('created_at', '>=', now()->startOfMonth())->get()->sum(fn ($m) => abs($m->quantity) * (float) $m->unit_cost),
        ]);
    }

    public function movements(Request $request)
    {
        $moves = StockMovement::with(['item', 'supplier', 'user'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('item'), fn ($q) => $q->where('stock_item_id', $request->query('item')))
            ->latest('id')->paginate(40)->withQueryString();
        return view('pos.inventory.movements', ['moves' => $moves, 'items' => StockItem::orderBy('name')->pluck('name', 'id')]);
    }

    public function store(Request $request)
    {
        $item = StockItem::create($this->validated($request));
        AuditService::log('inventory', 'item_created', $item, $item->name);
        return back()->with('success', $item->name.' added.');
    }

    public function update(Request $request, StockItem $item)
    {
        $data = $this->validated($request, $item);
        unset($data['current_qty']); // quantity changes only through movements
        $item->fill($data + ['is_active' => $request->boolean('is_active')]);
        AuditService::logChanges('inventory', $item);
        $item->save();
        return back()->with('success', $item->name.' saved.');
    }

    public function stockIn(Request $request)
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'], 'lines.*.stock_item_id' => ['nullable', 'exists:stock_items,id'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0.001'], 'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'], 'reference' => ['nullable', 'string', 'max:80'],
        ]);
        $supplier = ! empty($data['supplier_id']) ? Supplier::find($data['supplier_id']) : null;
        $n = 0;
        foreach ($data['lines'] as $l) {
            if (empty($l['stock_item_id']) || empty($l['quantity'])) continue;
            $item = StockItem::findOrFail($l['stock_item_id']);
            $this->inventory->stockIn($item, (float) $l['quantity'], (float) ($l['unit_cost'] ?? $item->unit_cost), $supplier, $data['reference'] ?? null, 'Goods received');
            $n++;
        }
        return back()->with('success', $n.' item(s) received into stock.');
    }

    public function stockOut(Request $request)
    {
        $data = $request->validate([
            'stock_item_id' => ['required', 'exists:stock_items,id'], 'quantity' => ['required', 'numeric', 'min:0.001'],
            'type' => ['required', Rule::in(['out', 'waste'])], 'reason' => ['nullable', 'required_if:type,waste', 'string', 'max:200'],
        ], ['reason.required_if' => 'Give a reason for wastage (spoiled, dropped, expired…).']);
        $this->inventory->stockOut(StockItem::findOrFail($data['stock_item_id']), (float) $data['quantity'], $data['type'], $data['reason'] ?? null);
        return back()->with('success', $data['type'] === 'waste' ? 'Wastage recorded.' : 'Stock issued.');
    }

    public function count(Request $request)
    {
        $data = $request->validate(['counts' => ['required', 'array'], 'counts.*' => ['nullable', 'numeric', 'min:0'], 'reason' => ['nullable', 'string', 'max:200']]);
        $n = 0;
        foreach ($data['counts'] as $id => $qty) {
            if ($qty === null || $qty === '') continue;
            if ($this->inventory->adjust(StockItem::findOrFail($id), (float) $qty, $data['reason'] ?? 'Stock count')) $n++;
        }
        return back()->with('success', 'Stock count saved; '.$n.' variance(s) posted.');
    }

    public function recipes()
    {
        return view('pos.inventory.recipes', [
            'items' => MenuItem::with(['recipe.stockItem', 'category'])->where('is_active', true)->orderBy('menu_category_id')->orderBy('name')->get(),
            'stock' => StockItem::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function saveRecipe(Request $request)
    {
        $data = $request->validate(['menu_item_id' => ['required', 'exists:menu_items,id'], 'stock_item_id' => ['required', 'exists:stock_items,id'], 'quantity' => ['required', 'numeric', 'min:0.001']]);
        Recipe::updateOrCreate(['menu_item_id' => $data['menu_item_id'], 'stock_item_id' => $data['stock_item_id']], ['quantity' => $data['quantity']]);
        return back()->with('success', 'Recipe line saved.');
    }

    public function destroyRecipe(Recipe $recipe)
    {
        $recipe->delete();
        return back()->with('success', 'Recipe line removed.');
    }

    private function validated(Request $request, ?StockItem $item = null): array
    {
        return $request->validate([
            'sku' => ['required', 'string', 'max:30', Rule::unique('stock_items', 'sku')->ignore($item?->id)],
            'name' => ['required', 'string', 'max:120'], 'category' => ['required', 'string', 'max:40'],
            'unit' => ['required', Rule::in(['kg', 'g', 'l', 'ml', 'pcs', 'btl', 'pack', 'box'])],
            'reorder_level' => ['required', 'numeric', 'min:0'], 'unit_cost' => ['required', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'], 'current_qty' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
