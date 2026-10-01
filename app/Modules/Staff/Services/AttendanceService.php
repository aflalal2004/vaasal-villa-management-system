<?php

namespace App\Modules\Staff\Services;

use App\Models\AttendanceDevice;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\RosterEntry;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use Illuminate\Support\Carbon;

/**
 * Time card. Punches can come from the web app, a kiosk, or (later) RFID / QR / biometric
 * terminals via the device API — all go through clock(), so calculation rules live in one place.
 *
 * Rules: late = clock-in after shift start + grace; early leave = clock-out before shift end;
 * worked = elapsed − break (break deducted when elapsed > 5h); overtime = worked beyond scheduled.
 * Without a rostered shift, 8h is the standard day.
 */
class AttendanceService
{
    public const STANDARD_MINUTES = 480;

    public function clock(Employee $employee, string $source = 'web', ?AttendanceDevice $device = null, ?Carbon $at = null, ?string $ip = null): AttendanceRecord
    {
        if ($employee->status === 'terminated') {
            throw new BusinessRuleException('This employee is not active.');
        }
        $at ??= now();
        $record = AttendanceRecord::where('employee_id', $employee->id)
            ->whereNotNull('clock_in')->whereNull('clock_out')
            ->where('work_date', '>=', $at->copy()->subDay()->toDateString())
            ->latest('work_date')->first();

        if ($record) {
            // Clock out (supports overnight shifts that started yesterday)
            $record->fill(['clock_out' => $at, 'source_out' => $source]);
            $this->calculate($record);
            $record->save();
            return $record;
        }

        $today = AttendanceRecord::firstOrNew(['employee_id' => $employee->id, 'work_date' => $at->toDateString()]);
        if ($today->exists && $today->clock_out) {
            throw new BusinessRuleException('Already clocked out today. Ask a supervisor to correct the time card if needed.');
        }
        $roster = RosterEntry::with('shift')->where('employee_id', $employee->id)->where('work_date', $at->toDateString())->first();
        $today->fill([
            'clock_in' => $at, 'source_in' => $source, 'device_id' => $device?->id, 'ip_address' => $ip,
            'shift_id' => $roster?->shift_id, 'status' => 'incomplete',
        ]);
        $this->calculate($today);
        $today->save();
        return $today;
    }

    public function calculate(AttendanceRecord $r): void
    {
        $shift = $r->shift_id ? ($r->shift ?? $r->shift()->first()) : null;
        $date = Carbon::parse($r->work_date)->toDateString();
        $late = 0;
        $early = 0;
        $scheduled = self::STANDARD_MINUTES;
        $break = 60;

        if ($shift) {
            $start = Carbon::parse($date.' '.$shift->start_time);
            $end = Carbon::parse($date.' '.$shift->end_time);
            if ($end->lte($start)) $end->addDay(); // overnight
            $break = $shift->break_minutes;
            $scheduled = max(0, (int) $start->diffInMinutes($end) - $break);
            if ($r->clock_in && $r->clock_in->gt($start->copy()->addMinutes($shift->grace_minutes))) {
                $late = (int) $start->diffInMinutes($r->clock_in);
            }
            if ($r->clock_out && $r->clock_out->lt($end)) {
                $early = (int) $r->clock_out->diffInMinutes($end);
            }
        }

        $worked = 0;
        if ($r->clock_in && $r->clock_out) {
            $elapsed = (int) $r->clock_in->diffInMinutes($r->clock_out);
            $worked = max(0, $elapsed - ($elapsed > 300 ? $break : 0));
        }

        $r->late_minutes = $late;
        $r->early_leave_minutes = $early;
        $r->worked_minutes = $worked;
        $r->overtime_minutes = $r->clock_out ? max(0, $worked - $scheduled) : 0;
        $r->status = ! $r->clock_out ? ($late ? 'late' : 'incomplete') : ($late ? 'late' : 'present');
    }

    /** Supervisor correction; original punches are preserved in original_values. */
    public function correct(AttendanceRecord $r, ?string $clockIn, ?string $clockOut, string $reason, ?string $status = null): AttendanceRecord
    {
        $original = $r->original_values ?? ['clock_in' => $r->clock_in?->toDateTimeString(), 'clock_out' => $r->clock_out?->toDateTimeString(), 'status' => $r->status];
        $r->fill([
            'clock_in' => $clockIn ? Carbon::parse($clockIn) : null,
            'clock_out' => $clockOut ? Carbon::parse($clockOut) : null,
            'source_in' => $r->source_in ?? 'manual',
            'source_out' => $clockOut ? ($r->source_out ?? 'manual') : null,
            'corrected_by' => auth()->id(),
            'correction_reason' => $reason,
            'original_values' => $original,
        ]);
        if ($r->clock_in && $r->clock_out && $r->clock_out->lte($r->clock_in)) {
            throw new BusinessRuleException('Clock-out must be after clock-in.');
        }
        $this->calculate($r);
        if ($status) {
            $r->status = $status;
        }
        $r->save();
        AuditService::log('staff', 'attendance_corrected', $r, $reason, $original, ['clock_in' => $clockIn, 'clock_out' => $clockOut]);
        return $r;
    }

    /** For rostered employees without a punch: mark absent (or on_leave if approved leave). */
    public function markAbsences(Carbon $date): int
    {
        $n = 0;
        RosterEntry::where('work_date', $date->toDateString())->get()->each(function (RosterEntry $entry) use ($date, &$n) {
            $exists = AttendanceRecord::where('employee_id', $entry->employee_id)->where('work_date', $date->toDateString())->exists();
            if ($exists) return;
            $onLeave = LeaveRequest::where('employee_id', $entry->employee_id)->where('status', 'approved')
                ->where('start_date', '<=', $date->toDateString())->where('end_date', '>=', $date->toDateString())->exists();
            AttendanceRecord::create(['employee_id' => $entry->employee_id, 'work_date' => $date->toDateString(), 'shift_id' => $entry->shift_id,
                'status' => $onLeave ? 'on_leave' : 'absent']);
            $n++;
        });
        return $n;
    }

    /** Aggregates for time card / reports. */
    public function summary($records): array
    {
        return [
            'days' => $records->whereIn('status', ['present', 'late'])->count(),
            'late_days' => $records->where('late_minutes', '>', 0)->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'on_leave' => $records->where('status', 'on_leave')->count(),
            'worked' => (int) $records->sum('worked_minutes'),
            'overtime' => (int) $records->sum('overtime_minutes'),
            'late' => (int) $records->sum('late_minutes'),
            'early' => (int) $records->sum('early_leave_minutes'),
        ];
    }
}
