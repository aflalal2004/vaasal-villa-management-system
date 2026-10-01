<?php

namespace App\Modules\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\LeaveType;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Core\Services\UploadService;
use App\Modules\Staff\Http\Requests\EmployeeRequest;
use App\Modules\Staff\Services\AttendanceService;
use App\Modules\Staff\Services\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $employees = Employee::with(['department', 'jobRole', 'user.roles'])
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->query('department')))
            ->when($request->query('status', 'active') !== 'all', fn ($q) => $q->where('status', $request->query('status', 'active')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('first_name', 'like', '%'.$request->query('q').'%')->orWhere('last_name', 'like', '%'.$request->query('q').'%')->orWhere('employee_no', 'like', '%'.$request->query('q').'%')))
            ->orderBy('first_name')->get();
        $today = AttendanceRecord::where('work_date', now()->toDateString())->get()->keyBy('employee_id');
        return view('admin.staff.employees', ['employees' => $employees, 'departments' => Department::orderBy('name')->pluck('name', 'id'), 'today' => $today]);
    }

    public function show(Employee $employee, AttendanceService $attendance, LeaveService $leave)
    {
        $employee->load(['department', 'jobRole', 'user.roles']);
        $records = AttendanceRecord::with('shift')->where('employee_id', $employee->id)->where('work_date', '>=', now()->subDays(30))->orderByDesc('work_date')->get();
        return view('admin.staff.employee-show', [
            'e' => $employee, 'records' => $records, 'summary' => $attendance->summary($records),
            'leave' => $employee->leaveRequests()->with('type')->latest()->limit(10)->get(),
            'balances' => LeaveType::all()->mapWithKeys(fn ($t) => [$t->name => $t->days_per_year ? $leave->balance($employee, $t, (int) now()->format('Y')) : null]),
            'roles' => Role::where('slug', '!=', 'tour_operator')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create()
    {
        return view('admin.staff.employee-form', ['e' => new Employee(['hire_date' => now(), 'employment_type' => 'full_time']), 'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'jobRoles' => JobRole::with('department')->get()->mapWithKeys(fn ($j) => [$j->id => $j->department->name.' — '.$j->title])]);
    }

    public function store(EmployeeRequest $request)
    {
        $data = $this->prepare($request);
        $e = Employee::create($data + ['property_id' => Property::current()->id, 'employee_no' => DocumentNumberService::next('employee'), 'qr_token' => Str::random(24)]);
        AuditService::log('staff', 'employee_created', $e, $e->fullName());
        return redirect()->route('admin.staff.employees.show', $e)->with('success', 'Employee '.$e->employee_no.' created.');
    }

    public function edit(Employee $employee)
    {
        return view('admin.staff.employee-form', ['e' => $employee, 'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'jobRoles' => JobRole::with('department')->get()->mapWithKeys(fn ($j) => [$j->id => $j->department->name.' — '.$j->title])]);
    }

    public function update(EmployeeRequest $request, Employee $employee)
    {
        $employee->fill($this->prepare($request));
        AuditService::logChanges('staff', $employee, 'employee_updated');
        $employee->save();
        return redirect()->route('admin.staff.employees.show', $employee)->with('success', 'Employee saved.');
    }

    /** Give an employee a system login (staff realm) with one or more roles. */
    public function createLogin(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'.($employee->user_id ? ','.$employee->user_id : '')],
            'password' => [$employee->user_id ? 'nullable' : 'required', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'], 'roles.*' => ['exists:roles,id'],
        ]);
        if (Role::whereIn('id', $data['roles'])->where('slug', 'admin')->exists() && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Only administrators can grant the Administrator role.');
        }
        DB::transaction(function () use ($employee, $data) {
            $user = $employee->user ?? new User(['property_id' => $employee->property_id, 'user_type' => 'staff']);
            $user->fill(['name' => $employee->fullName(), 'email' => strtolower($data['email']), 'status' => 'active']);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
                $user->password_changed_at = now();
            }
            $user->save();
            $user->roles()->sync($data['roles']);
            $employee->update(['user_id' => $user->id, 'email' => $employee->email ?? $user->email]);
            AuditService::log('staff', 'login_granted', $user, 'Roles: '.Role::whereIn('id', $data['roles'])->pluck('name')->implode(', '));
        });
        return back()->with('success', 'Login saved for '.$employee->fullName().'.');
    }

    public function terminate(Request $request, Employee $employee)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        DB::transaction(function () use ($employee, $data) {
            $employee->update(['status' => 'terminated', 'termination_date' => now()]);
            if ($employee->user) {
                $employee->user->update(['status' => 'inactive']);
                DB::table('sessions')->where('user_id', $employee->user_id)->delete();
            }
            // Revoke all staff key cards immediately
            \App\Models\KeyCardAssignment::where('employee_id', $employee->id)->where('status', 'active')->get()
                ->each(fn ($a) => app(\App\Modules\KeyCards\Services\KeyCardService::class)->revoke($a, 'Employment ended'));
            AuditService::log('staff', 'terminated', $employee, $data['reason']);
        });
        return back()->with('success', $employee->fullName().' offboarded: login disabled, sessions ended and key cards revoked.');
    }

    public function departments()
    {
        return view('admin.staff.departments', ['departments' => Department::withCount('employees')->with('jobRoles.defaultRole')->orderBy('name')->get(),
            'roles' => Role::where('slug', '!=', 'tour_operator')->pluck('name', 'id')]);
    }

    public function storeDepartment(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'alpha_dash', 'max:20', 'unique:departments,code'], 'name' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:255']]);
        Department::create($data + ['property_id' => Property::current()->id]);
        return back()->with('success', 'Department added.');
    }

    public function storeJobRole(Request $request)
    {
        $data = $request->validate(['department_id' => ['required', 'exists:departments,id'], 'title' => ['required', 'string', 'max:100'], 'default_role_id' => ['nullable', 'exists:roles,id']]);
        JobRole::firstOrCreate(['department_id' => $data['department_id'], 'title' => $data['title']], $data);
        return back()->with('success', 'Job role added.');
    }

    private function prepare(EmployeeRequest $request): array
    {
        $data = $request->validated();
        unset($data['photo']);
        if (! empty($data['attendance_pin'])) {
            $data['attendance_pin'] = Hash::make($data['attendance_pin']);
        } else {
            unset($data['attendance_pin']);
        }
        if (empty($data['national_id'])) unset($data['national_id']);
        if ($request->hasFile('photo')) {
            $data['photo_path'] = UploadService::image($request->file('photo'), 'staff');
        }
        return $data;
    }
}
