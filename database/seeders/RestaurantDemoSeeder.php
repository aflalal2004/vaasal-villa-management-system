<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\TableReservation;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Restaurant POS demo data. Idempotent — safe on an existing database:
 *   php artisan db:seed --class=RestaurantDemoSeeder
 *  - stand-in dish photos (Unsplash CDN) only for menu items without an uploaded photo; the dashboard and terminal
 *    fall back to an icon tile if a photo cannot load;
 *  - table reservations for today and tomorrow (one linked to an in-house hotel guest), only if none exist yet.
 */
class RestaurantDemoSeeder extends Seeder
{
    private const PHOTOS = [
        'BK1' => 'photo-1533089860892-a7c6f0a88666', 'BK2' => 'photo-1525351484163-7529414344d8', 'BK3' => 'photo-1490474418585-ba9bad8fd0ea',
        'ST1' => 'photo-1565680018434-b513d5e5fd47', 'ST2' => 'photo-1512621776951-a57141f2eefd', 'ST3' => 'photo-1547592166-23ac45744acd',
        'MN1' => 'photo-1585937421612-70a008356fbe', 'MN2' => 'photo-1565557623262-b51c2513a641', 'MN3' => 'photo-1559847844-5315695dadae',
        'MN4' => 'photo-1519708227418-c8fd9a32b7a2', 'MN5' => 'photo-1603133872878-684f208fb84b', 'MN6' => 'photo-1563379926898-05f4575a45d8',
        'DS1' => 'photo-1551024601-bec78aea704b', 'DS2' => 'photo-1488477181946-6428a0291777',
        'CF1' => 'photo-1544787219-7f47ccb76574', 'CF2' => 'photo-1509042239860-f550ce710b93', 'CF3' => 'photo-1461023058943-07fcbe16d735',
        'DR1' => 'photo-1580984969071-a8da5656c2fb', 'DR2' => 'photo-1513558161293-cdaf765ed2fd', 'DR3' => 'photo-1608270586620-248524c67de9',
        'DR4' => 'photo-1523362628745-0c100150b504',
        'CK1' => 'photo-1514362545857-3bc16c4c7d1b', 'CK2' => 'photo-1551538827-9c037cb4f32a', 'CK3' => 'photo-1470337458703-46ad1756a187',
    ];

    public function run(): void
    {
        foreach (self::PHOTOS as $code => $photo) {
            MenuItem::where('code', $code)->whereNull('image_path')
                ->update(['image_path' => 'https://images.unsplash.com/'.$photo.'?auto=format&fit=crop&w=600&q=70']);
        }

        $rest = Outlet::where('code', 'REST')->with('tables')->first();
        if (! $rest || $rest->tables->isEmpty() || TableReservation::whereDate('reserved_for', today())->exists()) {
            return;
        }
        $tables = $rest->tables->values();
        $staff = User::where('email', 'posmanager@vaasalvilla.test')->value('id');
        $inHouse = Booking::with('guest')->where('status', 'checked_in')->first();
        $rows = [
            ['Ms. Silva', '077 123 4567', 2, today()->setTime(19, 30), 'confirmed', 0, null, 'Window seat, anniversary'],
            ['Mr. Wickrama', '071 555 0199', 4, today()->setTime(20, 0), 'confirmed', 1, null, null],
            ['Mrs. Abeysekera', '076 441 2200', 6, today()->setTime(20, 30), 'pending', 2, null, 'Birthday cake to be served at dessert'],
            [$inHouse?->guest->fullName() ?? 'Mr. Jayawardena', null, 3, today()->setTime(21, 0), 'confirmed', 3, $inHouse?->id, 'In-house guest — charge to villa'],
            ['Mr. Thevarajah', '077 900 1122', 2, today()->addDay()->setTime(13, 0), 'confirmed', 4, null, 'Jaffna crab curry pre-order'],
            ['Dr. Kumaran', '075 310 8844', 5, today()->addDay()->setTime(19, 45), 'pending', 5, null, null],
        ];
        foreach ($rows as [$name, $phone, $party, $at, $status, $t, $booking, $notes]) {
            $table = $tables[$t % $tables->count()];
            TableReservation::create(['outlet_id' => $rest->id, 'pos_table_id' => $table->id, 'booking_id' => $booking, 'guest_name' => $name, 'phone' => $phone,
                'party_size' => min($party, $table->seats + 2), 'reserved_for' => $at, 'status' => $status, 'notes' => $notes, 'created_by' => $staff]);
        }
    }
}
