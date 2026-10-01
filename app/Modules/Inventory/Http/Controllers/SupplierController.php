<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Modules\Core\Services\AuditService;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $spend = StockMovement::where('type', 'in')->where('created_at', '>=', now()->subDays(90))->get()->groupBy('supplier_id')
            ->map(fn ($g) => $g->sum(fn ($m) => $m->quantity * (float) $m->unit_cost));
        return view('pos.inventory.suppliers', ['suppliers' => Supplier::withCount('stockItems')->orderBy('name')->get(), 'spend' => $spend]);
    }

    public function create()
    {
        return view('pos.inventory.supplier-form', ['s' => new Supplier(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $s = Supplier::create($this->validated($request));
        AuditService::log('inventory', 'supplier_created', $s, $s->name);
        return redirect()->route('pos.inventory.suppliers.index')->with('success', 'Supplier added.');
    }

    public function edit(Supplier $supplier)
    {
        return view('pos.inventory.supplier-form', ['s' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->fill($this->validated($request));
        AuditService::logChanges('inventory', $supplier);
        $supplier->save();
        return redirect()->route('pos.inventory.suppliers.index')->with('success', 'Supplier saved.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'], 'contact_name' => ['nullable', 'string', 'max:100'], 'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:300'], 'tax_id' => ['nullable', 'string', 'max:60'], 'notes' => ['nullable', 'string', 'max:500'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
