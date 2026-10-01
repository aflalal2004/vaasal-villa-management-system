<?php

namespace App\Modules\POS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Outlet;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    public function index()
    {
        return view('pos.menu', [
            'categories' => MenuCategory::with(['items.modifierGroups', 'outlet'])->orderBy('sort_order')->get(),
            'groups' => ModifierGroup::with('modifiers')->orderBy('name')->get(),
            'outlets' => Outlet::pluck('name', 'id'),
        ]);
    }

    public function storeCategory(Request $request)
    {
        MenuCategory::create($this->category($request));
        return back()->with('success', 'Category added.');
    }

    public function updateCategory(Request $request, MenuCategory $category)
    {
        $category->update($this->category($request) + ['is_active' => $request->boolean('is_active')]);
        return back()->with('success', 'Category saved.');
    }

    public function create(Request $request)
    {
        return view('pos.menu-item', ['item' => new MenuItem(['is_active' => true, 'is_available' => true, 'prep_minutes' => 10, 'menu_category_id' => $request->query('category')]),
            'categories' => MenuCategory::orderBy('sort_order')->pluck('name', 'id'), 'groups' => ModifierGroup::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $item = MenuItem::create($this->item($request));
        $item->modifierGroups()->sync($request->input('groups', []));
        AuditService::log('pos', 'menu_item_created', $item, $item->name.' '.money($item->price));
        return redirect()->route('pos.menu.index')->with('success', $item->name.' added to the menu.');
    }

    public function edit(MenuItem $item)
    {
        return view('pos.menu-item', ['item' => $item->load('modifierGroups'), 'categories' => MenuCategory::orderBy('sort_order')->pluck('name', 'id'), 'groups' => ModifierGroup::orderBy('name')->get()]);
    }

    public function update(Request $request, MenuItem $item)
    {
        $item->fill($this->item($request, $item));
        AuditService::logChanges('pos', $item, 'menu_item_updated');
        $item->save();
        $item->modifierGroups()->sync($request->input('groups', []));
        return redirect()->route('pos.menu.index')->with('success', $item->name.' saved.');
    }

    /** 86 / un-86 an item (sold out) — instant from the menu screen. */
    public function toggle(MenuItem $item)
    {
        $item->update(['is_available' => ! $item->is_available]);
        AuditService::log('pos', $item->is_available ? 'item_available' : 'item_86', $item, $item->name);
        return back()->with('success', $item->name.($item->is_available ? ' is available again.' : ' marked sold out.'));
    }

    public function destroy(MenuItem $item)
    {
        $item->update(['is_active' => false]);
        $item->delete();
        return back()->with('success', $item->name.' removed from the menu (history kept).');
    }

    public function storeGroup(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'min_select' => ['required', 'integer', 'min:0', 'max:10'], 'max_select' => ['required', 'integer', 'min:1', 'max:10', 'gte:min_select']]);
        ModifierGroup::create($data);
        return back()->with('success', 'Modifier group added.');
    }

    public function storeModifier(Request $request, ModifierGroup $group)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'price' => ['nullable', 'numeric', 'min:0']]);
        $group->modifiers()->create(['name' => $data['name'], 'price' => $data['price'] ?? 0]);
        return back()->with('success', 'Option added to '.$group->name.'.');
    }

    public function destroyModifier(Modifier $modifier)
    {
        $modifier->update(['is_active' => false]);
        return back()->with('success', 'Option removed.');
    }

    private function category(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'], 'station' => ['required', Rule::in(['kitchen', 'bar', 'pastry'])],
            'outlet_id' => ['nullable', 'exists:outlets,id'], 'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'sort_order' => ['nullable', 'integer'],
        ]) + ['sort_order' => (int) $request->input('sort_order', 0)];
    }

    private function item(Request $request, ?MenuItem $item = null): array
    {
        $data = $request->validate([
            'menu_category_id' => ['required', 'exists:menu_categories,id'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('menu_items', 'code')->ignore($item?->id)],
            'name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'], 'cost' => ['nullable', 'numeric', 'min:0'],
            'station' => ['nullable', Rule::in(['kitchen', 'bar', 'pastry'])], 'allergens' => ['nullable', 'string', 'max:200'],
            'prep_minutes' => ['nullable', 'integer', 'min:0', 'max:180'], 'sort_order' => ['nullable', 'integer'],
            'image' => ['nullable', 'file', 'max:'.config('vaasal.security.upload_max_kb')],
            'groups' => ['nullable', 'array'], 'groups.*' => ['exists:modifier_groups,id'],
        ]);
        unset($data['image'], $data['groups']);
        if ($request->hasFile('image')) $data['image_path'] = UploadService::image($request->file('image'), 'menu');
        return $data + ['is_active' => $request->boolean('is_active'), 'is_available' => $request->boolean('is_available'), 'cost' => $data['cost'] ?? 0, 'sort_order' => $data['sort_order'] ?? 0];
    }
}
