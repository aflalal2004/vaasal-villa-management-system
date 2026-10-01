<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChargeItem;
use App\Modules\Core\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Guest service charge catalogue: laundry, spa, transport, airport transfer, minibar, excursions … */
class ChargeItemController extends Controller
{
    public function index()
    {
        return view('admin.billing.charge-items', ['items' => ChargeItem::orderBy('department')->orderBy('name')->get()->groupBy('department'), 'item' => null]);
    }

    public function create()
    {
        return view('admin.billing.charge-item-form', ['item' => new ChargeItem(['taxable' => true, 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $item = ChargeItem::create($this->validated($request));
        AuditService::log('billing', 'charge_item_created', $item, $item->name);
        return redirect()->route('admin.charge-items.index')->with('success', 'Service "'.$item->name.'" added.');
    }

    public function edit(ChargeItem $chargeItem)
    {
        return view('admin.billing.charge-item-form', ['item' => $chargeItem]);
    }

    public function update(Request $request, ChargeItem $chargeItem)
    {
        $chargeItem->fill($this->validated($request, $chargeItem));
        AuditService::logChanges('billing', $chargeItem);
        $chargeItem->save();
        return redirect()->route('admin.charge-items.index')->with('success', 'Service updated.');
    }

    private function validated(Request $request, ?ChargeItem $item = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('charge_items', 'code')->ignore($item?->id)],
            'name' => ['required', 'string', 'max:120'],
            'department' => ['required', Rule::in(array_keys(config('vaasal.departments')))],
            'price' => ['required', 'numeric', 'min:0'],
        ]);
        return $data + ['taxable' => $request->boolean('taxable'), 'service_chargeable' => $request->boolean('service_chargeable'), 'is_active' => $request->boolean('is_active')];
    }
}
