<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Outlet;
use App\Models\PosTable;
use App\Models\Property;
use App\Models\Recipe;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class PosSeeder extends Seeder
{
    public function run(): void
    {
        $p = Property::first();
        $rest = Outlet::create(['property_id' => $p->id, 'code' => 'REST', 'name' => 'Vaasal Kitchen', 'type' => 'restaurant', 'folio_department' => 'restaurant',
            'tax_pct' => 8, 'service_charge_pct' => 10, 'receipt_header' => "Vaasal Kitchen\nJaffna, Sri Lanka\nTel 0764413420", 'receipt_footer' => 'Thank you — ස්තූතියි — நன்றி']);
        $bar = Outlet::create(['property_id' => $p->id, 'code' => 'BAR', 'name' => 'Pool Bar', 'type' => 'bar', 'folio_department' => 'bar',
            'tax_pct' => 8, 'service_charge_pct' => 10, 'receipt_header' => 'Pool Bar · Vaasal Villa', 'receipt_footer' => 'Cheers!']);
        $ird = Outlet::create(['property_id' => $p->id, 'code' => 'IRD', 'name' => 'In-villa dining', 'type' => 'room_service', 'folio_department' => 'room_service',
            'tax_pct' => 8, 'service_charge_pct' => 10, 'receipt_header' => 'In-villa dining · Vaasal Villa']);

        foreach (['T1' => 'Terrace', 'T2' => 'Terrace', 'T3' => 'Terrace', 'T4' => 'Terrace', 'T5' => 'Dining room', 'T6' => 'Dining room', 'T7' => 'Dining room', 'T8' => 'Dining room', 'T9' => 'Garden', 'T10' => 'Garden'] as $i => $area) {
            PosTable::create(['outlet_id' => $rest->id, 'name' => $i, 'area' => $area, 'seats' => in_array($i, ['T5', 'T9']) ? 6 : 4, 'sort_order' => (int) substr($i, 1)]);
        }
        foreach (['B1', 'B2', 'B3', 'B4', 'Deck'] as $i => $n) {
            PosTable::create(['outlet_id' => $bar->id, 'name' => $n, 'area' => 'Pool', 'seats' => 2, 'sort_order' => $i]);
        }

        $spice = ModifierGroup::create(['name' => 'Spice level', 'min_select' => 1, 'max_select' => 1]);
        foreach (['Mild', 'Medium', 'Sri Lankan hot'] as $m) Modifier::create(['modifier_group_id' => $spice->id, 'name' => $m]);
        $addons = ModifierGroup::create(['name' => 'Add-ons', 'min_select' => 0, 'max_select' => 3]);
        foreach ([['Extra egg', 350], ['Extra prawns', 1800], ['Papadam', 250], ['Extra rice', 400]] as [$m, $pr]) Modifier::create(['modifier_group_id' => $addons->id, 'name' => $m, 'price' => $pr]);
        $eggs = ModifierGroup::create(['name' => 'Eggs', 'min_select' => 1, 'max_select' => 1]);
        foreach (['Fried', 'Scrambled', 'Poached', 'Omelette'] as $m) Modifier::create(['modifier_group_id' => $eggs->id, 'name' => $m]);
        $milk = ModifierGroup::create(['name' => 'Milk', 'min_select' => 0, 'max_select' => 1]);
        foreach ([['Regular', 0], ['Oat milk', 300], ['Coconut milk', 250]] as [$m, $pr]) Modifier::create(['modifier_group_id' => $milk->id, 'name' => $m, 'price' => $pr]);

        $cats = [
            ['Breakfast', 'kitchen', '#B07A1E', [
                ['BK1', 'Sri Lankan hopper breakfast', 3200, 900, [$spice], 'Egg hoppers, pol sambol, seeni sambol, dhal curry'],
                ['BK2', 'Garden breakfast', 3500, 1100, [$eggs], 'Eggs your way, sourdough, grilled tomato, avocado'],
                ['BK3', 'Tropical fruit platter', 2200, 700, [], 'Papaya, pineapple, mango, passionfruit'],
            ]],
            ['Starters', 'kitchen', '#0E6B63', [
                ['ST1', 'Devilled prawns', 3800, 1500, [$spice], null], ['ST2', 'Mango & cashew salad', 2600, 800, [], null], ['ST3', 'Crab soup', 2900, 1100, [], null],
            ]],
            ['Mains', 'kitchen', '#1F5F9E', [
                ['MN1', 'Rice & curry (chicken)', 4200, 1400, [$spice, $addons], 'Seven curries, rice, papadam'],
                ['MN2', 'Rice & curry (vegetarian)', 3600, 1000, [$spice, $addons], null],
                ['MN3', 'Jaffna crab curry', 7800, 3400, [$spice], null],
                ['MN4', 'Grilled catch of the day', 6500, 2600, [], 'Seer fish, lime butter, garden greens'],
                ['MN5', 'Kottu roti (chicken)', 3400, 1100, [$spice, $addons], null],
                ['MN6', 'Seafood linguine', 5600, 2200, [], null],
            ]],
            ['Desserts', 'kitchen', '#9B3A33', [
                ['DS1', 'Watalappan', 1800, 450, [], 'Coconut custard with jaggery and cardamom'], ['DS2', 'Buffalo curd & kithul treacle', 1600, 400, [], null],
            ]],
            ['Coffee & tea', 'bar', '#6D4C2F', [
                ['CF1', 'Ceylon tea (pot)', 900, 150, [$milk], null], ['CF2', 'Flat white', 1200, 300, [$milk], null], ['CF3', 'Iced coffee', 1400, 350, [$milk], null],
            ]],
            ['Drinks', 'bar', '#1F7A8C', [
                ['DR1', 'King coconut', 800, 150, [], null], ['DR2', 'Fresh lime soda', 900, 150, [], null], ['DR3', 'Lion lager', 1500, 600, [], null], ['DR4', 'Sparkling water', 900, 300, [], null],
            ]],
            ['Cocktails', 'bar', '#5B4B8A', [
                ['CK1', 'Arrack sour', 2800, 700, [], 'Ceylon arrack, lime, egg white'], ['CK2', 'Passion fruit mojito', 2600, 650, [], null], ['CK3', 'Pol Toddy spritz', 2400, 600, [], null],
            ]],
        ];
        $items = [];
        foreach ($cats as $i => [$cname, $station, $color, $list]) {
            $cat = MenuCategory::create(['name' => $cname, 'station' => $station, 'color' => $color, 'sort_order' => $i]);
            foreach ($list as $j => [$code, $name, $price, $cost, $groups, $desc]) {
                $item = MenuItem::create(['menu_category_id' => $cat->id, 'code' => $code, 'name' => $name, 'description' => $desc, 'price' => $price, 'cost' => $cost,
                    'sort_order' => $j, 'prep_minutes' => $station === 'bar' ? 5 : 15]);
                $item->modifierGroups()->sync(collect($groups)->pluck('id')->all());
                $items[$code] = $item;
            }
        }

        $s1 = Supplier::create(['name' => 'Jaffna Fish Market Co-op', 'contact_name' => 'Mr. Pradeep', 'phone' => '+94 21 222 3344', 'email' => 'orders@jaffnafish.test']);
        $s2 = Supplier::create(['name' => 'Northern Fresh Produce', 'contact_name' => 'Ms. Kumari', 'phone' => '+94 21 555 1212']);
        $s3 = Supplier::create(['name' => 'Ceylon Beverages Distributors', 'contact_name' => 'Accounts desk', 'phone' => '+94 11 234 5678', 'email' => 'sales@ceylonbev.test']);

        $stock = [
            ['FSH-PRW', 'Prawns (medium)', 'Seafood', 'kg', 22, 4, 4200, $s1], ['FSH-CRB', 'Lagoon crab', 'Seafood', 'kg', 30, 4, 5200, $s1], ['FSH-SEER', 'Seer fish fillet', 'Seafood', 'kg', 8, 3, 3800, $s1],
            ['MT-CHK', 'Chicken (boneless)', 'Meat', 'kg', 30, 5, 1600, $s2], ['DRY-RICE', 'Samba rice', 'Dry store', 'kg', 40, 15, 320, $s2], ['DRY-FLOUR', 'Wheat flour', 'Dry store', 'kg', 20, 8, 240, $s2],
            ['DAI-EGG', 'Eggs', 'Dairy', 'pcs', 420, 60, 45, $s2], ['DAI-CURD', 'Buffalo curd (clay pot)', 'Dairy', 'pcs', 40, 6, 450, $s2], ['VEG-COC', 'Coconut', 'Produce', 'pcs', 50, 20, 110, $s2],
            ['VEG-LIME', 'Limes', 'Produce', 'kg', 4, 2, 600, $s2], ['FRT-MIX', 'Tropical fruit (mixed)', 'Produce', 'kg', 15, 6, 480, $s2], ['BEV-LION', 'Lion lager 625ml', 'Beverages', 'btl', 48, 24, 520, $s3],
            ['BEV-ARR', 'Ceylon arrack 750ml', 'Beverages', 'btl', 8, 4, 3200, $s3], ['BEV-KC', 'King coconut', 'Beverages', 'pcs', 30, 15, 120, $s2], ['BEV-TEA', 'Ceylon BOP tea', 'Beverages', 'kg', 2, 1, 2600, $s3],
            ['BEV-COF', 'Coffee beans', 'Beverages', 'kg', 3, 1, 5800, $s3],
        ];
        $st = [];
        foreach ($stock as [$sku, $name, $cat, $unit, $qty, $reorder, $cost, $sup]) {
            $item = StockItem::create(['sku' => $sku, 'name' => $name, 'category' => $cat, 'unit' => $unit, 'current_qty' => $qty, 'reorder_level' => $reorder, 'unit_cost' => $cost, 'supplier_id' => $sup->id]);
            StockMovement::create(['stock_item_id' => $item->id, 'type' => 'in', 'quantity' => $qty, 'unit_cost' => $cost, 'balance_after' => $qty, 'supplier_id' => $sup->id, 'reference' => 'Opening stock']);
            $st[$sku] = $item;
        }
        foreach ([
            ['ST1', 'FSH-PRW', 0.2], ['MN3', 'FSH-CRB', 0.6], ['MN4', 'FSH-SEER', 0.25], ['MN1', 'MT-CHK', 0.18], ['MN1', 'DRY-RICE', 0.2], ['MN2', 'DRY-RICE', 0.2],
            ['MN5', 'MT-CHK', 0.15], ['MN5', 'DRY-FLOUR', 0.15], ['BK1', 'DAI-EGG', 2], ['BK1', 'DRY-FLOUR', 0.1], ['BK2', 'DAI-EGG', 2], ['BK3', 'FRT-MIX', 0.35],
            ['DS2', 'DAI-CURD', 0.5], ['DR1', 'BEV-KC', 1], ['DR3', 'BEV-LION', 1], ['CK1', 'BEV-ARR', 0.06], ['DR2', 'VEG-LIME', 0.05], ['CF1', 'BEV-TEA', 0.01], ['CF2', 'BEV-COF', 0.018],
        ] as [$menu, $sku, $q]) {
            Recipe::create(['menu_item_id' => $items[$menu]->id, 'stock_item_id' => $st[$sku]->id, 'quantity' => $q]);
        }
    }
}
