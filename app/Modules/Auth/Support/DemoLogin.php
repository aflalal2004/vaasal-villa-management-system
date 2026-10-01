<?php

namespace App\Modules\Auth\Support;

use App\Models\User;

/**
 * Role quick-fill for the sign-in page.
 *
 * Returns an empty list unless APP_ENV=local AND config('vaasal.demo_login') is true, so demo usernames and
 * the shared demo password are never rendered in staging or production. Only accounts that exist and are
 * active are offered, so a card can never fill credentials for an account that is not there.
 */
class DemoLogin
{
    /** The password given to every seeded demo account (see Database\Seeders\CoreSeeder::PASSWORD). */
    public const PASSWORD = 'Vaasal@2026';

    /** [key, label, username, icon, description] in the order shown on the sign-in page. */
    private const ROLES = [
        ['admin', 'Administrator', 'admin', 'shield', 'Full system access'],
        ['manager', 'Hotel Manager', 'manager', 'briefcase', 'Hotel PMS: bookings, billing, villas'],
        ['reception', 'Reception', 'reception', 'door', 'Bookings, check-in & check-out'],
        ['pos_manager', 'Restaurant Manager', 'posmanager', 'utensils', 'Restaurant POS: menu, stock, sales'],
        ['cashier', 'Cashier', 'cashier', 'cash', 'POS billing, payments & own shift'],
        ['waiter', 'Waiter', 'waiter', 'table', 'Tables & guest orders'],
        ['kitchen', 'Kitchen', 'kitchen', 'fire', 'Kitchen display (KOT)'],
        ['housekeeping', 'Housekeeping', 'housekeeping', 'broom', 'Cleaning tasks & room status'],
        ['hk_supervisor', 'HK Supervisor', 'hksupervisor', 'check', 'Inspect & approve villas'],
        ['staff', 'General Staff', 'staff', 'user', 'Own profile & time card'],
    ];

    public static function enabled(): bool
    {
        return app()->environment('local') && (bool) config('vaasal.demo_login');
    }

    /** @return array<int, array{key:string,label:string,username:string,icon:string,description:string}> */
    public static function roles(): array
    {
        if (! self::enabled()) {
            return [];
        }
        $active = User::whereIn('username', array_column(self::ROLES, 2))->where('status', 'active')->pluck('username')->all();
        return array_values(array_map(
            fn ($r) => ['key' => $r[0], 'label' => $r[1], 'username' => $r[2], 'icon' => $r[3], 'description' => $r[4]],
            array_filter(self::ROLES, fn ($r) => in_array($r[2], $active, true))
        ));
    }
}
