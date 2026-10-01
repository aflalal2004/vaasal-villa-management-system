<?php

namespace App\Modules\POS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\PosTable;
use App\Models\Property;
use App\Modules\Core\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OutletController extends Controller
{
    public function index()
    {
        return view('pos.outlets', ['outlets' => Outlet::with('tables')->get()]);
    }

    public function store(Request $request)
    {
        $o = Outlet::create($this->validated($request) + ['property_id' => Property::current()->id]);
        AuditService::log('pos', 'outlet_created', $o, $o->name);
        return back()->with('success', 'Outlet added.');
    }

    public function update(Request $request, Outlet $outlet)
    {
        $outlet->fill($this->validated($request, $outlet) + ['is_active' => $request->boolean('is_active')]);
        AuditService::logChanges('pos', $outlet);
        $outlet->save();
        return back()->with('success', $outlet->name.' saved.');
    }

    public function storeTable(Request $request, Outlet $outlet)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:30', Rule::unique('pos_tables')->where('outlet_id', $outlet->id)], 'area' => ['nullable', 'string', 'max:40'], 'seats' => ['required', 'integer', 'min:1', 'max:40']]);
        $outlet->tables()->create($data + ['area' => $data['area'] ?? 'Main', 'sort_order' => $outlet->tables()->count() + 1]);
        return back()->with('success', 'Table '.$data['name'].' added.');
    }

    public function updateTable(Request $request, PosTable $table)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:30', Rule::unique('pos_tables')->where('outlet_id', $table->outlet_id)->ignore($table->id)], 'area' => ['nullable', 'string', 'max:40'], 'seats' => ['required', 'integer', 'min:1', 'max:40']]);
        $table->update($data + ['is_active' => $request->boolean('is_active')]);
        return back()->with('success', 'Table saved.');
    }

    private function validated(Request $request, ?Outlet $outlet = null): array
    {
        return $request->validate([
            'code' => ['required', 'alpha_dash', 'max:20', Rule::unique('outlets', 'code')->ignore($outlet?->id)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['restaurant', 'bar', 'room_service', 'pool_bar'])],
            'folio_department' => ['required', Rule::in(array_keys(config('vaasal.departments')))],
            'tax_pct' => ['required', 'numeric', 'min:0', 'max:50'],
            'service_charge_pct' => ['required', 'numeric', 'min:0', 'max:30'],
            'receipt_header' => ['nullable', 'string', 'max:300'],
            'receipt_footer' => ['nullable', 'string', 'max:300'],
        ]);
    }
}
