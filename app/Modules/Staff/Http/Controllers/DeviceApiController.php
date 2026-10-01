<?php

namespace App\Modules\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Staff\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Attendance device API for RFID readers, QR scanners, biometric terminals and PIN kiosks.
 * POST /api/v1/attendance/punch   Authorization: Bearer <device token>
 * Body: { "identifier_type": "rfid|qr|biometric|pin", "identifier": "...", "employee_no": "(pin only)", "occurred_at": "(optional ISO-8601)" }
 */
class DeviceApiController extends Controller
{
    public function punch(Request $request, AttendanceService $attendance)
    {
        $token = (string) $request->bearerToken();
        $device = $token !== '' ? AttendanceDevice::where('api_token_hash', hash('sha256', $token))->where('is_active', true)->first() : null;
        if (! $device) {
            return response()->json(['message' => 'Unknown or inactive device.'], 401);
        }
        $data = $request->validate([
            'identifier_type' => ['required', 'in:rfid,qr,biometric,pin'],
            'identifier' => ['required', 'string', 'max:80'],
            'employee_no' => ['required_if:identifier_type,pin', 'nullable', 'string', 'max:20'],
            'occurred_at' => ['nullable', 'date'],
        ]);
        $device->update(['last_seen_at' => now()]);

        $employee = match ($data['identifier_type']) {
            'rfid' => Employee::where('rfid_uid', strtoupper($data['identifier']))->first(),
            'qr' => Employee::where('qr_token', $data['identifier'])->first(),
            'biometric' => Employee::where('biometric_ref', $data['identifier'])->first(),
            'pin' => tap(Employee::where('employee_no', $data['employee_no'])->first(), function ($e) use ($data) {
                if ($e && ! Hash::check($data['identifier'], (string) $e->attendance_pin)) {
                    abort(response()->json(['message' => 'Incorrect PIN.'], 422));
                }
            }),
        };
        if (! $employee) {
            return response()->json(['message' => 'Employee not recognised.'], 404);
        }

        try {
            $at = isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : now();
            if ($at->gt(now()->addMinutes(5)) || $at->lt(now()->subDays(2))) {
                return response()->json(['message' => 'Punch time outside the accepted window.'], 422);
            }
            $r = $attendance->clock($employee, $device->type === 'kiosk' ? 'kiosk' : $device->type, $device, $at, $request->ip());
        } catch (BusinessRuleException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'employee' => $employee->fullName(),
            'action' => $r->clock_out ? 'clock_out' : 'clock_in',
            'time' => ($r->clock_out ?? $r->clock_in)->format('H:i'),
            'late_minutes' => $r->late_minutes,
            'worked' => minutes_hm($r->worked_minutes),
        ]);
    }
}
