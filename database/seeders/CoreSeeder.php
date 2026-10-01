<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Property;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Property, departments, permissions, roles (RBAC matrix) and staff login accounts.
 * Every demo account uses the password Vaasal@2026 (local development only).
 */
class CoreSeeder extends Seeder
{
    public const PASSWORD = 'Vaasal@2026';

    public const PERMISSIONS = [
        'dashboard' => ['dashboard.view' => 'View management dashboard'],
        'bookings' => [
            'bookings.view' => 'View bookings & enquiries', 'bookings.manage' => 'Create / modify bookings', 'bookings.cancel' => 'Cancel bookings',
            'bookings.override_rate' => 'Override room rates', 'calendar.view' => 'View availability calendar', 'conflicts.manage' => 'Resolve OTA conflict queue',
        ],
        'guests' => ['guests.view' => 'View guests', 'guests.manage' => 'Edit guests, blacklist, ID documents'],
        'frontdesk' => ['frontdesk.checkin' => 'Check guests in', 'frontdesk.checkout' => 'Check guests out', 'frontdesk.night_audit' => 'Run night audit'],
        'billing' => [
            'folio.post' => 'Post charges to folios', 'folio.adjust' => 'Reverse / adjust folio lines', 'payments.receive' => 'Receive payments',
            'payments.refund' => 'Issue refunds', 'invoices.view' => 'View invoices & receipts',
        ],
        'villas' => ['villas.view' => 'View villas', 'villas.manage' => 'Manage villas, types & facilities', 'rates.manage' => 'Manage rates, seasons & offers'],
        'operators' => ['operators.view' => 'View tour operators', 'operators.manage' => 'Manage operators & contracts', 'operators.finance' => 'Operator invoices, payments & commission'],
        'channels' => ['channels.manage' => 'Channel manager & OTA mappings'],
        'keycards' => ['keycards.view' => 'View key cards & access logs', 'keycards.issue' => 'Issue / revoke guest cards', 'keycards.manage' => 'Manage card stock, staff & master cards'],
        'housekeeping' => [
            'housekeeping.view' => 'View housekeeping board', 'housekeeping.manage' => 'Assign tasks & override villa status',
            'housekeeping.inspect' => 'Approve / reject inspections', 'housekeeping.work' => 'Perform assigned cleaning tasks', 'lostfound.manage' => 'Lost & found',
        ],
        'maintenance' => ['maintenance.view' => 'View maintenance tickets', 'maintenance.manage' => 'Manage & resolve tickets', 'maintenance.report' => 'Report maintenance issues'],
        'staff' => [
            'staff.view' => 'View employees', 'staff.manage' => 'Manage employees, departments, shifts & rosters', 'attendance.self' => 'Own time card (clock in/out)',
            'attendance.manage' => 'View & correct attendance', 'leave.self' => 'Request leave', 'leave.approve' => 'Approve leave',
        ],
        'pos' => [
            'pos.access' => 'Access POS', 'pos.order' => 'Take orders', 'pos.bill' => 'Bill & take payments', 'pos.void' => 'Void fired items / checks',
            'pos.discount' => 'Apply discounts', 'pos.refund' => 'Refund POS payments', 'pos.shift' => 'Cashier shift & drawer', 'pos.menu' => 'Manage menu, tables & outlets',
            'pos.kds' => 'Kitchen display', 'pos.reports' => 'POS reports', 'pos.shift_review' => 'Review, approve & reopen closed cashier shifts', 'pos.reservations' => 'Table reservations',
        ],
        'inventory' => ['inventory.view' => 'View stock', 'inventory.manage' => 'Stock in/out, wastage, suppliers, recipes'],
        'reports' => ['reports.operational' => 'Operational reports', 'reports.financial' => 'Financial reports'],
        'admin' => [
            'website.manage' => 'Manage website content', 'users.manage' => 'Manage users & roles', 'settings.manage' => 'System settings', 'audit.view' => 'Audit & login logs',
        ],
        'operator_portal' => ['portal.access' => 'Tour operator portal'],
    ];

    /** role slug => [name, home route, permissions ('*' = all except portal)] */
    public const ROLES = [
        'admin' => ['Administrator', 'admin.dashboard', ['*']],
        'owner' => ['Owner', 'admin.dashboard', ['dashboard.view', 'bookings.view', 'calendar.view', 'guests.view', 'invoices.view', 'villas.view', 'operators.view',
            'keycards.view', 'housekeeping.view', 'maintenance.view', 'staff.view', 'inventory.view', 'reports.operational', 'reports.financial', 'audit.view', 'pos.reports']],
        'manager' => ['Hotel Manager', 'admin.dashboard', ['dashboard.view', 'bookings.view', 'bookings.manage', 'bookings.cancel', 'bookings.override_rate', 'calendar.view', 'conflicts.manage',
            'guests.view', 'guests.manage', 'frontdesk.checkin', 'frontdesk.checkout', 'frontdesk.night_audit', 'folio.post', 'folio.adjust', 'payments.receive', 'payments.refund',
            'invoices.view', 'villas.view', 'villas.manage', 'rates.manage', 'operators.view', 'operators.manage', 'channels.manage', 'keycards.view', 'keycards.issue', 'keycards.manage',
            'housekeeping.view', 'housekeeping.manage', 'housekeeping.inspect', 'lostfound.manage', 'maintenance.view', 'maintenance.manage', 'maintenance.report',
            'staff.view', 'staff.manage', 'attendance.self', 'attendance.manage', 'leave.self', 'leave.approve',
            'reports.operational', 'reports.financial', 'website.manage']], // hotel PMS only — no restaurant POS
        'receptionist' => ['Receptionist', 'admin.frontdesk.index', ['bookings.view', 'bookings.manage', 'calendar.view', 'guests.view', 'guests.manage', 'frontdesk.checkin',
            'frontdesk.checkout', 'folio.post', 'payments.receive', 'invoices.view', 'villas.view', 'operators.view', 'keycards.view', 'keycards.issue', 'housekeeping.view',
            'maintenance.view', 'maintenance.report', 'lostfound.manage', 'attendance.self', 'leave.self', 'reports.operational']],
        'accountant' => ['Accountant', 'admin.payments.index', ['dashboard.view', 'bookings.view', 'guests.view', 'folio.post', 'folio.adjust', 'payments.receive', 'payments.refund',
            'invoices.view', 'operators.view', 'operators.finance', 'inventory.view', 'reports.financial', 'reports.operational', 'pos.reports', 'attendance.self', 'leave.self']],
        'pos_manager' => ['Restaurant Manager', 'pos.dashboard', ['pos.access', 'pos.order', 'pos.bill', 'pos.void', 'pos.discount', 'pos.refund', 'pos.shift', 'pos.menu', 'pos.kds',
            'pos.reports', 'pos.shift_review', 'pos.reservations', 'inventory.view', 'inventory.manage', 'attendance.self', 'leave.self']], // restaurant POS only
        'cashier' => ['Cashier', 'pos.terminal', ['pos.access', 'pos.order', 'pos.bill', 'pos.shift', 'pos.reservations', 'attendance.self', 'leave.self']],
        'waiter' => ['Waiter', 'pos.terminal', ['pos.access', 'pos.order', 'pos.reservations', 'attendance.self', 'leave.self']],
        'kitchen' => ['Kitchen Staff', 'pos.kds', ['pos.access', 'pos.kds', 'inventory.view', 'attendance.self', 'leave.self']],
        'inventory' => ['Inventory Staff', 'pos.inventory.items.index', ['pos.access', 'inventory.view', 'inventory.manage', 'attendance.self', 'leave.self']],
        'hk_supervisor' => ['Housekeeping Supervisor', 'admin.housekeeping.index', ['housekeeping.view', 'housekeeping.manage', 'housekeeping.inspect', 'housekeeping.work',
            'lostfound.manage', 'maintenance.view', 'maintenance.report', 'villas.view', 'staff.view', 'attendance.self', 'leave.self', 'reports.operational']],
        'housekeeper' => ['Housekeeping Staff', 'admin.housekeeping.my', ['housekeeping.work', 'maintenance.report', 'lostfound.manage', 'attendance.self', 'leave.self']],
        'maintenance' => ['Maintenance Technician', 'admin.maintenance.index', ['maintenance.view', 'maintenance.manage', 'maintenance.report', 'villas.view', 'attendance.self', 'leave.self']],
        'staff' => ['General Staff', 'admin.staff.my-timecard', ['attendance.self', 'leave.self', 'maintenance.report']],
        'tour_operator' => ['Tour Operator', 'operator.dashboard', ['portal.access']],
    ];

    public function run(): void
    {
        $property = Property::create([
            'code' => config('vaasal.property_code', 'VV'),
            'name' => 'Vaasal Villa',
            'legal_name' => 'Vaasal Villa (Pvt) Ltd',
            'email' => 'aflalal2004@gmail.com',
            'phone' => '0764413420',
            'address' => 'Jaffna',
            'city' => 'Jaffna',
            'country' => 'Sri Lanka',
            'tax_id' => 'VAT-000000000',
            'timezone' => 'Asia/Colombo',
            'currency' => config('vaasal.currency', 'LKR'),
            'tax_pct' => 8.00,
            'service_charge_pct' => 10.00,
            'check_in_time' => '14:00',
            'check_out_time' => '11:00',
            'latitude' => config('vaasal.site.latitude'),
            'longitude' => config('vaasal.site.longitude'),
        ]);

        foreach ([['FO', 'Front Office'], ['HK', 'Housekeeping'], ['FB', 'Food & Beverage Service'], ['KIT', 'Kitchen'], ['MNT', 'Maintenance'],
            ['ADM', 'Administration & Finance'], ['STR', 'Stores']] as [$code, $name]) {
            Department::create(['property_id' => $property->id, 'code' => $code, 'name' => $name]);
        }

        foreach (self::PERMISSIONS as $module => $perms) {
            foreach ($perms as $slug => $name) {
                Permission::updateOrCreate(['slug' => $slug], ['module' => $module, 'name' => $name]);
            }
        }
        $all = Permission::where('module', '!=', 'operator_portal')->pluck('id', 'slug');
        foreach (self::ROLES as $slug => [$name, $home, $perms]) {
            $role = Role::updateOrCreate(['slug' => $slug], ['name' => $name, 'home_route' => $home, 'is_system' => true]);
            $ids = $perms === ['*'] ? $all->values() : Permission::whereIn('slug', $perms)->pluck('id');
            $role->permissions()->sync($ids);
        }

        $users = [
            ['Aisha Perera', 'admin@vaasalvilla.test', ['admin']],
            ['Rohan Fernando', 'owner@vaasalvilla.test', ['owner']],
            ['Nadia Silva', 'manager@vaasalvilla.test', ['manager']],
            ['Kavin Raj', 'reception@vaasalvilla.test', ['receptionist']],
            ['Tharushi de Mel', 'accounts@vaasalvilla.test', ['accountant']],
            ['Suresh Kumar', 'posmanager@vaasalvilla.test', ['pos_manager']],
            ['Dilani Jayawardena', 'cashier@vaasalvilla.test', ['cashier']],
            ['Nimal Bandara', 'waiter@vaasalvilla.test', ['waiter']],
            ['Chef Arun Pillai', 'kitchen@vaasalvilla.test', ['kitchen']],
            ['Lakmal Wijesinghe', 'stores@vaasalvilla.test', ['inventory']],
            ['Priya Nathan', 'hksupervisor@vaasalvilla.test', ['hk_supervisor']],
            ['Malini Ratnam', 'housekeeping@vaasalvilla.test', ['housekeeper']],
            ['Sanjeev Maharaj', 'housekeeping2@vaasalvilla.test', ['housekeeper']],
            ['Ruwan Dias', 'maintenance@vaasalvilla.test', ['maintenance']],
            ['Selvi Arumugam', 'staff@vaasalvilla.test', ['staff']],
        ];
        $roleIds = Role::pluck('id', 'slug');
        foreach ($users as [$name, $email, $roles]) {
            $u = User::create(['property_id' => $property->id, 'user_type' => 'staff', 'name' => $name, 'email' => $email, 'username' => strstr($email, '@', true),
                'password' => self::PASSWORD, 'status' => 'active', 'password_changed_at' => now()]);
            $u->roles()->sync(collect($roles)->map(fn ($r) => $roleIds[$r])->all());
        }

        $settings = [
            'site_tagline' => 'Private pool villas in Jaffna, Sri Lanka',
            'site_hero_title' => 'Slow mornings. Private pools. The warm northern light of Jaffna.',
            'site_hero_text' => 'Private villas set among palmyra palms in Jaffna, with a restaurant, spa and a team that knows your name by the second day.',
            'site_hero_video' => '',
            'site_about' => "Vaasal means “threshold” — the doorway where guests are welcomed. Our villas were built around that idea: every one opens onto its own garden, pool and horizon.\n\nWe are a small, family-run property. The kitchen cooks from the morning market, the spa uses local oils, and our drivers know every back road between Jaffna Fort, Nallur and Point Pedro.",
            'whatsapp_number' => config('vaasal.whatsapp.number'),
            'contact_email' => 'aflalal2004@gmail.com',
            'contact_phone' => '0764413420',
            'contact_address' => 'Jaffna, Sri Lanka',
            'stat_guests' => '4800',
            'stat_rating' => '4.9',
            'stat_years' => '9',
            'night_audit_last' => null,
        ];
        foreach ($settings as $k => $v) {
            Setting::put($k, $v);
        }
    }
}
