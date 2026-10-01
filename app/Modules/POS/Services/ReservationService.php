<?php

namespace App\Modules\POS\Services;

use App\Models\Booking;
use App\Models\Outlet;
use App\Models\PosOrder;
use App\Models\PosTable;
use App\Models\TableReservation;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Restaurant table reservations: book, confirm, seat (opens the check at the table), complete, cancel, no-show.
 * A table cannot hold two active reservations whose time windows overlap.
 */
class ReservationService
{
    public function __construct(private PosOrderService $orders) {}

    public function save(array $data, ?TableReservation $r = null): TableReservation
    {
        $outlet = Outlet::findOrFail($data['outlet_id']);
        $at = Carbon::parse($data['reserved_for']);
        $duration = (int) ($data['duration_minutes'] ?? 90);
        $table = ! empty($data['pos_table_id']) ? PosTable::where('outlet_id', $outlet->id)->where('is_active', true)->findOrFail($data['pos_table_id']) : null;
        if (! $r && $at->lt(now()->subMinutes(15))) {
            throw new BusinessRuleException('A reservation cannot start in the past.');
        }
        if ($table && (int) $data['party_size'] > $table->seats + 2) {
            throw new BusinessRuleException("Table {$table->name} seats {$table->seats}; choose a larger table for {$data['party_size']} guests.");
        }
        if (! empty($data['booking_id'])) {
            Booking::where('status', 'checked_in')->findOrFail($data['booking_id']);
        }

        return DB::transaction(function () use ($data, $r, $outlet, $at, $duration, $table) {
            if ($table) {
                $clash = TableReservation::where('pos_table_id', $table->id)->whereIn('status', TableReservation::ACTIVE)
                    ->when($r, fn ($q) => $q->where('id', '!=', $r->id))->lockForUpdate()->get()
                    ->first(fn (TableReservation $x) => $x->reserved_for->lt($at->copy()->addMinutes($duration)) && $x->endsAt()->gt($at));
                if ($clash) {
                    throw new BusinessRuleException("Table {$table->name} is already reserved for {$clash->guest_name} at {$clash->reserved_for->format('H:i')}.");
                }
            }
            $fields = ['outlet_id' => $outlet->id, 'pos_table_id' => $table?->id, 'booking_id' => $data['booking_id'] ?? null, 'guest_name' => $data['guest_name'],
                'phone' => $data['phone'] ?? null, 'party_size' => (int) $data['party_size'], 'reserved_for' => $at, 'duration_minutes' => $duration,
                'notes' => $data['notes'] ?? null, 'status' => $data['status'] ?? ($r?->status ?? 'confirmed')];
            if ($r) {
                $r->update($fields);
            } else {
                $r = TableReservation::create($fields + ['created_by' => auth()->id()]);
            }
            AuditService::log('pos', 'reservation_saved', $r, "{$r->guest_name} · {$r->party_size} pax · ".$r->reserved_for->format('d M H:i').($table ? ' · table '.$table->name : ''));
            return $r;
        });
    }

    public function setStatus(TableReservation $r, string $status): TableReservation
    {
        $allowed = ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['seated', 'cancelled', 'no_show', 'pending'], 'seated' => ['completed']];
        if (! in_array($status, $allowed[$r->status] ?? [], true)) {
            throw new BusinessRuleException('A '.strtolower(TableReservation::STATUSES[$r->status]).' reservation cannot be marked '.strtolower(TableReservation::STATUSES[$status] ?? $status).'.');
        }
        if ($status === 'seated') {
            return $this->seat($r);
        }
        $r->update(['status' => $status]);
        AuditService::log('pos', 'reservation_'.$status, $r, $r->guest_name);
        return $r;
    }

    /** Seat the party: opens (or reuses) the dine-in check at the reserved table. */
    public function seat(TableReservation $r): TableReservation
    {
        if (! $r->pos_table_id) {
            throw new BusinessRuleException('Assign a table before seating the party.');
        }
        $order = $this->orders->open($r->outlet, ['type' => 'dine_in', 'pos_table_id' => $r->pos_table_id, 'covers' => $r->party_size, 'guest_name' => $r->guest_name]);
        if ($r->booking_id && ! $order->booking_id) {
            // In-house hotel guest: remember the stay so "Charge to villa" is preselected at payment.
            $order->update(['booking_id' => $r->booking_id, 'villa_id' => $r->booking?->activeVillas()->first()?->villa_id]);
        }
        $r->update(['status' => 'seated', 'pos_order_id' => $order->id]);
        AuditService::log('pos', 'reservation_seated', $r, $r->guest_name.' → '.$order->order_no);
        return $r;
    }

    /** Active reservations per table for the next few hours (shown on the terminal floor plan). */
    public function upcomingByTable(Outlet $outlet, int $hours = 3): \Illuminate\Support\Collection
    {
        return TableReservation::where('outlet_id', $outlet->id)->whereIn('status', ['pending', 'confirmed'])->whereNotNull('pos_table_id')
            ->whereBetween('reserved_for', [now()->subMinutes(30), now()->addHours($hours)])->orderBy('reserved_for')->get()->groupBy('pos_table_id')->map->first();
    }
}
