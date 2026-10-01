<?php

namespace App\Modules\POS;

use App\Models\User;
use App\Modules\Core\Navigation as CoreNavigation;

/**
 * Restaurant POS sidebar. Restaurant operations only; the single "Hotel PMS" link appears for users who also
 * hold back-office permissions (administrators). Same item format and permission filtering as the admin console.
 */
class Navigation
{
    public static function groups(): array
    {
        return [
            'Restaurant' => [
                ['Dashboard', 'pos.dashboard', 'dashboard', 'pos.access', 'pos.dashboard'],
                ['POS / New order', 'pos.terminal', 'utensils', 'pos.order|pos.bill', 'pos.terminal|pos.order'],
                ['Reservations', 'pos.reservations.index', 'calendar', 'pos.reservations', 'pos.reservations.*'],
                ['Orders', 'pos.orders.index', 'receipt', 'pos.bill|pos.reports', 'pos.orders.*'],
                ['Kitchen', 'pos.kds', 'fire', 'pos.kds', 'pos.kds'],
            ],
            'Cashier' => [
                ['Today', 'pos.today', 'wallet', 'pos.shift|pos.reports|pos.shift_review', 'pos.today'],
                ['Shifts & drawer', 'pos.shifts.index', 'drawer', 'pos.shift|pos.reports|pos.shift_review', 'pos.shifts.*'],
                ['Cash movements', 'pos.cash-movements', 'coins', 'pos.shift|pos.reports|pos.shift_review', 'pos.cash-movements'],
                ['Day-end closing', 'pos.day-end', 'lock', 'pos.shift|pos.shift_review', 'pos.day-end'],
            ],
            'Menu & floor' => [
                ['Food menu', 'pos.menu.index', 'layers', 'pos.menu', 'pos.menu.*'],
                ['Tables & outlets', 'pos.outlets.index', 'table', 'pos.menu', 'pos.outlets.*'],
                ['Social media', 'pos.social.index', 'share', 'pos.menu', 'pos.social.*'],
            ],
            'Inventory' => [
                ['Stock', 'pos.inventory.items.index', 'box', 'inventory.view', 'pos.inventory.items.*'],
                ['Stock movements', 'pos.inventory.movements', 'refresh', 'inventory.view', 'pos.inventory.movements'],
                ['Purchases', 'pos.inventory.movements', 'download', 'inventory.view', '__purchases', ['type' => 'in']],
                ['Low stock', 'pos.inventory.items.index', 'alert', 'inventory.view', '__low', ['filter' => 'low']],
                ['Suppliers', 'pos.inventory.suppliers.index', 'car', 'inventory.manage', 'pos.inventory.suppliers.*'],
                ['Recipes', 'pos.inventory.recipes', 'leaf', 'inventory.manage', 'pos.inventory.recipes'],
            ],
            'Sales' => [
                ['Restaurant sales', 'pos.sales', 'chart', 'pos.reports', '__sales', ['type' => 'pos_sales']],
                ['Dish sales mix', 'pos.sales', 'trending', 'pos.reports', '__mix', ['type' => 'pos_items']],
            ],
            'My account' => [
                ['My time card', 'admin.staff.my-timecard', 'clock', 'attendance.self', 'admin.staff.my-timecard'],
                ['Hotel PMS', 'admin.dashboard', 'villa', 'dashboard.view', '__pms'],
            ],
        ];
    }

    public static function for(User $user): array
    {
        return CoreNavigation::build(self::groups(), $user);
    }
}
