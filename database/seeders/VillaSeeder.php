<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\ChannelMapping;
use App\Models\Facility;
use App\Models\Media;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Rate;
use App\Models\RatePlan;
use App\Models\Season;
use App\Models\Villa;
use App\Models\VillaType;
use Illuminate\Database\Seeder;

class VillaSeeder extends Seeder
{
    public const IMG = 'https://images.unsplash.com/';

    public static function img(string $id, int $w = 1600): string
    {
        return self::IMG.$id.'?auto=format&fit=crop&w='.$w.'&q=70';
    }

    public function run(): void
    {
        $p = Property::first();

        $facilities = [];
        foreach ([
            ['Private pool', 'waves', 'outdoor'], ['Ocean view', 'globe', 'outdoor'], ['Garden terrace', 'leaf', 'outdoor'], ['Air conditioning', 'snow', 'comfort'],
            ['Free Wi-Fi', 'wifi', 'comfort'], ['Smart TV', 'tv', 'comfort'], ['Kitchenette', 'kitchen', 'comfort'], ['Espresso machine', 'coffee', 'comfort'],
            ['Outdoor rain shower', 'sparkles', 'bath'], ['Bathtub', 'sparkles', 'bath'], ['Daily housekeeping', 'broom', 'service'], ['Airport pickup (on request)', 'car', 'service'],
            ['In-villa dining', 'utensils', 'service'], ['Safe', 'lock', 'comfort'],
        ] as [$name, $icon, $cat]) {
            $facilities[$name] = Facility::create(['name' => $name, 'icon' => $icon, 'category' => $cat])->id;
        }

        $types = [
            [
                'name' => 'Garden Villa', 'max_adults' => 2, 'max_children' => 1, 'bedrooms' => 1, 'bathrooms' => 1, 'size_sqm' => 65, 'base_rate' => 38000,
                'extra_adult_rate' => 0, 'extra_child_rate' => 4000, 'bed_configuration' => '1 king bed',
                'short' => 'A quiet one-bedroom villa opening onto a walled tropical garden and plunge pool.',
                'facilities' => ['Garden terrace', 'Air conditioning', 'Free Wi-Fi', 'Espresso machine', 'Outdoor rain shower', 'Daily housekeeping', 'Safe', 'In-villa dining'],
                'images' => ['photo-1590490360182-c33d57733427', 'photo-1540541338287-41700207dee6', 'photo-1596394516093-501ba68a0ba6'],
            ],
            [
                'name' => 'Pool Villa', 'max_adults' => 2, 'max_children' => 2, 'bedrooms' => 1, 'bathrooms' => 1, 'size_sqm' => 90, 'base_rate' => 55000,
                'extra_adult_rate' => 0, 'extra_child_rate' => 5000, 'bed_configuration' => '1 king bed + daybed',
                'short' => 'Our signature villa: a 9-metre private pool, sun deck and open-air living pavilion.',
                'facilities' => ['Private pool', 'Air conditioning', 'Free Wi-Fi', 'Smart TV', 'Espresso machine', 'Outdoor rain shower', 'Bathtub', 'Daily housekeeping', 'Safe', 'In-villa dining'],
                'images' => ['photo-1582719508461-905c673771fd', 'photo-1631049307264-da0ec9d70304', 'photo-1584132967334-10e028bd69f7'],
            ],
            [
                'name' => 'Ocean Pool Villa', 'max_adults' => 2, 'max_children' => 1, 'bedrooms' => 1, 'bathrooms' => 2, 'size_sqm' => 120, 'base_rate' => 72000,
                'extra_adult_rate' => 0, 'extra_child_rate' => 6000, 'bed_configuration' => '1 super-king bed',
                'short' => 'Clifftop villa with an infinity pool that meets the Indian Ocean horizon.',
                'facilities' => ['Private pool', 'Ocean view', 'Air conditioning', 'Free Wi-Fi', 'Smart TV', 'Espresso machine', 'Bathtub', 'Outdoor rain shower', 'Daily housekeeping', 'Safe', 'Airport pickup (on request)', 'In-villa dining'],
                'images' => ['photo-1571896349842-33c89424de2d', 'photo-1618773928121-c32242e63f39', 'photo-1507525428034-b723cf961d3e'],
            ],
            [
                'name' => 'Family Villa', 'max_adults' => 4, 'max_children' => 2, 'bedrooms' => 2, 'bathrooms' => 2, 'size_sqm' => 160, 'base_rate' => 85000,
                'extra_adult_rate' => 7500, 'extra_child_rate' => 5000, 'bed_configuration' => '1 king + 2 twin beds', 'base_occupancy' => 4,
                'short' => 'Two bedrooms around a shared pool courtyard, with a kitchenette and space for everyone.',
                'facilities' => ['Private pool', 'Garden terrace', 'Air conditioning', 'Free Wi-Fi', 'Smart TV', 'Kitchenette', 'Espresso machine', 'Bathtub', 'Daily housekeeping', 'Safe', 'In-villa dining'],
                'images' => ['photo-1602002418082-a4443e081dd1', 'photo-1493809842364-78817add7ffb', 'photo-1566073771259-6a8506099945'],
            ],
        ];

        $typeIds = [];
        foreach ($types as $i => $t) {
            $type = VillaType::create([
                'property_id' => $p->id, 'name' => $t['name'], 'slug' => \Str::slug($t['name']), 'short_description' => $t['short'],
                'description' => $t['short']."\n\nEvery villa has its own entrance, air-conditioned bedroom, an outdoor shower, and a terrace for breakfast. Housekeeping visits twice daily; in-villa dining is available from the restaurant until 10pm.",
                'max_adults' => $t['max_adults'], 'max_children' => $t['max_children'], 'bedrooms' => $t['bedrooms'], 'bathrooms' => $t['bathrooms'],
                'size_sqm' => $t['size_sqm'], 'bed_configuration' => $t['bed_configuration'], 'base_rate' => $t['base_rate'], 'extra_adult_rate' => $t['extra_adult_rate'],
                'extra_child_rate' => $t['extra_child_rate'], 'base_occupancy' => $t['base_occupancy'] ?? 2, 'sort_order' => $i,
            ]);
            $type->facilities()->sync(collect($t['facilities'])->map(fn ($f) => $facilities[$f])->all());
            foreach ($t['images'] as $j => $img) {
                Media::create(['mediable_type' => VillaType::class, 'mediable_id' => $type->id, 'type' => 'image', 'path' => self::img($img),
                    'alt' => $t['name'].' photo '.($j + 1), 'is_cover' => $j === 0, 'sort_order' => $j]);
            }
            $typeIds[$t['name']] = $type->id;
        }

        $villas = [
            ['G1', 'Frangipani', 'Garden Villa', 'Garden'], ['G2', 'Jasmine', 'Garden Villa', 'Garden'],
            ['P1', 'Lotus', 'Pool Villa', 'Pool Court'], ['P2', 'Cinnamon', 'Pool Villa', 'Pool Court'], ['P3', 'Tamarind', 'Pool Villa', 'Pool Court'],
            ['O1', 'Moonstone', 'Ocean Pool Villa', 'Ocean Front'], ['O2', 'Sapphire', 'Ocean Pool Villa', 'Ocean Front'],
            ['F1', 'Banyan', 'Family Villa', 'Garden'],
        ];
        foreach ($villas as $i => [$code, $name, $type, $zone]) {
            Villa::create([
                'property_id' => $p->id, 'villa_type_id' => $typeIds[$type], 'code' => $code, 'name' => $name.' '.$type,
                'slug' => \Str::slug($name.'-'.$code), 'zone' => $zone, 'lock_ref' => 'LOCK-'.(100 + $i + 1),
                'description' => "{$name} is a {$type} in the {$zone} area.", 'hk_status' => 'ready', 'sort_order' => $i,
            ]);
        }

        $plans = [
            ['RO', 'Flexible — room only', 'room_only', true, 7, 100, 30, 0, 'Free cancellation up to 7 days before arrival. 30% deposit to confirm.'],
            ['BB', 'Flexible — bed & breakfast', 'bb', true, 7, 100, 30, 12, 'Daily breakfast for all guests. Free cancellation up to 7 days before arrival.'],
            ['NR', 'Non-refundable saver', 'bb', false, 0, 100, 100, -8, 'Best price with breakfast. Paid in full at booking; no refunds.'],
        ];
        foreach ($plans as [$code, $name, $meal, $ref, $days, $pen, $dep, $adj, $desc]) {
            RatePlan::create(['property_id' => $p->id, 'code' => $code, 'name' => $name, 'meal_plan' => $meal, 'is_refundable' => $ref, 'free_cancel_days' => $days,
                'cancel_penalty_pct' => $pen, 'deposit_pct' => $dep, 'price_adjust_pct' => $adj, 'description' => $desc]);
        }

        $y = (int) now()->format('Y');
        $seasons = [];
        foreach ([$y - 1, $y, $y + 1] as $year) {
            $seasons[] = ['Peak '.$year.'/'.($year + 1), "$year-12-15", ($year + 1).'-01-15', '#9B3A33', 3, 1.35, 3];
            $seasons[] = ['High '.($year + 1), ($year + 1).'-01-16', ($year + 1).'-04-15', '#B07A1E', 2, 1.2, 2];
            $seasons[] = ['Low '.($year + 1), ($year + 1).'-05-01', ($year + 1).'-09-30', '#1F5F9E', 1, 0.8, 1];
        }
        foreach ($seasons as [$name, $start, $end, $color, $prio, $mult, $min]) {
            $s = Season::create(['property_id' => $p->id, 'name' => $name, 'start_date' => $start, 'end_date' => $end, 'color' => $color, 'priority' => $prio]);
            foreach (VillaType::all() as $t) {
                Rate::create(['villa_type_id' => $t->id, 'season_id' => $s->id, 'amount' => round($t->base_rate * $mult / 500) * 500, 'min_stay' => $min]);
            }
        }

        Offer::create(['property_id' => $p->id, 'title' => 'Stay 4, save 15%', 'slug' => 'stay-4-save-15', 'summary' => 'Stay four nights or more and save 15% on your villa.',
            'description' => 'Book four or more nights in any villa and the discount applies automatically with code STAY4.', 'image' => self::img('photo-1520250497591-112f2f40a3f4'),
            'discount_type' => 'percent', 'discount_value' => 15, 'promo_code' => 'STAY4', 'min_nights' => 4, 'valid_from' => now()->subMonth(), 'valid_to' => now()->addMonths(10), 'is_featured' => true]);
        Offer::create(['property_id' => $p->id, 'title' => 'Early bird — 10% off', 'slug' => 'early-bird', 'summary' => 'Plan ahead and save 10% when you book 60 days in advance.',
            'description' => 'Use code EARLY10 at checkout.', 'image' => self::img('photo-1571003123894-1f0594d2b5d9'),
            'discount_type' => 'percent', 'discount_value' => 10, 'promo_code' => 'EARLY10', 'min_nights' => 2, 'valid_from' => now()->subMonth(), 'valid_to' => now()->addYear(), 'is_featured' => true]);
        Offer::create(['property_id' => $p->id, 'title' => 'Honeymoon welcome', 'slug' => 'honeymoon', 'summary' => 'LKR 15,000 off plus a sunset cocktail set-up on your terrace.',
            'description' => 'For stays of three nights or more. Code HONEY.', 'image' => self::img('photo-1540555700478-4be289fbecef'),
            'discount_type' => 'fixed', 'discount_value' => 15000, 'promo_code' => 'HONEY', 'min_nights' => 3, 'valid_from' => now()->subMonth(), 'valid_to' => now()->addYear()]);

        foreach ([
            ['website', 'Website (direct)', 'direct', 0], ['admin', 'Admin / manual', 'direct', 0], ['walk_in', 'Walk-in', 'direct', 0],
            ['phone', 'Phone', 'direct', 0], ['email', 'Email', 'direct', 0], ['tour_operator', 'Tour operator', 'operator', 0],
            ['booking_com', 'Booking.com', 'ota', 15], ['agoda', 'Agoda', 'ota', 15], ['airbnb', 'Airbnb', 'ota', 3], ['expedia', 'Expedia', 'ota', 18],
        ] as [$code, $name, $type, $comm]) {
            Channel::create(['code' => $code, 'name' => $name, 'type' => $type, 'commission_pct' => $comm]);
        }
        foreach (Channel::where('type', 'ota')->get() as $ch) {
            foreach (VillaType::all() as $t) {
                ChannelMapping::create(['channel_id' => $ch->id, 'villa_type_id' => $t->id, 'external_room_code' => 'CHX-'.strtoupper(\Str::slug($t->name, '_')),
                    'external_rate_code' => 'CHX-BAR']);
            }
        }
    }
}
