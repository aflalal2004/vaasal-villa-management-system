<?php

namespace App\Modules\Staff\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use App\Modules\Notifications\Services\NotificationService;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

class LeaveService
{
    public function request(Employee $employee, LeaveType $type, string $start, string $end, ?string $reason = null, bool $halfDay = false): LeaveRequest
    {
        $s = Carbon::parse($start);
        $e = Carbon::parse($end);
        if ($e->lt($s)) {
            throw new BusinessRuleException('End date must be on or after the start date.');
        }
        $days = $halfDay ? 0.5 : (float) (CarbonPeriod::create($s, $e)->count());
        $overlap = LeaveRequest::where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', $e->toDateString())->where('end_date', '>=', $s->toDateString())->exists();
        if ($overlap) {
            throw new BusinessRuleException('This request overlaps an existing leave request.');
        }
        if ($type->days_per_year > 0 && $days > $this->balance($employee, $type, (int) $s->format('Y'))) {
            throw new BusinessRuleException("Not enough {$type->name} balance for {$days} day(s).");
        }

        $req = LeaveRequest::create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => $s, 'end_date' => $e, 'days' => $days, 'reason' => $reason]);
        NotificationService::notify('leave.requested', 'Leave request: '.$employee->fullName(), $type->name.' · '.fmt_date($s).' – '.fmt_date($e)." ({$days} d)",
            route('admin.staff.leave.index'), 'info', 'leave.approve');
        return $req;
    }

    public function decide(LeaveRequest $req, bool $approve, ?string $remarks = null): LeaveRequest
    {
        if ($req->status !== 'pending') {
            throw new BusinessRuleException('This request has already been decided.');
        }
        // balance() already counts this pending request, so it must not go below zero.
        $available = $this->balance($req->employee, $req->type, (int) $req->start_date->format('Y')) + (float) $req->days;
        if ($approve && $req->type->days_per_year > 0 && $req->days > $available) {
            throw new BusinessRuleException('Insufficient leave balance.');
        }
        $req->update(['status' => $approve ? 'approved' : 'rejected', 'approved_by' => auth()->id(), 'approved_at' => now(), 'remarks' => $remarks]);
        AuditService::log('staff', $approve ? 'leave_approved' : 'leave_rejected', $req, $req->employee->fullName());
        if ($req->employee->user_id) {
            NotificationService::notify('leave.decided', 'Your leave request was '.($approve ? 'approved' : 'rejected'), $remarks, route('admin.staff.my-timecard'),
                $approve ? 'success' : 'warning', null, $req->employee->user_id);
        }
        return $req;
    }

    public function balance(Employee $employee, LeaveType $type, int $year): float
    {
        $taken = (float) LeaveRequest::where('employee_id', $employee->id)->where('leave_type_id', $type->id)
            ->whereIn('status', ['approved', 'pending'])->whereYear('start_date', $year)->sum('days');
        return max(0, $type->days_per_year - $taken);
    }
}
