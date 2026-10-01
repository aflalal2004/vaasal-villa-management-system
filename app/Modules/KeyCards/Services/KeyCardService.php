<?php

namespace App\Modules\KeyCards\Services;

use App\Models\AccessLog;
use App\Models\Booking;
use App\Models\BookingVilla;
use App\Models\Employee;
use App\Models\KeyCard;
use App\Models\KeyCardAssignment;
use App\Models\LockJob;
use App\Models\Property;
use App\Models\Villa;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use App\Modules\KeyCards\Providers\SimulatorLockProvider;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * RFID key card lifecycle: registration, guest/villa/staff assignment, activation, duplicate,
 * extension, lost/replacement, revoke/block, expiry and access logging.
 *
 * Physical encoding is delegated to lock jobs picked up by the on-premise Lock Bridge
 * (or processed instantly by the simulator when LOCK_DRIVER=simulator).
 */
class KeyCardService
{
    public function register(string $uid, ?string $cardNumber = null, string $type = 'guest', ?string $notes = null): KeyCard
    {
        $uid = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $uid));
        if ($uid === '') {
            throw new BusinessRuleException('Card UID is required.');
        }
        $card = KeyCard::create(['property_id' => Property::current()->id, 'uid' => $uid, 'card_number' => $cardNumber ?: null, 'type' => $type, 'notes' => $notes]);
        AuditService::log('keycards', 'registered', $card, "Card {$uid} registered ({$type})");
        return $card;
    }

    /** Find a card by UID, registering it on the fly if the front desk scans a new blank card. */
    public function findOrRegister(string $uid, string $type = 'guest'): KeyCard
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $uid));
        return KeyCard::where('uid', $clean)->first() ?? $this->register($clean, null, $type);
    }

    /**
     * Issue a guest card for a villa on a checked-in (or arriving) booking.
     * issue_type "new" invalidates all previous guest cards for that villa; "duplicate" adds another card.
     */
    public function issueForBooking(BookingVilla $bv, string $uid, string $issueType = 'new', ?Carbon $validTo = null): KeyCardAssignment
    {
        $booking = $bv->booking;
        if (! in_array($booking->status, ['confirmed', 'checked_in'], true)) {
            throw new BusinessRuleException('Cards can only be issued for confirmed or checked-in bookings.');
        }
        $card = $this->findOrRegister($uid, 'guest');
        if (in_array($card->status, ['lost', 'blocked', 'damaged', 'retired'], true)) {
            throw new BusinessRuleException("Card {$card->uid} is {$card->status} and cannot be issued.");
        }
        if ($card->status === 'active' && $card->activeAssignment && $card->activeAssignment->booking_villa_id !== $bv->id) {
            throw new BusinessRuleException("Card {$card->uid} is already active for another guest. Revoke it first.");
        }

        $villa = $bv->villa;
        $property = Property::current();
        $validFrom = now();
        $validTo ??= Carbon::parse($bv->departure->toDateString().' '.$property->check_out_time)->addMinutes(config('vaasal.locks.checkout_grace_minutes'));

        $assignment = DB::transaction(function () use ($card, $bv, $booking, $villa, $issueType, $validFrom, $validTo) {
            if ($issueType === 'new') {
                // New key: every earlier guest card for this villa stops working at the lock.
                KeyCardAssignment::where('villa_id', $villa->id)->where('access_level', 'guest')->where('status', 'active')->get()
                    ->each(fn ($a) => $this->closeAssignment($a, 'revoked', 'Superseded by new key'));
            }
            $a = KeyCardAssignment::create([
                'key_card_id' => $card->id, 'booking_id' => $booking->id, 'booking_villa_id' => $bv->id, 'villa_id' => $villa->id,
                'guest_id' => $booking->guest_id, 'access_level' => 'guest', 'lock_refs' => [$villa->lock_ref ?: $villa->code],
                'valid_from' => $validFrom, 'valid_to' => $validTo, 'issue_type' => $issueType, 'status' => 'pending', 'issued_by' => auth()->id(),
            ]);
            AuditService::log('keycards', 'issue_requested', $a, "Card {$card->uid} → {$villa->code} for {$booking->reference} ({$issueType})");
            return $a;
        });

        $this->dispatch($assignment, $issueType === 'duplicate' ? 'encode_duplicate' : 'encode_new');
        return $assignment->fresh(['card', 'jobs']);
    }

    /** Staff / master / zone / housekeeping / maintenance cards. */
    public function issueForEmployee(Employee $employee, string $uid, string $accessLevel, ?string $zone, Carbon $validTo, array $villaIds = []): KeyCardAssignment
    {
        $type = in_array($accessLevel, ['master', 'maintenance', 'housekeeping', 'zone'], true) ? $accessLevel : 'staff';
        $card = $this->findOrRegister($uid, $type);
        if ($card->status !== 'available') {
            throw new BusinessRuleException("Card {$card->uid} is {$card->status}; only available cards can be issued.");
        }
        $locks = match ($accessLevel) {
            'master' => ['ALL'],
            'zone' => Villa::where('zone', $zone)->pluck('lock_ref')->filter()->values()->all() ?: ['ZONE:'.$zone],
            default => $villaIds ? Villa::whereIn('id', $villaIds)->get()->map(fn ($v) => $v->lock_ref ?: $v->code)->all() : ['ALL'],
        };

        $assignment = KeyCardAssignment::create([
            'key_card_id' => $card->id, 'employee_id' => $employee->id, 'access_level' => $accessLevel, 'zone' => $zone,
            'lock_refs' => $locks, 'valid_from' => now(), 'valid_to' => $validTo, 'issue_type' => 'new', 'status' => 'pending', 'issued_by' => auth()->id(),
        ]);
        $card->update(['type' => $type]);
        AuditService::log('keycards', 'staff_card_requested', $assignment, "Card {$card->uid} → {$employee->fullName()} ({$accessLevel})");
        $this->dispatch($assignment, 'encode_new');
        return $assignment->fresh(['card']);
    }

    public function extend(KeyCardAssignment $a, Carbon $validTo): KeyCardAssignment
    {
        if ($a->status !== 'active') {
            throw new BusinessRuleException('Only active cards can be extended.');
        }
        $old = $a->valid_to;
        $a->update(['valid_to' => $validTo]);
        AuditService::log('keycards', 'extended', $a, 'Valid to '.$old.' → '.$validTo);
        $this->dispatch($a, 'extend');
        return $a;
    }

    public function revoke(KeyCardAssignment $a, string $reason = 'Revoked'): void
    {
        if (! in_array($a->status, ['active', 'pending'], true)) return;
        $this->closeAssignment($a, 'revoked', $reason);
        $this->dispatch($a, 'revoke');
    }

    /** Revoke every active card on a booking — called by the checkout transaction. */
    public function revokeForBooking(Booking $booking, string $reason = 'Checked out'): int
    {
        $n = 0;
        KeyCardAssignment::where('booking_id', $booking->id)->whereIn('status', ['active', 'pending'])->get()->each(function ($a) use ($reason, &$n) {
            $this->revoke($a, $reason);
            $n++;
        });
        return $n;
    }

    /** Lost card: block it and (optionally) issue a replacement as a NEW key so the lost card fails at the lock. */
    public function reportLost(KeyCard $card, ?string $replacementUid = null): ?KeyCardAssignment
    {
        $active = $card->activeAssignment;
        DB::transaction(function () use ($card, $active) {
            if ($active) {
                $this->closeAssignment($active, 'lost', 'Card reported lost');
            }
            $card->update(['status' => 'lost']);
            AuditService::log('keycards', 'lost', $card, "Card {$card->uid} reported lost");
        });
        if ($active) {
            $this->dispatch($active, 'revoke');
        }
        NotificationService::notify('keycard.lost', "Key card {$card->uid} reported lost", $active ? 'Villa '.($active->villa?->code ?? '—').' · '.$active->holderName() : null,
            route('admin.keycards.show', $card), 'warning', 'keycards.manage');

        if ($replacementUid && $active?->bookingVilla) {
            return $this->issueForBooking($active->bookingVilla, $replacementUid, 'new');
        }
        if ($replacementUid && $active?->employee) {
            return $this->issueForEmployee($active->employee, $replacementUid, $active->access_level, $active->zone, $active->valid_to);
        }
        return null;
    }

    public function block(KeyCard $card, string $reason): void
    {
        if ($card->activeAssignment) {
            $this->revoke($card->activeAssignment, 'Blocked: '.$reason);
        }
        $card->update(['status' => 'blocked', 'notes' => trim(($card->notes ? $card->notes.' | ' : '').'Blocked: '.$reason)]);
        AuditService::log('keycards', 'blocked', $card, $reason);
    }

    public function unblock(KeyCard $card): void
    {
        if (! in_array($card->status, ['blocked', 'lost', 'damaged'], true)) return;
        $card->update(['status' => 'available']);
        AuditService::log('keycards', 'unblocked', $card, 'Returned to available stock');
    }

    /** Mark expired assignments; guest cards return to available stock. Runs on schedule. */
    public function expireDue(): int
    {
        $n = 0;
        KeyCardAssignment::where('status', 'active')->where('valid_to', '<', now())->get()->each(function ($a) use (&$n) {
            $this->closeAssignment($a, 'expired', 'Validity ended');
            $n++;
        });
        return $n;
    }

    // ------------------------------------------------------------------ Lock jobs

    public function dispatch(KeyCardAssignment $a, string $action): LockJob
    {
        $a->loadMissing(['card', 'villa', 'guest', 'employee']);
        $job = LockJob::create([
            'uuid' => (string) Str::uuid(),
            'key_card_assignment_id' => $a->id,
            'action' => $action,
            'payload' => [
                'card_uid' => $a->card->uid,
                'lock_refs' => $a->lock_refs ?? [],
                'valid_from' => $a->valid_from->toIso8601String(),
                'valid_to' => $a->valid_to->toIso8601String(),
                'access_level' => $a->access_level,
                'zone' => $a->zone,
                'holder' => $a->holderName(),
                'villa_code' => $a->villa?->code,
            ],
            'requested_by' => auth()->id(),
        ]);

        if (config('vaasal.locks.driver') === 'simulator') {
            $method = match ($action) {
                'encode_new' => 'encodeNew', 'encode_duplicate' => 'encodeDuplicate', 'revoke' => 'revoke', 'extend' => 'extend', default => 'readCard',
            };
            $job->update(['status' => 'processing', 'picked_at' => now(), 'attempts' => 1]);
            $this->completeJob($job, app(SimulatorLockProvider::class)->{$method}($job->payload), 'simulator');
        }
        return $job->fresh();
    }

    /** Called by the simulator or by the Lock Bridge API when the encoder finishes. */
    public function completeJob(LockJob $job, array $result, string $source = 'online'): LockJob
    {
        $ok = (bool) ($result['success'] ?? false);
        $job->update(['status' => $ok ? 'completed' : 'failed', 'result' => $result, 'error' => $ok ? null : ($result['message'] ?? 'Unknown encoder error'), 'completed_at' => now()]);
        $a = $job->assignment;
        if (! $a) return $job;

        if (in_array($job->action, ['encode_new', 'encode_duplicate'], true)) {
            if ($ok) {
                $a->update(['status' => 'active', 'encoder' => $result['encoder'] ?? $source]);
                $a->card->update(['status' => 'active']);
                $this->log($a, 'issued', $source, ['job' => $job->uuid, 'message' => $result['message'] ?? null]);
            } else {
                $a->update(['status' => 'failed']);
                if ($a->card->status !== 'active') {
                    $a->card->update(['status' => 'available']);
                }
                NotificationService::notify('keycard.failed', 'Card encoding failed', ($result['message'] ?? '').' · card '.$a->card->uid, route('admin.keycards.show', $a->card), 'danger', 'keycards.issue');
            }
        } elseif ($job->action === 'revoke' && $ok) {
            $this->log($a, 'revoked', $source, ['job' => $job->uuid]);
        }
        return $job;
    }

    public function log(KeyCardAssignment $a, string $event, string $source = 'system', array $details = []): void
    {
        AccessLog::create([
            'villa_id' => $a->villa_id, 'lock_ref' => $a->lock_refs[0] ?? null, 'card_uid' => $a->card->uid, 'key_card_id' => $a->key_card_id,
            'event' => $event, 'source' => $source, 'details' => $details + ['holder' => $a->holderName()], 'occurred_at' => now(),
        ]);
    }

    private function closeAssignment(KeyCardAssignment $a, string $status, string $reason): void
    {
        $a->update(['status' => $status, 'revoked_at' => now(), 'revoked_by' => auth()->id(), 'revoke_reason' => $reason]);
        $card = $a->card;
        if ($card && $card->status === 'active' && ! $card->assignments()->where('status', 'active')->where('id', '!=', $a->id)->exists()) {
            $card->update(['status' => $status === 'lost' ? 'lost' : 'available']);
        }
        AuditService::log('keycards', $status, $a, "Card {$card?->uid}: {$reason}");
    }
}
