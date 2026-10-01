<?php

namespace App\Modules\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\RosterEntry;
use App\Models\Shift;
use App\Modules\Core\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RosterController extends Controller
{
    public function index(Request $request)
    {
        $start = Carbon::parse($request->query('week', now()->startOfWeek()->toDateString()))->startOfWeek();
        $days = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i));
        $employees = Employee::with('department')->where('status', 'active')
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->query('department')))
            ->orderBy('department_id')->orderBy('first_name')->get();
        $entries = RosterEntry::whereBetween('work_date', [$start->toDateString(), $start->copy()->addDays(6)->toDateString()])->get()
            ->groupBy('employee_id')->map(fn ($g) => $g->keyBy(fn ($e) => $e->work_date->toDateString()));
        $leave = LeaveRequest::where('status', 'approved')->where('start_date', '<=', $start->copy()->addDays(6))->where('end_date', '>=', $start)->get()->groupBy('employee_id');
        return view('admin.staff.roster', ['start' => $start, 'days' => $days, 'employees' => $employees, 'entries' => $entries, 'leave' => $leave,
            'shifts' => Shift::orderBy('start_time')->get(), 'departments' => Department::orderBy('name')->pluck('name', 'id')]);
    }

    public function save(Request $request)
    {
        $data = $request->validate(['roster' => ['array'], 'roster.*.*' => ['nullable', 'exists:shifts,id']]);
        $n = 0;
        foreach ($data['roster'] ?? [] as $employeeId => $days) {
            foreach ($days as $date => $shiftId) {
                if ($shiftId) {
                    RosterEntry::updateOrCreate(['employee_id' => $employeeId, 'work_date' => $date], ['shift_id' => $shiftId]);
                } else {
                    RosterEntry::where('employee_id', $employeeId)->where('work_date', $date)->delete();
                }
                $n++;
            }
        }
        AuditService::log('staff', 'roster_saved', null, $n.' roster cells saved');
        return back()->with('success', 'Roster saved.');
    }

    public function storeShift(Request $request)
    {
        Shift::create($this->shift($request));
        return back()->with('success', 'Shift added.');
    }

    public function updateShift(Request $request, Shift $shift)
    {
        $shift->update($this->shift($request));
        return back()->with('success', 'Shift updated.');
    }

    public function devices()
    {
        return view('admin.staff.devices', ['devices' => AttendanceDevice::all()]);
    }

    /** Register an RFID / QR / biometric / kiosk terminal and show its API token once. */
    public function storeDevice(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'type' => ['required', Rule::in(['kiosk', 'rfid', 'qr', 'biometric'])], 'location' => ['nullable', 'string', 'max:100']]);
        $token = Str::random(40);
        AttendanceDevice::create($data + ['api_token_hash' => hash('sha256', $token)]);
        return back()->with('success', 'Device registered.')->with('device_token', $token);
    }

    private function shift(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:120'], 'break_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
    }
}
