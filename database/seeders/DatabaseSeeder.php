<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CoreSeeder::class,        // property, departments, RBAC, users, settings
            VillaSeeder::class,       // facilities, villa types, villas, rate plans, seasons, offers, channels
            OperationsSeeder::class,  // service charges, checklists, linen, key cards, lock bridge
            StaffSeeder::class,       // job roles, employees, shifts, rosters, attendance, leave
            PosSeeder::class,         // outlets, tables, menu, modifiers, suppliers, stock, recipes
            WebsiteSeeder::class,     // services, gallery, testimonials
            OperatorSeeder::class,    // tour operators, contracts, portal logins
            AccessAndStaffDemoSeeder::class, // general staff login, RFID badges, readers, simulator cards
        ]);

        // Operating history through the real services (skip with SEED_DEMO=false)
        if (filter_var(env('SEED_DEMO', true), FILTER_VALIDATE_BOOL)) {
            $this->call(DemoSeeder::class);
        }

        $this->call(RestaurantDemoSeeder::class); // dish photos + table reservations (idempotent)
    }
}
