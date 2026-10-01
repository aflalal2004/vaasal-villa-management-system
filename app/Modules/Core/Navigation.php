<?php

namespace App\Modules\Core;

use App\Models\User;

/**
 * Admin console sidebar (Hotel PMS). Items are shown only when the user holds the permission,
 * mirroring (not replacing) the route-level `perm:` middleware.
 *
 * The "Restaurant" and "Inventory" groups link into the separate Restaurant POS and only appear for users who
 * hold POS / inventory permissions (administrators) — hotel-only staff never see them.
 *
 * Item: [label, route, icon, permission|null, active route pattern(s), query params (optional)]
 */
class Navigation
{
    public static function groups(): array
    {
        return [
            'Overview' => [
                ['Dashboard', 'admin.dashboard', 'dashboard', 'dashboard.view', 'admin.dashboard'],
                ['Notifications', 'admin.notifications.index', 'bell', null, 'admin.notifications.*'],
            ],
            'Villa management' => [
                ['Villas / Rooms', 'admin.villas.index', 'villa', 'villas.view', 'admin.villas.*'],
                ['Villa calendar', 'admin.calendar', 'calendar', 'calendar.view', 'admin.calendar*'],
                ['Bookings', 'admin.bookings.index', 'booking', 'bookings.view', 'admin.bookings.*'],
                ['Website requests', 'admin.enquiries.index', 'globe', 'bookings.view', 'admin.enquiries.*'],
                ['Tour operators', 'admin.operators.index', 'briefcase', 'operators.view', 'admin.operators.*'],
                ['Check-in', 'admin.frontdesk.index', 'door', 'frontdesk.checkin', 'admin.frontdesk.index|admin.frontdesk.checkin*', ['tab' => 'arrivals']],
                ['Check-out', 'admin.frontdesk.index', 'door-open', 'frontdesk.checkout', 'admin.frontdesk.index|admin.frontdesk.checkout*', ['tab' => 'departures']],
                ['Active guests', 'admin.frontdesk.index', 'users', 'frontdesk.checkin|frontdesk.checkout', 'admin.frontdesk.index', ['tab' => 'inhouse']],
                ['Key cards', 'admin.keycards.index', 'key', 'keycards.view', 'admin.keycards.index|admin.keycards.show'],
                ['Housekeeping', 'admin.housekeeping.index', 'broom', 'housekeeping.view', 'admin.housekeeping.index|admin.housekeeping.tasks.*|admin.housekeeping.linen|admin.housekeeping.checklists'],
                ['My cleaning tasks', 'admin.housekeeping.my', 'check', 'housekeeping.work', 'admin.housekeeping.my'],
                ['Guests', 'admin.guests.index', 'user', 'guests.view', 'admin.guests.*'],
                ['Maintenance', 'admin.maintenance.index', 'wrench', 'maintenance.view|maintenance.report', 'admin.maintenance.*'],
                ['Lost & found', 'admin.lost-found.index', 'box', 'lostfound.manage', 'admin.lost-found.*'],
            ],
            'Villa setup & channels' => [
                ['Villa types', 'admin.villa-types.index', 'bed', 'villas.manage', 'admin.villa-types.*'],
                ['Rates & seasons', 'admin.rates.index', 'percent', 'rates.manage', 'admin.rates.*'],
                ['Offers', 'admin.offers.index', 'star', 'rates.manage', 'admin.offers.*'],
                ['Channel manager', 'admin.channels.index', 'link', 'channels.manage', 'admin.channels.*'],
                ['Conflict queue', 'admin.conflicts.index', 'alert', 'conflicts.manage', 'admin.conflicts.*'],
            ],
            'Restaurant' => [
                ['POS / New order', 'pos.terminal', 'utensils', 'pos.order|pos.bill', 'pos.terminal'],
                ['POS dashboard', 'pos.dashboard', 'grid', 'pos.access', 'pos.dashboard'],
                ['Orders', 'pos.orders.index', 'receipt', 'pos.bill|pos.reports', 'pos.orders.*'],
                ['Tables', 'pos.outlets.index', 'table', 'pos.menu', 'pos.outlets.*'],
                ['Reservations', 'pos.reservations.index', 'calendar', 'pos.reservations', 'pos.reservations.*'],
                ['Food menu', 'pos.menu.index', 'layers', 'pos.menu', 'pos.menu.*'],
                ['Kitchen', 'pos.kds', 'fire', 'pos.kds', 'pos.kds'],
                ['Restaurant sales', 'pos.sales', 'chart', 'pos.reports', 'pos.sales'],
                ['Cashier today', 'pos.today', 'wallet', 'pos.shift|pos.reports|pos.shift_review', 'pos.today'],
            ],
            'Billing' => [
                ['Invoices', 'admin.invoices.index', 'file', 'invoices.view', 'admin.invoices.*'],
                ['Payments', 'admin.payments.index', 'card', 'payments.receive|invoices.view', 'admin.payments.*'],
                ['Guest charges', 'admin.charge-items.index', 'sparkles', 'folio.post', 'admin.charge-items.*'],
                ['Outstanding payments', 'admin.reports.show', 'alert', 'reports.financial', '__outstanding', ['type' => 'outstanding']],
            ],
            'Inventory' => [
                ['Inventory', 'pos.inventory.items.index', 'box', 'inventory.view', 'pos.inventory.items.*'],
                ['Stock movements', 'pos.inventory.movements', 'refresh', 'inventory.view', 'pos.inventory.movements'],
                ['Suppliers', 'pos.inventory.suppliers.index', 'car', 'inventory.manage', 'pos.inventory.suppliers.*'],
                ['Purchases', 'pos.inventory.movements', 'download', 'inventory.view', '__purchases', ['type' => 'in']],
                ['Low stock', 'pos.inventory.items.index', 'alert', 'inventory.view', '__low', ['filter' => 'low']],
            ],
            'Staff' => [
                ['Staff', 'admin.staff.employees.index', 'users', 'staff.view', 'admin.staff.employees.*'],
                ['Attendance', 'admin.staff.attendance.index', 'history', 'attendance.manage', 'admin.staff.attendance.*'],
                ['Rosters & shifts', 'admin.staff.roster.index', 'calendar', 'staff.manage', 'admin.staff.roster.*'],
                ['Leave', 'admin.staff.leave.index', 'leaf', 'leave.approve|leave.self', 'admin.staff.leave.*'],
                ['My time card', 'admin.staff.my-timecard', 'clock', 'attendance.self', 'admin.staff.my-timecard'],
            ],
            'Access control' => [
                ['RFID devices', 'admin.access.devices', 'device', 'keycards.manage', 'admin.access.devices'],
                ['RFID simulator', 'admin.access.simulator', 'rfid', 'keycards.manage', 'admin.access.simulator'],
                ['Access logs', 'admin.keycards.logs', 'history', 'keycards.view', 'admin.keycards.logs'],
            ],
            'Insights' => [
                ['Reports', 'admin.reports.index', 'chart', 'reports.operational|reports.financial', 'admin.reports.*'],
            ],
            'Administration' => [
                ['Website content', 'admin.cms.index', 'globe', 'website.manage', 'admin.cms.*'],
                ['Social media', 'admin.social.index', 'share', 'website.manage', 'admin.social.*'],
                ['Users & roles', 'admin.users.index', 'shield', 'users.manage', 'admin.users.*|admin.roles.*'],
                ['Settings', 'admin.settings.edit', 'settings', 'settings.manage', 'admin.settings.*'],
                ['Audit log', 'admin.audit.index', 'eye', 'audit.view', 'admin.audit.*'],
            ],
        ];
    }

    public static function admin(User $user): array
    {
        return self::build(self::groups(), $user);
    }

    /** Shared builder (also used by the POS sidebar). */
    public static function build(array $groups, User $user): array
    {
        if (! config('vaasal.locks.rfid_simulator') && isset($groups['Access control'])) {
            $groups['Access control'] = array_values(array_filter($groups['Access control'], fn ($i) => $i[1] !== 'admin.access.simulator'));
        }
        $out = [];
        foreach ($groups as $group => $items) {
            $visible = array_values(array_filter($items, fn ($i) => $i[3] === null || $user->hasPermission($i[3])));
            if ($visible) {
                $out[$group] = array_map(fn ($i) => ['label' => $i[0], 'route' => $i[1], 'icon' => $i[2], 'params' => $i[5] ?? [],
                    'url' => route($i[1], $i[5] ?? []), 'active' => self::isActive($i[1], $i[4], $i[5] ?? [])], $visible);
            }
        }
        return $out;
    }

    /**
     * Active when the current route matches one of the item's patterns; items that differ only by a parameter
     * (front-desk tab, report type, low-stock filter) must also match that parameter.
     */
    private static function isActive(string $route, string $patterns, array $params): bool
    {
        $request = request();
        $patterns = array_values(array_filter(explode('|', $patterns), fn ($p) => ! str_starts_with($p, '__')));
        if (! $params) {
            return $request->routeIs(...$patterns);
        }
        $defaults = ['tab' => 'arrivals'];
        $onRoute = $request->routeIs($route) && collect($params)->every(
            fn ($v, $k) => (string) ($request->route($k) ?? $request->query($k, $defaults[$k] ?? null)) === (string) $v);
        $subPages = array_values(array_filter($patterns, fn ($p) => $p !== $route));
        return $onRoute || ($subPages && $request->routeIs(...$subPages));
    }
}
