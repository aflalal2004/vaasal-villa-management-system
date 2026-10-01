<?php

namespace App\Modules\Maintenance\Services;

use App\Models\MaintenanceTicket;
use App\Models\Villa;
use App\Modules\Booking\Services\AvailabilityService;
use App\Modules\Channel\Services\ChannelSyncService;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Maintenance tickets. A "blocking" ticket takes the villa out of order: it disappears from
 * availability search and, where no bookings exist, its nights are blocked in the ledger and
 * pushed to the channel manager. Resolving the ticket returns the villa to sale.
 */
class MaintenanceService
{
    public function __construct(private AvailabilityService $availability, private ChannelSyncService $channel) {}

    public function report(array $data, int $blockDays = 1): MaintenanceTicket
    {
        $ticket = DB::transaction(function () use ($data) {
            $ticket = MaintenanceTicket::create($data + [
                'ticket_no' => DocumentNumberService::next('maintenance'),
                'reported_by' => auth()->id(),
                'blocks_inventory' => ($data['severity'] ?? 'medium') === 'blocking',
            ]);
            if ($ticket->villa_id) {
                $this->refreshVillaStatus($ticket->villa);
            }
            AuditService::log('maintenance', 'reported', $ticket, $ticket->title);
            return $ticket;
        });

        if ($ticket->blocks_inventory && $ticket->villa) {
            $this->tryBlock($ticket, $blockDays);
        }

        NotificationService::notify('maintenance.reported', "Maintenance {$ticket->ticket_no}: {$ticket->title}",
            ($ticket->villa ? 'Villa '.$ticket->villa->code.' · ' : '').MaintenanceTicket::SEVERITIES[$ticket->severity],
            route('admin.maintenance.show', $ticket), $ticket->severity === 'blocking' || $ticket->severity === 'high' ? 'danger' : 'warning', 'maintenance.manage');

        return $ticket;
    }

    public function updateStatus(MaintenanceTicket $ticket, string $status, ?string $notes = null, ?float $cost = null): MaintenanceTicket
    {
        if (! array_key_exists($status, MaintenanceTicket::STATUSES)) {
            throw new BusinessRuleException('Unknown status.');
        }
        DB::transaction(function () use ($ticket, $status, $notes, $cost) {
            $ticket->status = $status;
            if ($status === 'in_progress' && ! $ticket->started_at) $ticket->started_at = now();
            if (in_array($status, ['resolved', 'closed'], true)) {
                $ticket->resolved_at ??= now();
                $ticket->resolution_notes = $notes ?? $ticket->resolution_notes;
                if ($cost !== null) $ticket->cost = $cost;
            }
            AuditService::logChanges('maintenance', $ticket, 'status_changed');
            $ticket->save();

            if (in_array($status, ['resolved', 'closed'], true) && $ticket->block) {
                $this->availability->unblock($ticket->block);
                $ticket->update(['villa_block_id' => null]);
            }
            if ($ticket->villa) {
                $this->refreshVillaStatus($ticket->villa);
            }
        });
        $this->channel->queueAri(now(), now()->addDays(30));
        return $ticket;
    }

    /** Villa maintenance status reflects its worst open ticket. */
    public function refreshVillaStatus(Villa $villa): void
    {
        $open = $villa->tickets()->whereNotIn('status', ['resolved', 'closed'])->get();
        $status = $open->contains('severity', 'blocking') ? 'out_of_order' : ($open->isNotEmpty() ? 'issue' : 'ok');
        $updates = ['maintenance_status' => $status];
        if ($status !== 'out_of_order' && $villa->maintenance_status === 'out_of_order' && $villa->hk_status === 'clean') {
            $updates['hk_status'] = 'ready';
        }
        $villa->update($updates);
    }

    private function tryBlock(MaintenanceTicket $ticket, int $days): void
    {
        $start = now()->startOfDay();
        $end = now()->startOfDay()->addDays(max(1, $days));
        if ($this->availability->isVillaFree($ticket->villa_id, $start, $end)) {
            $block = $this->availability->block($ticket->villa, $start, $end, 'maintenance', 'Ticket '.$ticket->ticket_no);
            $ticket->update(['villa_block_id' => $block->id]);
        } else {
            NotificationService::notify('maintenance.relocate', "Villa {$ticket->villa->code} out of order with bookings",
                'Existing bookings overlap the repair window. Review and relocate guests if needed.', route('admin.calendar'), 'danger', 'bookings.manage');
        }
        $this->channel->queueAri($start, $end->copy()->addDays(30));
    }
}
