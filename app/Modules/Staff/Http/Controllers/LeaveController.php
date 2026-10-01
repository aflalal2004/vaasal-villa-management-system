<?php

namespace App\Modules\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Staff\Services\LeaveService;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function __construct(private LeaveService $leave) {}

    public function index(Request $request)
    {
        $canApprove = $request->user()->hasPermission('leave.approve');
        $status = $request->query('status', $canApprove ? 'pending' : 'all');
        $q = LeaveRequest::with(['employee.department', 'type', 'approver'])
            ->when(! $canApprove, fn ($w) => $w->where('employee_id', $request->user()->employee?->id ?? 0))
            ->when($status !== 'all', fn ($w) => $w->where('status', $status))->latest();
        return view('admin.staff.leave', [
            'requests' => $q->paginate(25)->withQueryString(), 'status' => $status, 'canApprove' => $canApprove,
            'types' => LeaveType::all(),
            'employees' => $canApprove ? Employee::where('status', 'active')->orderBy('first_name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->fullName()]) : collect(),
            'onLeaveToday' => LeaveRequest::with('employee')->where('status', 'approved')->where('start_date', '<=', now()->toDateString())->where('end_date', '>=', now()->toDateString())->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'], 'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date', 'after_or_equal:'.now()->subDays(30)->toDateString()], 'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day' => ['nullable', 'boolean'], 'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $employee = ! empty($data['employee_id']) && $request->user()->hasPermission('leave.approve')
            ? Employee::findOrFail($data['employee_id'])
            : ($request->user()->employee ?? throw new BusinessRuleException('Your login is not linked to an employee profile.'));
        $this->leave->request($employee, LeaveType::findOrFail($data['leave_type_id']), $data['start_date'], $data['end_date'], $data['reason'] ?? null, $request->boolean('half_day'));
        return back()->with('success', 'Leave request submitted.');
    }

    public function decide(Request $request, LeaveRequest $leave)
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'remarks' => ['nullable', 'string', 'max:300']]);
        if ($leave->employee->user_id === $request->user()->id && ! $request->user()->isSuperAdmin()) {
            throw new BusinessRuleException('You cannot approve your own leave.');
        }
        $this->leave->decide($leave, $data['decision'] === 'approve', $data['remarks'] ?? null);
        return back()->with('success', 'Leave '.($data['decision'] === 'approve' ? 'approved' : 'rejected').'.');
    }

    public function cancel(Request $request, LeaveRequest $leave)
    {
        $own = $leave->employee->user_id === $request->user()->id;
        abort_unless($own || $request->user()->hasPermission('leave.approve'), 403);
        if (! in_array($leave->status, ['pending', 'approved'], true) || ($leave->status === 'approved' && $leave->start_date->isPast() && ! $request->user()->hasPermission('leave.approve'))) {
            throw new BusinessRuleException('This request can no longer be cancelled.');
        }
        $leave->update(['status' => 'cancelled']);
        return back()->with('success', 'Leave request cancelled.');
    }

    public function storeType(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'alpha_dash', 'max:10', 'unique:leave_types,code'], 'name' => ['required', 'string', 'max:60'], 'days_per_year' => ['required', 'integer', 'min:0', 'max:60']]);
        LeaveType::create($data + ['is_paid' => $request->boolean('is_paid', true)]);
        return back()->with('success', 'Leave type added.');
    }
}
