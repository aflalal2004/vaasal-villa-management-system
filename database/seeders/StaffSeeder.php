<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Property;
use App\Models\Role;
use App\Models\RosterEntry;
use App\Models\Shift;
use App\Models\User;
use App\Modules\Staff\Services\AttendanceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $p = Property::first();
        $d = Department::pluck('id', 'code');
        $r = Role::pluck('id', 'slug');

        $jobs = [
            ['ADM', 'General Manager', 'manager'], ['ADM', 'System Administrator', 'admin'], ['ADM', 'Accountant', 'accountant'], ['ADM', 'Owner', 'owner'],
            ['FO', 'Front Office Executive', 'receptionist'], ['FB', 'Restaurant Manager', 'pos_manager'], ['FB', 'Cashier', 'cashier'], ['FB', 'Steward / Waiter', 'waiter'],
            ['KIT', 'Chef de Cuisine', 'kitchen'], ['KIT', 'Commis Chef', 'kitchen'], ['STR', 'Storekeeper', 'inventory'], ['HK', 'Executive Housekeeper', 'hk_supervisor'],
            ['HK', 'Room Attendant', 'housekeeper'], ['MNT', 'Maintenance Technician', 'maintenance'], ['MNT', 'Pool & Garden Attendant', null],
        ];
        $jobIds = [];
        foreach ($jobs as [$dep, $title, $role]) {
            $jobIds[$title] = JobRole::create(['department_id' => $d[$dep], 'title' => $title, 'default_role_id' => $role ? $r[$role] : null])->id;
        }

        $shifts = [
            'Morning' => Shift::create(['name' => 'Morning', 'start_time' => '06:00', 'end_time' => '14:00', 'grace_minutes' => 10, 'break_minutes' => 45, 'color' => '#B07A1E']),
            'Day' => Shift::create(['name' => 'Day', 'start_time' => '09:00', 'end_time' => '17:30', 'grace_minutes' => 10, 'break_minutes' => 60, 'color' => '#0E6B63']),
            'Evening' => Shift::create(['name' => 'Evening', 'start_time' => '14:00', 'end_time' => '22:00', 'grace_minutes' => 10, 'break_minutes' => 45, 'color' => '#1F5F9E']),
            'Night' => Shift::create(['name' => 'Night', 'start_time' => '22:00', 'end_time' => '06:00', 'grace_minutes' => 10, 'break_minutes' => 30, 'color' => '#5B4B8A']),
        ];

        $map = [
            'admin@vaasalvilla.test' => ['ADM', 'System Administrator', 'Day'], 'owner@vaasalvilla.test' => ['ADM', 'Owner', null],
            'manager@vaasalvilla.test' => ['ADM', 'General Manager', 'Day'], 'reception@vaasalvilla.test' => ['FO', 'Front Office Executive', 'Morning'],
            'accounts@vaasalvilla.test' => ['ADM', 'Accountant', 'Day'], 'posmanager@vaasalvilla.test' => ['FB', 'Restaurant Manager', 'Evening'],
            'cashier@vaasalvilla.test' => ['FB', 'Cashier', 'Evening'], 'waiter@vaasalvilla.test' => ['FB', 'Steward / Waiter', 'Evening'],
            'kitchen@vaasalvilla.test' => ['KIT', 'Chef de Cuisine', 'Evening'], 'stores@vaasalvilla.test' => ['STR', 'Storekeeper', 'Day'],
            'hksupervisor@vaasalvilla.test' => ['HK', 'Executive Housekeeper', 'Morning'], 'housekeeping@vaasalvilla.test' => ['HK', 'Room Attendant', 'Morning'],
            'housekeeping2@vaasalvilla.test' => ['HK', 'Room Attendant', 'Morning'], 'maintenance@vaasalvilla.test' => ['MNT', 'Maintenance Technician', 'Day'],
        ];
        $n = 1;
        $employees = [];
        foreach ($map as $email => [$dep, $job, $shift]) {
            $u = User::where('email', $email)->first();
            [$first, $last] = array_pad(explode(' ', str_replace('Chef ', '', $u->name), 2), 2, '');
            $e = Employee::create([
                'property_id' => $p->id, 'employee_no' => sprintf('EMP-%04d', $n++), 'user_id' => $u->id, 'department_id' => $d[$dep], 'job_role_id' => $jobIds[$job],
                'first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => '+94 77 '.rand(100, 999).' '.rand(1000, 9999),
                'hire_date' => now()->subMonths(rand(4, 60))->startOfMonth(), 'employment_type' => 'full_time', 'attendance_pin' => Hash::make('1234'),
                'qr_token' => bin2hex(random_bytes(12)), 'emergency_contact_name' => 'Family contact', 'emergency_contact_phone' => '+94 71 000 0000',
            ]);
            $employees[] = [$e, $shift];
        }
        foreach ([['Kamal', 'Senanayake', 'MNT', 'Pool & Garden Attendant', 'Morning'], ['Anjali', 'Thevarajah', 'KIT', 'Commis Chef', 'Morning']] as [$f, $l, $dep, $job, $shift]) {
            $e = Employee::create(['property_id' => $p->id, 'employee_no' => sprintf('EMP-%04d', $n++), 'department_id' => $d[$dep], 'job_role_id' => $jobIds[$job],
                'first_name' => $f, 'last_name' => $l, 'hire_date' => now()->subYear(), 'attendance_pin' => Hash::make('1234'), 'qr_token' => bin2hex(random_bytes(12))]);
            $employees[] = [$e, $shift];
        }
        // Continue the EMP numbering sequence
        \App\Models\DocumentSequence::create(['type' => 'employee', 'prefix' => 'EMP', 'year' => (int) now()->format('Y'), 'next_number' => $n]);

        foreach ([['AL', 'Annual leave', 14, true], ['CL', 'Casual leave', 7, true], ['SL', 'Sick leave', 7, true], ['UL', 'Unpaid leave', 0, false]] as [$c, $name, $days, $paid]) {
            LeaveType::create(['code' => $c, 'name' => $name, 'days_per_year' => $days, 'is_paid' => $paid]);
        }

        // Roster: last 7 days and next 7 days; attendance history for the past 6 days
        $svc = app(AttendanceService::class);
        foreach ($employees as [$e, $shiftName]) {
            if (! $shiftName) continue;
            $shift = $shifts[$shiftName];
            for ($i = -6; $i <= 7; $i++) {
                $date = now()->addDays($i)->startOfDay();
                if ($date->isSunday() && $e->department->code !== 'FB' && $e->department->code !== 'HK') continue;
                RosterEntry::create(['employee_id' => $e->id, 'shift_id' => $shift->id, 'work_date' => $date]);
                if ($i < 0) {
                    if (rand(1, 12) === 1) continue; // occasional absence (marked by night audit)
                    $start = Carbon::parse($date->toDateString().' '.$shift->start_time);
                    $end = Carbon::parse($date->toDateString().' '.$shift->end_time);
                    if ($end->lte($start)) $end->addDay();
                    $in = $start->copy()->addMinutes(rand(-12, 22));
                    $out = $end->copy()->addMinutes(rand(-20, 75));
                    $rec = new AttendanceRecord(['employee_id' => $e->id, 'work_date' => $date, 'shift_id' => $shift->id, 'clock_in' => $in, 'clock_out' => $out,
                        'source_in' => 'kiosk', 'source_out' => 'kiosk']);
                    $svc->calculate($rec);
                    $rec->save();
                }
            }
        }
        $svc->markAbsences(now()->subDay());

        // Today: staff whose shift has started are clocked in (on duty now).
        foreach (RosterEntry::with(['shift', 'employee'])->where('work_date', now()->toDateString())->get() as $entry) {
            $start = Carbon::parse(now()->toDateString().' '.$entry->shift->start_time);
            if ($start->isFuture() || $entry->shift->name === 'Night') continue;
            $svc->clock($entry->employee, 'kiosk', null, $start->copy()->addMinutes(rand(-10, 18)));
            $end = Carbon::parse(now()->toDateString().' '.$entry->shift->end_time);
            if ($end->gt($start) && $end->isPast()) {
                $svc->clock($entry->employee, 'kiosk', null, $end->copy()->addMinutes(rand(-5, 40))->min(now()));
            }
        }

        $emp = Employee::where('email', 'housekeeping2@vaasalvilla.test')->first();
        LeaveRequest::create(['employee_id' => $emp->id, 'leave_type_id' => LeaveType::where('code', 'AL')->value('id'), 'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(12), 'days' => 3, 'reason' => 'Family wedding in Jaffna']);
    }
}
