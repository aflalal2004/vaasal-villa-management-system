<?php

namespace Database\Seeders;

use App\Models\AttendanceDevice;
use App\Models\ChargeItem;
use App\Models\ChecklistTemplate;
use App\Models\KeyCard;
use App\Models\LinenItem;
use App\Models\LockBridge;
use App\Models\Property;
use Illuminate\Database\Seeder;

/** Service charge catalogue, housekeeping checklists, linen, key card stock, lock bridge, devices. */
class OperationsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['LAUN-WASH', 'Laundry — wash & fold (per bag)', 'laundry', 2500, true, false],
            ['LAUN-PRESS', 'Laundry — pressing (per item)', 'laundry', 400, true, false],
            ['SPA-60', 'Spa — Ayurvedic massage 60 min', 'spa', 12500, true, true],
            ['SPA-90', 'Spa — signature ritual 90 min', 'spa', 18500, true, true],
            ['TRF-CMB', 'Airport transfer — Colombo (BIA), one way', 'airport_transfer', 22000, true, false],
            ['TRF-LOCAL', 'Car & driver — half day', 'transport', 9000, true, false],
            ['TUK', 'Tuk-tuk to Jaffna Fort (return)', 'transport', 2500, false, false],
            ['EXC-DELFT', 'Excursion — Delft Island day trip (per person)', 'excursion', 16000, true, false],
            ['EXC-COOK', 'Excursion — Sri Lankan cooking class', 'excursion', 9500, true, false],
            ['MINI-WATER', 'Minibar — water / soft drink', 'minibar', 600, true, false],
            ['MINI-BEER', 'Minibar — local beer', 'minibar', 1200, true, false],
            ['LATE-CO', 'Late checkout (until 16:00)', 'room', 15000, true, false],
            ['EXTRA-BED', 'Extra bed (per night)', 'room', 7500, true, false],
            ['DMG', 'Damage / replacement', 'damage', 0, false, false],
            ['MISC', 'Miscellaneous', 'misc', 0, true, false],
        ] as [$code, $name, $dept, $price, $tax, $svc]) {
            ChargeItem::create(['code' => $code, 'name' => $name, 'department' => $dept, 'price' => $price, 'taxable' => $tax, 'service_chargeable' => $svc]);
        }

        ChecklistTemplate::create(['name' => 'Departure clean', 'task_type' => 'departure', 'items' => [
            'Strip beds and remove all linen & towels', 'Remove rubbish, check drawers & safe for left items', 'Clean & sanitise bathroom, shower, toilet',
            'Dust surfaces, furniture, lamps', 'Vacuum and mop all floors', 'Make beds with fresh linen', 'Replace towels, bathrobes & amenities',
            'Restock minibar & water; record consumption', 'Clean pool deck, skim pool, arrange loungers', 'Check AC, lights, TV, Wi-Fi, door lock battery', 'Final walk-through & air freshener',
        ]]);
        ChecklistTemplate::create(['name' => 'Stay-over service', 'task_type' => 'stayover', 'items' => [
            'Make bed / tidy linen', 'Replace used towels', 'Clean bathroom & restock amenities', 'Empty bins', 'Refill drinking water', 'Tidy terrace & pool area',
        ]]);
        ChecklistTemplate::create(['name' => 'Deep clean', 'task_type' => 'deep', 'items' => [
            'Move furniture & clean behind', 'Wash curtains & cushion covers', 'Descale shower heads & taps', 'Clean AC filters', 'Polish wood & brass', 'Pool tile scrub',
        ]]);
        ChecklistTemplate::create(['name' => 'Ad-hoc request', 'task_type' => 'adhoc', 'items' => ['Complete the guest request', 'Report back to front desk']]);

        foreach ([['Bath towel', 4, 60], ['Hand towel', 4, 60], ['Bath mat', 2, 30], ['Pool towel', 4, 50], ['King sheet set', 2, 24], ['Twin sheet set', 2, 12], ['Pillowcase', 6, 80], ['Bathrobe', 2, 24]] as [$n, $par, $stock]) {
            LinenItem::create(['name' => $n, 'par_per_villa' => $par, 'stock_qty' => $stock]);
        }

        $p = Property::first();
        for ($i = 1; $i <= 24; $i++) {
            KeyCard::create(['property_id' => $p->id, 'uid' => sprintf('04A1%06X', 0x1000 + $i * 37), 'card_number' => sprintf('G-%03d', $i), 'type' => 'guest']);
        }
        for ($i = 1; $i <= 4; $i++) {
            KeyCard::create(['property_id' => $p->id, 'uid' => sprintf('04B2%06X', 0x2000 + $i * 41), 'card_number' => sprintf('S-%03d', $i), 'type' => 'staff']);
        }
        KeyCard::create(['property_id' => $p->id, 'uid' => '04C3FF0001', 'card_number' => 'M-001', 'type' => 'master', 'notes' => 'General manager master card']);

        LockBridge::create(['name' => 'Front office Lock Bridge', 'token_hash' => hash('sha256', (string) config('vaasal.locks.bridge_token')), 'vendor' => config('vaasal.locks.driver')]);
        AttendanceDevice::create(['name' => 'Staff entrance kiosk', 'type' => 'kiosk', 'location' => 'Back-of-house entrance', 'api_token_hash' => hash('sha256', 'device-kiosk-token')]);
    }
}
