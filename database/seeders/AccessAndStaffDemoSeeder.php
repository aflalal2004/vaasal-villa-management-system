<?php

namespace Database\Seeders;

use App\Models\AttendanceDevice;
use App\Models\Department;
use App\Models\Employee;
use App\Models\KeyCard;
use App\Models\KeyCardAssignment;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\Villa;
use App\Modules\Auth\Support\DemoLogin;
use App\Modules\KeyCards\Services\KeyCardService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for the access-control and staff features. Idempotent: safe to run on an existing database
 * (php artisan db:seed --class=AccessAndStaffDemoSeeder) as well as on a fresh install.
 *
 *  - General Staff login (username "staff") with an employee profile;
 *  - employee RFID badges for time clocks and staff doors;
 *  - RFID readers: villa door, Villa Area gate, main gate, staff-entrance clock (simulator mode, local token);
 *  - a housekeeping key card (UID 04AABBCC1122) and an expired card, for the simulator.
 * Local-only secrets: the simulator reader token is "rfid-demo-token" (see README → Demo accounts).
 */
class AccessAndStaffDemoSeeder extends Seeder
{
    public function run(): void
    {
        $p = Property::query()->first();
        if (! $p) return;

        // ---------------- General staff account ----------------
        $role = Role::where('slug', 'staff')->first();
        $staff = User::withTrashed()->where('email', 'staff@vaasalvilla.test')->first()
            ?? User::create(['property_id' => $p->id, 'user_type' => 'staff', 'name' => 'Selvi Arumugam', 'email' => 'staff@vaasalvilla.test', 'username' => 'staff',
                'password' => DemoLogin::PASSWORD, 'status' => 'active', 'password_changed_at' => now()]);
        if ($role) $staff->roles()->syncWithoutDetaching([$role->id]);
        if (! $staff->employee && ($dept = Department::where('code', 'ADM')->first())) {
            Employee::create(['property_id' => $p->id, 'employee_no' => 'EMP-'.str_pad((string) (Employee::count() + 1), 4, '0', STR_PAD_LEFT).'S', 'user_id' => $staff->id,
                'department_id' => $dept->id, 'first_name' => 'Selvi', 'last_name' => 'Arumugam', 'email' => $staff->email, 'hire_date' => now()->subMonths(8)->startOfMonth(),
                'attendance_pin' => Hash::make('1234'), 'qr_token' => bin2hex(random_bytes(12))]);
        }

        // ---------------- Employee RFID badges ----------------
        $badges = ['housekeeping@vaasalvilla.test' => '04B1C2D3E401', 'housekeeping2@vaasalvilla.test' => '04B1C2D3E402', 'maintenance@vaasalvilla.test' => '04B1C2D3E403',
            'kitchen@vaasalvilla.test' => '04B1C2D3E404', 'reception@vaasalvilla.test' => '04B1C2D3E405', 'staff@vaasalvilla.test' => '04B1C2D3E406'];
        foreach ($badges as $email => $uid) {
            $e = Employee::whereHas('user', fn ($q) => $q->where('email', $email))->first();
            if ($e && ! $e->rfid_uid && ! Employee::where('rfid_uid', $uid)->exists()) $e->update(['rfid_uid' => $uid]);
        }

        // ---------------- RFID readers ----------------
        $g1 = Villa::where('code', 'G1')->first();
        $devices = [
            ['Villa G1 door', 'rfid', 'access', $g1?->id, null, 'Villa G1 entrance'],
            ['Villa Area gate', 'rfid', 'access', null, 'Villa Area', 'Service path to the villas'],
            ['Main gate', 'rfid', 'access', null, 'Main Gate', 'Property entrance'],
            ['Staff entrance clock', 'rfid', 'both', null, 'Staff Entrance', 'Back-of-house entrance'],
        ];
        foreach ($devices as [$name, $type, $purpose, $villaId, $zone, $location]) {
            AttendanceDevice::firstOrCreate(['name' => $name], ['type' => $type, 'purpose' => $purpose, 'villa_id' => $villaId, 'zone' => $zone, 'location' => $location,
                'mode' => 'simulator', 'api_token_hash' => hash('sha256', $name === 'Staff entrance clock' ? 'rfid-demo-token' : bin2hex(random_bytes(20)))]);
        }

        // ---------------- Cards for the simulator ----------------
        $cards = app(KeyCardService::class);
        $hk = Employee::whereHas('user', fn ($q) => $q->where('email', 'housekeeping@vaasalvilla.test'))->first();
        if ($hk && ! KeyCard::where('uid', '04AABBCC1122')->exists()) {
            $cards->issueForEmployee($hk, '04AABBCC1122', 'housekeeping', null, now()->addYear()->endOfDay());
        }
        if (! KeyCard::where('uid', '04EE00000001')->exists()) {
            $expired = $cards->register('04EE00000001', null, 'guest', 'Demo: expired guest card');
            KeyCardAssignment::create(['key_card_id' => $expired->id, 'villa_id' => $g1?->id, 'access_level' => 'guest', 'lock_refs' => [$g1?->lock_ref ?: 'G1'],
                'valid_from' => now()->subDays(4), 'valid_to' => now()->subDay(), 'status' => 'expired', 'revoked_at' => now()->subDay(), 'revoke_reason' => 'Validity ended']);
        }
    }
}
