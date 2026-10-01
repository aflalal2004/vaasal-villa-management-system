<?php

namespace App\Modules\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\RosterEntry;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Staff\Services\AttendanceService;
use App\Modules\Staff\Services\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    /** Staff self-service time card: clock in/out, this month's hours, roster, leave. */
    public function mine(Request $request, LeaveService $leave)
    {
        $e = $request->user()->employee;
        if (! $e) {
            return view('admin.staff.my-timecard', ['e' => null]);
        }
        $month = Carbon::parse($request->query('month', now()->format('Y-m')).'-01');
        $records = AttendanceRecord::with('shift')->where('employee_id', $e->id)->whereBetween('work_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->orderByDesc('work_date')->get();
        return view('admin.staff.my-timecard', [
            'e' => $e, 'month' => $month, 'records' => $records, 'summary' => $this->attendance->summary($records),
            'open' => AttendanceRecord::where('employee_id', $e->id)->whereNotNull('clock_in')->whereNull('clock_out')->where('work_date', '>=', now()->subDay()->toDateString())->first(),
            'roster' => RosterEntry::with('shift')->where('employee_id', $e->id)->whereBetween('work_date', [now()->toDateString(), now()->addDays(7)->toDateString()])->orderBy('work_date')->get(),
            'leaveRequests' => $e->leaveRequests()->with('type')->latest()->limit(8)->get(),
            'leaveTypes' => LeaveType::all(),
            'balances' => LeaveType::all()->mapWithKeys(fn ($t) => [$t->id => $t->days_per_year ? $leave->balance($e, $t, (int) now()->format('Y')) : null]),
        ]);
    }

    public function clock(Request $request)
    {
        $e = $request->user()->employee ?? throw new BusinessRuleException('Your login is not linked to an employee profile.');
        $r = $this->attendance->clock($e, 'web', null, now(), $request->ip());
        $msg = $r->clock_out ? 'Clocked out at '.$r->clock_out->format('H:i').' — worked '.minutes_hm($r->worked_minutes).($r->overtime_minutes ? ', overtime '.minutes_hm($r->overtime_minutes) : '').'.'
            : 'Clocked in at '.$r->clock_in->format('H:i').($r->late_minutes ? ' ('.$r->late_minutes.' min late)' : '').'.';
        return back()->with('success', $msg);
    }

    public function index(Request $request)
    {
        $from = Carbon::parse($request->query('from', now()->subDays(6)->toDateString()));
        $to = Carbon::parse($request->query('to', now()->toDateString()));
        $records = AttendanceRecord::with(['employee.department', 'shift', 'corrector'])
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->when($request->filled('department'), fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $request->query('department'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('employee'), fn ($q) => $q->where('employee_id', $request->query('employee')))
            ->orderByDesc('work_date')->orderBy('employee_id')->paginate(40)->withQueryString();
        $all = AttendanceRecord::whereBetween('work_date', [$from->toDateString(), $to->toDateString()])->get();
        return view('admin.staff.attendance', [
            'records' => $records, 'from' => $from, 'to' => $to, 'summary' => $this->attendance->summary($all),
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'employees' => Employee::where('status', '!=', 'terminated')->orderBy('first_name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->fullName()]),
            'onDuty' => AttendanceRecord::with('employee')->whereNotNull('clock_in')->whereNull('clock_out')->where('work_date', '>=', now()->subDay()->toDateString())->get(),
        ]);
    }

    public function correct(Request $request, AttendanceRecord $record)
    {
        $data = $request->validate(['clock_in' => ['nullable', 'date'], 'clock_out' => ['nullable', 'date', 'after:clock_in'], 'reason' => ['required', 'string', 'max:300'],
            'status' => ['nullable', Rule::in(['present', 'late', 'absent', 'on_leave'])]]);
        $this->attendance->correct($record, $data['clock_in'] ?? null, $data['clock_out'] ?? null, $data['reason'], $data['status'] ?? null);
        return back()->with('success', 'Time card corrected; original punches kept in the audit trail.');
    }

    public function manual(Request $request)
    {
        $data = $request->validate(['employee_id' => ['required', 'exists:employees,id'], 'work_date' => ['required', 'date', 'before_or_equal:today'],
            'clock_in' => ['required', 'date_format:H:i'], 'clock_out' => ['nullable', 'date_format:H:i'], 'reason' => ['required', 'string', 'max:300']]);
        $record = AttendanceRecord::firstOrNew(['employee_id' => $data['employee_id'], 'work_date' => $data['work_date']]);
        if (! $record->exists) {
            $record->shift_id = RosterEntry::where('employee_id', $data['employee_id'])->where('work_date', $data['work_date'])->value('shift_id');
            $record->source_in = 'manual';
            $record->save();
        }
        $out = $data['clock_out'] ? Carbon::parse($data['work_date'].' '.$data['clock_out']) : null;
        $in = Carbon::parse($data['work_date'].' '.$data['clock_in']);
        if ($out && $out->lte($in)) $out->addDay();
        $this->attendance->correct($record, $in->toDateTimeString(), $out?->toDateTimeString(), 'Manual entry: '.$data['reason']);
        return back()->with('success', 'Manual time entry saved.');
    }

    public function export(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $rows = AttendanceRecord::with(['employee.department', 'shift'])->whereBetween('work_date', [$from, $to])->orderBy('employee_id')->orderBy('work_date')->get();
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Employee no', 'Employee', 'Department', 'Date', 'Shift', 'Clock in', 'Clock out', 'Worked (min)', 'Late (min)', 'Early leave (min)', 'Overtime (min)', 'Status', 'Source', 'Correction']);
        foreach ($rows as $r) {
            fputcsv($out, [$r->employee->employee_no, $r->employee->fullName(), $r->employee->department->name, $r->work_date->toDateString(), $r->shift?->name,
                $r->clock_in?->format('H:i'), $r->clock_out?->format('H:i'), $r->worked_minutes, $r->late_minutes, $r->early_leave_minutes, $r->overtime_minutes,
                $r->status, trim($r->source_in.'/'.$r->source_out, '/'), $r->correction_reason]);
        }
        rewind($out);
        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="timesheet-'.$from.'-to-'.$to.'.csv"']);
    }
}
