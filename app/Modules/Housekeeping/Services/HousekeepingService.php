<?php

namespace App\Modules\Housekeeping\Services;

use App\Models\Booking;
use App\Models\ChecklistTemplate;
use App\Models\Employee;
use App\Models\HkTask;
use App\Models\LinenItem;
use App\Models\LinenMovement;
use App\Models\Villa;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Villa housekeeping status flow: Dirty → Cleaning → Inspection → Clean → Ready.
 * Checkout makes a villa Dirty and creates a departure task; supervisor approval makes it Ready
 * (sellable for same-day arrivals) unless maintenance has it out of order.
 */
class HousekeepingService
{
    public function createTask(Villa $villa, string $type, Carbon|string $date, ?Booking $booking = null, string $priority = 'normal', ?int $assignee = null, ?string $notes = null): HkTask
    {
        $date = Carbon::parse($date)->toDateString();
        // One open task of a type per villa per day
        $existing = HkTask::where('villa_id', $villa->id)->where('type', $type)->where('scheduled_date', $date)
            ->whereIn('status', \App\Models\HkTask::OPEN)->first();
        if ($existing) return $existing;

        if ($type === 'departure' || $type === 'deep') {
            $priority = $this->arrivingToday($villa) ? 'urgent' : $priority;
        }

        return DB::transaction(function () use ($villa, $type, $date, $booking, $priority, $assignee, $notes) {
            $task = HkTask::create([
                'villa_id' => $villa->id, 'booking_id' => $booking?->id, 'type' => $type, 'priority' => $priority,
                'assigned_to' => $assignee, 'scheduled_date' => $date, 'notes' => $notes, 'created_by' => auth()->id(),
            ]);
            $template = ChecklistTemplate::where('task_type', $type)->where('is_active', true)->first()
                ?? ChecklistTemplate::where('is_active', true)->first();
            foreach ($template->items ?? [] as $i => $label) {
                $task->items()->create(['label' => $label, 'sort_order' => $i]);
            }
            return $task;
        });
    }

    /** Checkout hook: villa → Dirty + departure task + immediate alert to housekeeping. */
    public function onCheckout(Villa $villa, Booking $booking): HkTask
    {
        $villa->update(['occupancy_status' => 'vacant', 'hk_status' => 'dirty']);
        $task = $this->createTask($villa, 'departure', now(), $booking, 'high', notes: 'Departure of '.$booking->guest->fullName().' ('.$booking->reference.')');
        NotificationService::notify('hk.departure', "Villa {$villa->code} is ready for cleaning after guest checkout.",
            'Departure clean · '.ucfirst($task->priority).' priority · '.$booking->guest->fullName().' checked out '.now()->format('H:i'),
            route('admin.housekeeping.tasks.show', $task), $task->priority === 'urgent' ? 'danger' : 'warning', 'housekeeping.work|housekeeping.view');
        return $task;
    }

    /** A room attendant claims an open task (or confirms one already assigned to them). */
    public function accept(HkTask $task, Employee $employee): HkTask
    {
        if (! in_array($task->status, ['pending', 'paused'], true)) {
            throw new BusinessRuleException('Only pending tasks can be accepted.');
        }
        if ($task->assigned_to && $task->assigned_to !== $employee->id) {
            throw new BusinessRuleException('This task is already assigned to '.$task->assignee?->fullName().'.');
        }
        $task->update(['assigned_to' => $employee->id, 'accepted_at' => now()]);
        AuditService::log('housekeeping', 'accepted', $task, 'Villa '.$task->villa->code.' accepted by '.$employee->fullName());
        return $task;
    }

    public function pause(HkTask $task, ?string $reason = null): HkTask
    {
        if ($task->status !== 'in_progress') {
            throw new BusinessRuleException('Only tasks being cleaned can be paused.');
        }
        $task->update(['status' => 'paused', 'paused_at' => now(), 'notes' => $reason ? trim(($task->notes ? $task->notes.' | ' : '').'Paused: '.$reason) : $task->notes]);
        AuditService::log('housekeeping', 'paused', $task, 'Villa '.$task->villa->code.($reason ? ' — '.$reason : ''));
        return $task;
    }

    public function assign(HkTask $task, ?Employee $employee): HkTask
    {
        $task->update(['assigned_to' => $employee?->id]);
        AuditService::log('housekeeping', 'assigned', $task, 'Villa '.$task->villa->code.' → '.($employee?->fullName() ?? 'unassigned'));
        return $task;
    }

    public function start(HkTask $task): HkTask
    {
        if (! in_array($task->status, ['pending', 'in_progress', 'paused'], true)) {
            throw new BusinessRuleException('This task has already been submitted.');
        }
        $paused = $task->status === 'paused' && $task->paused_at ? (int) $task->paused_at->diffInMinutes(now()) : 0;
        $task->update(['status' => 'in_progress', 'started_at' => $task->started_at ?? now(), 'paused_at' => null,
            'paused_minutes' => (int) $task->paused_minutes + $paused, 'accepted_at' => $task->accepted_at ?? now()]);
        $task->villa->update(['hk_status' => 'cleaning']);
        return $task;
    }

    public function toggleItem(HkTask $task, int $itemId, bool $checked): void
    {
        if ($task->status !== 'in_progress') {
            throw new BusinessRuleException('Start the task before ticking checklist items.');
        }
        $task->items()->whereKey($itemId)->update(['is_checked' => $checked, 'checked_at' => $checked ? now() : null]);
    }

    public function requestInspection(HkTask $task, array $linen = []): HkTask
    {
        if ($task->status !== 'in_progress') {
            throw new BusinessRuleException('Only tasks in progress can be sent for inspection.');
        }
        $unchecked = $task->items()->where('is_checked', false)->count();
        if ($unchecked > 0) {
            throw new BusinessRuleException("Complete the checklist first — {$unchecked} item(s) still open.");
        }
        DB::transaction(function () use ($task, $linen) {
            $this->recordLinen($task, $linen);
            $task->update(['status' => 'inspection', 'finished_at' => now()]);
            $task->villa->update(['hk_status' => 'inspection']);
        });
        NotificationService::notify('hk.inspection', 'Inspection requested: '.$task->villa->code, ($task->assignee?->fullName() ?? 'Housekeeping').' finished '.strtolower(HkTask::TYPES[$task->type]),
            route('admin.housekeeping.tasks.show', $task), 'info', 'housekeeping.inspect');
        return $task;
    }

    public function approve(HkTask $task, ?string $notes = null): HkTask
    {
        if ($task->status !== 'inspection') {
            throw new BusinessRuleException('Only tasks awaiting inspection can be approved.');
        }
        DB::transaction(function () use ($task, $notes) {
            $task->update(['status' => 'completed', 'inspection_result' => 'pass', 'inspection_notes' => $notes, 'inspected_by' => auth()->id(), 'inspected_at' => now()]);
            $villa = $task->villa;
            // Ready only when nothing blocks sale; otherwise stays Clean until maintenance clears it.
            $villa->update(['hk_status' => $villa->maintenance_status === 'out_of_order' ? 'clean' : 'ready']);
            AuditService::log('housekeeping', 'approved', $task, 'Villa '.$villa->code.' passed inspection');
        });
        $villa = $task->villa->fresh();
        if ($villa->hk_status === 'ready') {
            NotificationService::notify('hk.ready', "Villa {$villa->code} is ready", $this->arrivingToday($villa) ? 'A guest is arriving today.' : null,
                route('admin.frontdesk.index'), 'success', 'frontdesk.checkin');
        }
        return $task;
    }

    public function reject(HkTask $task, string $notes): HkTask
    {
        if ($task->status !== 'inspection') {
            throw new BusinessRuleException('Only tasks awaiting inspection can be rejected.');
        }
        $task->update(['status' => 'in_progress', 'inspection_result' => 'fail', 'inspection_notes' => $notes, 'inspected_by' => auth()->id(),
            'inspected_at' => now(), 'rejection_count' => $task->rejection_count + 1, 'finished_at' => null]);
        $task->villa->update(['hk_status' => 'cleaning']);
        AuditService::log('housekeeping', 'rejected', $task, $notes);
        return $task;
    }

    /** Supervisor manual override of a villa's housekeeping status. */
    public function setVillaStatus(Villa $villa, string $status, ?string $reason = null): void
    {
        if (! array_key_exists($status, Villa::HK_LABELS)) {
            throw new BusinessRuleException('Unknown housekeeping status.');
        }
        if ($status === 'ready' && $villa->maintenance_status === 'out_of_order') {
            throw new BusinessRuleException("Villa {$villa->code} is out of order. Resolve the maintenance ticket first.");
        }
        $old = $villa->hk_status;
        $villa->update(['hk_status' => $status]);
        AuditService::log('housekeeping', 'status_override', $villa, "{$villa->code}: {$old} → {$status}".($reason ? " ({$reason})" : ''));
    }

    /** Daily stay-over service tasks for occupied villas. */
    public function generateStayovers(Carbon|string|null $date = null): int
    {
        $date = Carbon::parse($date ?? now())->toDateString();
        $n = 0;
        Villa::where('occupancy_status', 'occupied')->get()->each(function (Villa $v) use ($date, &$n) {
            $stay = $v->currentStay()->with('booking')->first();
            if ($stay && $stay->departure->toDateString() !== $date) {
                // createTask() returns the existing open task when one exists; count only new ones.
                if ($this->createTask($v, 'stayover', $date, $stay->booking, 'normal')->wasRecentlyCreated) $n++;
            }
        });
        return $n;
    }

    public function recordLinen(HkTask $task, array $linen): void
    {
        foreach ($linen as $itemId => $qty) {
            $out = (int) ($qty['out'] ?? 0);
            $in = (int) ($qty['in'] ?? 0);
            if ($out === 0 && $in === 0) continue;
            $item = LinenItem::lockForUpdate()->findOrFail($itemId);
            LinenMovement::create(['hk_task_id' => $task->id, 'linen_item_id' => $item->id, 'qty_out' => $out, 'qty_in' => $in, 'user_id' => auth()->id()]);
            $item->update(['stock_qty' => $item->stock_qty - $out, 'in_laundry_qty' => $item->in_laundry_qty + $in]);
        }
    }

    private function arrivingToday(Villa $villa): bool
    {
        return $villa->bookingVillas()->where('status', 'active')->where('arrival', now()->toDateString())
            ->whereHas('booking', fn ($q) => $q->where('status', 'confirmed'))->exists();
    }
}
