<?php

namespace App\Modules\POS\Services;

use App\Models\CashMovement;
use App\Models\Outlet;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\PosShift;
use App\Models\User;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cashier shifts and cash drawer: opening float, cash in / paid-outs / bank deposits / adjustments, sales and refunds,
 * blind count at close with expected vs counted variance (X report mid-shift, Z report at close — frozen on the shift),
 * manager review, and an audited reopen for authorised users. All figures come from pos_payments and cash_movements.
 */
class PosShiftService
{
    /** Sri Lankan notes and coins offered on the drawer count. */
    public const DENOMINATIONS = [5000, 2000, 1000, 500, 100, 50, 20, 10, 5, 2, 1];

    public function current(User $user, ?int $outletId = null): ?PosShift
    {
        return PosShift::where('user_id', $user->id)->where('status', 'open')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))->latest('id')->first();
    }

    /**
     * The shift a payment or refund is recorded against: the cashier's open shift at the check's outlet, otherwise
     * their own open shift at another outlet (one cashier, one drawer — e.g. room-service checks settled at the
     * restaurant counter). Null only when the cashier has no open shift at all.
     */
    public function forPayment(User $user, int $outletId): ?PosShift
    {
        return $this->current($user, $outletId) ?? $this->current($user);
    }

    public function open(Outlet $outlet, User $user, float $float, ?string $notes = null): PosShift
    {
        if ($this->current($user, $outlet->id)) {
            throw new BusinessRuleException('You already have an open shift at '.$outlet->name.'.');
        }
        if ($float < 0) throw new BusinessRuleException('Opening float cannot be negative.');
        return DB::transaction(function () use ($outlet, $user, $float, $notes) {
            $shift = PosShift::create(['outlet_id' => $outlet->id, 'user_id' => $user->id, 'opened_at' => now(), 'opening_float' => $float, 'notes' => $notes]);
            CashMovement::create(['pos_shift_id' => $shift->id, 'type' => 'float', 'amount' => $float, 'reason' => 'Opening float', 'user_id' => $user->id]);
            AuditService::log('pos', 'shift_opened', $shift, $outlet->name.' float '.money($float));
            return $shift;
        });
    }

    /**
     * Manual drawer movement. $amount is entered positive, except for adjustments where the sign is the
     * correction (+ adds cash to the drawer, − removes it).
     */
    public function cashMovement(PosShift $shift, string $type, float $amount, string $reason, ?string $reference = null, ?string $notes = null): CashMovement
    {
        if ($shift->status !== 'open') throw new BusinessRuleException('This shift is closed.');
        if (! in_array($type, CashMovement::MANUAL, true)) throw new BusinessRuleException('Invalid movement type.');
        if ($type === 'adjustment' ? abs($amount) < 0.01 : $amount <= 0) throw new BusinessRuleException('Amount must be greater than zero.');

        $sign = CashMovement::TYPES[$type][1];
        $signed = round($sign === 0 ? $amount : $sign * abs($amount), 2);
        if ($signed < 0 && abs($signed) - $shift->expectedCash() > 0.009) {
            throw new BusinessRuleException('Not enough cash in the drawer ('.money($shift->expectedCash()).').');
        }
        $m = CashMovement::create(['pos_shift_id' => $shift->id, 'type' => $type, 'amount' => $signed, 'reason' => $reason,
            'reference' => $reference, 'notes' => $notes, 'user_id' => auth()->id()]);
        AuditService::log('pos', 'drawer_'.$type, $shift, CashMovement::TYPES[$type][0].' '.money($signed).' — '.$reason.($reference ? ' ['.$reference.']' : ''),
            null, ['movement_id' => $m->id, 'amount' => $signed, 'reference' => $reference]);
        if ($type === 'adjustment') {
            NotificationService::notify('pos.adjustment', 'Drawer adjustment '.money($signed), $shift->user->name.' · '.$shift->outlet->name.' · '.$reason,
                route('pos.shifts.show', $shift), 'warning', 'pos.shift_review');
        }
        return $m;
    }

    /** @param array<int|string,int> $denominations note/coin value => count (optional; must add up to $counted) */
    public function close(PosShift $shift, float $counted, ?string $notes = null, array $denominations = []): PosShift
    {
        if ($shift->status !== 'open') throw new BusinessRuleException('This shift is already closed.');
        $denominations = array_filter(array_map('intval', $denominations), fn ($n) => $n > 0);
        if ($denominations) {
            $sum = round(array_sum(array_map(fn ($value, $n) => (float) $value * $n, array_keys($denominations), $denominations)), 2);
            if (abs($sum - $counted) > 0.009) {
                throw new BusinessRuleException('The denomination count ('.money($sum).') does not match the counted cash ('.money($counted).').');
            }
        }
        DB::transaction(function () use ($shift, $counted, $notes, $denominations) {
            $shift = PosShift::whereKey($shift->id)->lockForUpdate()->first();
            $expected = $shift->expectedCash();
            $variance = round($counted - $expected, 2);
            $shift->update(['closed_at' => now(), 'expected_cash' => $expected, 'counted_cash' => $counted, 'variance' => $variance,
                'denominations' => $denominations ?: null, 'status' => 'closed', 'closed_by' => auth()->id(), 'review_status' => 'pending',
                'notes' => trim(($shift->notes ?? '')."\n".($notes ?? '')) ?: null]);
            $shift->update(['closing_report' => $this->report($shift->fresh())]);
            AuditService::log('pos', 'shift_closed', $shift, 'Expected '.money($expected).', counted '.money($counted).', variance '.money($variance));
        });
        $shift = $shift->fresh();
        if (abs((float) $shift->variance) >= 1) {
            NotificationService::notify('pos.variance', 'Cash variance '.money($shift->variance).' at shift close', $shift->user->name.' · '.$shift->outlet->name,
                route('pos.shifts.show', $shift), 'warning', 'pos.reports');
        }
        NotificationService::notify('pos.shift_closed', 'Shift closed: '.$shift->user->name, $shift->outlet->name.' · awaiting review', route('pos.shifts.show', $shift), 'info', 'pos.shift_review');
        return $shift;
    }

    public function review(PosShift $shift, string $status, ?string $notes = null): PosShift
    {
        if ($shift->status !== 'closed') throw new BusinessRuleException('Only closed shifts can be reviewed.');
        if (! in_array($status, ['approved', 'flagged'], true)) throw new BusinessRuleException('Invalid review status.');
        $shift->update(['review_status' => $status, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'review_notes' => $notes]);
        AuditService::log('pos', 'shift_'.$status, $shift, $notes);
        return $shift;
    }

    /** Reopen a closed shift (authorised users only — checked by the controller). The earlier count stays in the audit log. */
    public function reopen(PosShift $shift, string $reason): PosShift
    {
        if ($shift->status !== 'closed') throw new BusinessRuleException('Only closed shifts can be reopened.');
        if ($shift->closed_at && $shift->closed_at->lt(now()->subDays(3))) throw new BusinessRuleException('Shifts older than 3 days cannot be reopened; post an adjustment instead.');
        if (PosShift::where('user_id', $shift->user_id)->where('outlet_id', $shift->outlet_id)->where('status', 'open')->exists()) {
            throw new BusinessRuleException($shift->user->name.' already has an open shift at this outlet.');
        }
        $old = $shift->only(['expected_cash', 'counted_cash', 'variance', 'closed_at', 'review_status']);
        $shift->update(['status' => 'open', 'closed_at' => null, 'expected_cash' => null, 'counted_cash' => null, 'variance' => null, 'denominations' => null,
            'closing_report' => null, 'review_status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null,
            'reopened_by' => auth()->id(), 'reopened_at' => now(), 'reopen_reason' => $reason]);
        AuditService::log('pos', 'shift_reopened', $shift, $reason, $old, ['status' => 'open']);
        NotificationService::notify('pos.shift_reopened', 'Shift reopened: '.$shift->user->name, $reason, route('pos.shifts.show', $shift), 'warning', 'pos.reports');
        return $shift;
    }

    /** X / Z report figures, from the payments and drawer movements of the shift. */
    public function report(PosShift $shift): array
    {
        if ($shift->status === 'closed' && is_array($shift->closing_report) && $shift->closing_report) {
            return $shift->closing_report;
        }
        $payments = $shift->payments()->get(['method', 'type', 'amount']);
        $moves = $shift->movements()->get(['type', 'amount'])->groupBy('type')->map(fn ($g) => round((float) $g->sum('amount'), 2));
        $net = fn (string $m) => round((float) $payments->where('method', $m)->sum('amount'), 2);           // payments − refunds
        $gross = fn (string $m) => round((float) $payments->where('method', $m)->where('type', 'payment')->sum('amount'), 2);
        $orders = PosOrder::where('pos_shift_id', $shift->id)->whereIn('status', ['paid', 'charged_to_room', 'refunded'])->get(['subtotal', 'discount_amount', 'service_charge', 'tax_amount', 'total']);
        $byMethod = collect(PosPayment::METHODS)->mapWithKeys(fn ($l, $m) => [$m => $net($m)])->filter(fn ($v) => abs($v) > 0.009);
        $expected = $shift->status === 'closed' ? (float) $shift->expected_cash : $shift->expectedCash();

        return [
            'orders' => $orders->count(),
            'gross' => round((float) $orders->sum('subtotal'), 2),
            'discounts' => round((float) $orders->sum('discount_amount'), 2),
            'service' => round((float) $orders->sum('service_charge'), 2),
            'tax' => round((float) $orders->sum('tax_amount'), 2),
            'net_sales' => round((float) $orders->sum('total'), 2),
            'refunds' => round(abs((float) $payments->where('type', 'refund')->sum('amount')), 2),
            'by_method' => $byMethod->all(),
            'movements' => $moves->all(),
            // Drawer reconciliation
            'opening_float' => round((float) $shift->opening_float, 2),
            'cash_sales' => $gross('cash'),
            'cash_in' => (float) ($moves['cash_in'] ?? 0),
            'cash_out' => abs((float) ($moves['cash_out'] ?? 0)),
            'cash_refunds' => abs((float) ($moves['refund'] ?? 0)),
            'deposits' => abs((float) ($moves['deposit'] ?? 0)),
            'adjustments' => (float) ($moves['adjustment'] ?? 0),
            'expected_cash' => round($expected, 2),
            'counted_cash' => $shift->counted_cash !== null ? (float) $shift->counted_cash : null,
            'variance' => $shift->variance !== null ? (float) $shift->variance : null,
            // Non-cash tenders
            'card_total' => $net('card'),
            'bank_total' => $net('bank_transfer'),
            'digital_total' => round($net('digital') + $net('online'), 2),
            'room_charges' => $net('room_charge'),
            'grand_total' => round((float) $payments->sum('amount'), 2),
        ];
    }

    /**
     * "Today" cash & bank summary. Users without pos.reports see only their own shifts.
     * @return array{date: Carbon, totals: array, by_method: array, shifts: Collection, drawer: array}
     */
    public function today(User $viewer, ?Carbon $date = null, ?int $outletId = null): array
    {
        $date = ($date ?? now())->copy()->startOfDay();
        $all = $viewer->hasPermission('pos.reports');

        $payments = PosPayment::whereBetween('created_at', [$date, $date->copy()->endOfDay()])
            ->when(! $all, fn ($q) => $q->whereHas('shift', fn ($s) => $s->where('user_id', $viewer->id)))
            ->when($outletId, fn ($q) => $q->whereHas('order', fn ($o) => $o->where('outlet_id', $outletId)))
            ->get(['method', 'type', 'amount']);
        $sum = fn (?string $method = null, ?string $type = null) => round((float) $payments
            ->when($method, fn ($c) => $c->where('method', $method))->when($type, fn ($c) => $c->where('type', $type))->sum('amount'), 2);

        $shifts = PosShift::with(['outlet', 'user'])
            ->where(fn ($q) => $q->where('status', 'open')->orWhereBetween('opened_at', [$date, $date->copy()->endOfDay()])->orWhereBetween('closed_at', [$date, $date->copy()->endOfDay()]))
            ->when(! $all, fn ($q) => $q->where('user_id', $viewer->id))
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->orderByRaw("status = 'open' desc")->latest('opened_at')->get()
            ->map(fn (PosShift $s) => ['shift' => $s, 'r' => $this->report($s)]);

        $deposits = abs((float) CashMovement::where('type', 'deposit')->whereBetween('created_at', [$date, $date->copy()->endOfDay()])
            ->whereIn('pos_shift_id', $shifts->pluck('shift.id'))->sum('amount'));
        $open = $shifts->filter(fn ($x) => $x['shift']->status === 'open');

        return [
            'date' => $date,
            'scope' => $all ? 'all' : 'mine',
            'totals' => [
                'sales' => round($sum(null, 'payment'), 2),
                'cash' => $sum('cash'),
                'card' => $sum('card'),
                'bank' => $sum('bank_transfer'),
                'digital' => round($sum('digital') + $sum('online'), 2),
                'refunds' => abs($sum(null, 'refund')),
                'room_charges' => $sum('room_charge'),
                'total' => $sum(),
            ],
            'by_method' => collect(PosPayment::METHODS)->mapWithKeys(fn ($l, $m) => [$m => $sum($m)])->all(),
            'drawer' => [
                'in_drawer' => round($open->sum(fn ($x) => $x['r']['expected_cash']), 2),
                'open_shifts' => $open->count(),
                'expected_closed' => round($shifts->where('shift.status', 'closed')->sum(fn ($x) => (float) $x['shift']->expected_cash), 2),
                'counted_closed' => round($shifts->where('shift.status', 'closed')->sum(fn ($x) => (float) $x['shift']->counted_cash), 2),
                'variance' => round($shifts->where('shift.status', 'closed')->sum(fn ($x) => (float) $x['shift']->variance), 2),
                'deposits' => round($deposits, 2),
            ],
            'shifts' => $shifts,
        ];
    }
}
