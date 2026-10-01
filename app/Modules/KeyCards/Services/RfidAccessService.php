<?php

namespace App\Modules\KeyCards\Services;

use App\Models\AccessLog;
use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Models\KeyCard;
use App\Models\KeyCardAssignment;
use App\Models\Villa;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Staff\Services\AttendanceService;
use Illuminate\Support\Carbon;

/**
 * Decides whether an RFID scan opens a door, records the decision in access_logs, and (for time-clock readers)
 * punches staff attendance. Used by the device API (POST /api/v1/rfid/scan) and the in-app simulator.
 *
 * Identities:
 *  - key cards (guest / staff / housekeeping / master / zone) through their active KeyCardAssignment;
 *  - employee RFID badges (employees.rfid_uid) for staff doors and time clocks.
 * Nothing here talks to lock hardware: physical encoding stays with the Lock Bridge (KeyCardService).
 */
class RfidAccessService
{
    public function __construct(private AttendanceService $attendance) {}

    public static function normalise(string $uid): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $uid));
    }

    /**
     * @return array{granted: bool, decision: string, reason: string, holder: ?string, holder_type: ?string, target: string,
     *               card: ?KeyCard, employee: ?Employee, attendance: ?array, log_id: int}
     */
    public function scan(string $uid, ?AttendanceDevice $device = null, ?Villa $villa = null, ?string $zone = null, ?Carbon $at = null, string $source = 'device'): array
    {
        $uid = self::normalise($uid);
        $at ??= now();
        $villa ??= $device?->villa;
        $zone = $zone ?: $device?->zone;
        $target = collect([$villa ? 'Villa '.$villa->code : null, $zone])->filter()->implode(' · ') ?: 'Any door';

        $card = $uid !== '' ? KeyCard::with(['activeAssignment.villa', 'activeAssignment.guest', 'activeAssignment.employee'])->where('uid', $uid)->first() : null;
        $employee = $uid !== '' ? Employee::with('department')->where('rfid_uid', $uid)->first() : null;
        $checkAccess = ! $device || $device->handlesAccess();

        [$granted, $reason, $holder, $holderType, $assignment] = match (true) {
            $uid === '' => [false, 'No card UID read', null, null, null],
            ! $checkAccess => [true, 'Time clock scan', $employee?->fullName(), $employee ? 'staff' : null, null],
            $card !== null => $this->decideCard($card, $villa, $zone, $at),
            $employee !== null => $this->decideBadge($employee, $villa, $zone),
            default => [false, 'Unknown card', null, null, null],
        };
        if ($card && ! $employee && $assignment?->employee) {
            $employee = $assignment->employee;
        }

        // Staff time clock: punch on a granted (or attendance-only) scan by an employee.
        $punch = null;
        if ($device?->handlesAttendance() && $employee && ($granted || ! $checkAccess)) {
            try {
                $r = $this->attendance->clock($employee, 'rfid', $device, $at, request()?->ip());
                $punch = ['action' => $r->clock_out ? 'clock_out' : 'clock_in', 'time' => ($r->clock_out ?? $r->clock_in)->format('H:i'),
                    'late_minutes' => $r->late_minutes, 'worked' => minutes_hm($r->worked_minutes)];
            } catch (BusinessRuleException $e) {
                $punch = ['error' => $e->getMessage()];
            }
        }

        $event = $granted ? ($checkAccess ? 'open' : 'punch') : match (true) {
            str_contains($reason, 'expired') => 'expired_card',
            str_contains($reason, 'lost') || str_contains($reason, 'blocked') || str_contains($reason, 'damaged') => 'blocked_card',
            default => 'denied',
        };
        $log = AccessLog::create([
            'villa_id' => $villa?->id, 'lock_ref' => $villa?->lock_ref ?? $villa?->code, 'zone' => $zone, 'card_uid' => $uid ?: null,
            'key_card_id' => $card?->id, 'employee_id' => $employee?->id, 'device_id' => $device?->id,
            'event' => $event, 'granted' => $granted, 'reason' => mb_substr($reason, 0, 120), 'source' => $source,
            'details' => array_filter(['holder' => $holder, 'holder_type' => $holderType, 'target' => $target, 'device' => $device?->name, 'attendance' => $punch]),
            'occurred_at' => $at,
        ]);

        if (! $granted && $card && in_array($card->status, ['lost', 'blocked'], true)) {
            NotificationService::notify('keycard.alert', 'Blocked card used: '.$card->uid, $reason.' · '.$target, route('admin.keycards.show', $card), 'danger', 'keycards.manage');
        }

        return ['granted' => $granted, 'decision' => $granted ? 'allow' : 'deny', 'reason' => $reason, 'holder' => $holder, 'holder_type' => $holderType,
            'target' => $target, 'card' => $card, 'employee' => $employee, 'attendance' => $punch, 'log_id' => $log->id];
    }

    /** @return array{0:bool,1:string,2:?string,3:?string,4:?KeyCardAssignment} */
    private function decideCard(KeyCard $card, ?Villa $villa, ?string $zone, Carbon $at): array
    {
        if (in_array($card->status, ['lost', 'blocked', 'damaged', 'retired'], true)) {
            return [false, 'Card '.$card->status, null, null, null];
        }
        $a = $card->activeAssignment;
        if (! $a) {
            $last = $card->assignments()->with(['guest', 'employee'])->first();
            return $last && in_array($last->status, ['expired', 'revoked'], true)
                ? [false, $last->status === 'expired' ? 'Card expired' : 'Card revoked ('.($last->revoke_reason ?: 'no reason').')', $last->holderName() !== '—' ? $last->holderName() : null, $last->employee_id ? 'staff' : 'guest', $last]
                : [false, 'Card not assigned', null, null, null];
        }
        $holder = $a->holderName() !== '—' ? $a->holderName() : null;
        $type = $a->employee_id ? 'staff' : 'guest';
        if ($at->lt($a->valid_from)) return [false, 'Card not valid until '.$a->valid_from->format('d M H:i'), $holder, $type, $a];
        if ($at->gt($a->valid_to)) return [false, 'Card expired '.$a->valid_to->format('d M H:i'), $holder, $type, $a];

        $locks = $a->lock_refs ?? [];
        if (! $villa && ! $zone) return [true, 'Valid card', $holder, $type, $a];
        if ($a->access_level === 'master') return [true, 'Master card', $holder, $type, $a];
        if (in_array('ALL', $locks, true)) return [true, 'All doors · '.ucfirst($a->access_level).' card', $holder, $type, $a];
        if ($villa && (in_array($villa->lock_ref ?: $villa->code, $locks, true) || in_array($villa->code, $locks, true))) {
            return [true, $type === 'guest' ? 'Guest of villa '.$villa->code : 'Assigned to villa '.$villa->code, $holder, $type, $a];
        }
        if ($zone) {
            if ($type === 'guest' && in_array($zone, config('vaasal.locks.guest_zones'), true)) return [true, 'Guest shared area', $holder, $type, $a];
            if ($a->zone && strcasecmp($a->zone, $zone) === 0) return [true, 'Zone '.$zone, $holder, $type, $a];
            if (in_array('ZONE:'.$zone, $locks, true)) return [true, 'Zone '.$zone, $holder, $type, $a];
        }
        return [false, 'Not valid for '.($villa ? 'villa '.$villa->code : $zone), $holder, $type, $a];
    }

    /** Employee RFID badge (not an encoded key card): staff zones by department; never guest villas. */
    private function decideBadge(Employee $e, ?Villa $villa, ?string $zone): array
    {
        $name = $e->fullName();
        if ($e->status !== 'active') return [false, 'Employee '.str_replace('_', ' ', $e->status), $name, 'staff', null];
        if ($villa) return [false, 'Staff badge cannot open guest villas — use a staff key card', $name, 'staff', null];
        if (! $zone) return [true, 'Valid staff badge', $name, 'staff', null];
        $rule = config('vaasal.locks.staff_zones')[$zone] ?? null;
        if ($rule === '*' || (is_array($rule) && in_array($e->department?->code, $rule, true))) {
            return [true, 'Staff: '.($e->department?->name ?? 'employee'), $name, 'staff', null];
        }
        return [false, $rule === null ? 'Zone '.$zone.' is not a staff zone' : 'No access to '.$zone.' for '.($e->department?->name ?? 'this department'), $name, 'staff', null];
    }
}
