<?php

namespace App\Models;

/**
 * One line in a cashier's drawer. The signed `amount` is the effect on the physical cash in the drawer,
 * so expected drawer cash = SUM(amount) for the shift.
 */
class CashMovement extends BaseModel
{
    /** type => [label, sign applied to the entered amount (0 = entered sign kept), icon] */
    public const TYPES = [
        'float' => ['Opening float', 1, 'coins'],
        'sale' => ['Cash sale', 1, 'receipt'],
        'cash_in' => ['Cash in', 1, 'download'],
        'cash_out' => ['Cash out / paid-out', -1, 'upload'],
        'refund' => ['Cash refund', -1, 'refresh'],
        'deposit' => ['Bank deposit / safe drop', -1, 'bank'],
        'adjustment' => ['Adjustment', 0, 'settings'],
    ];

    /** Types a cashier or manager can record by hand (sales, refunds and float are system-generated). */
    public const MANUAL = ['cash_in', 'cash_out', 'deposit', 'adjustment'];

    public function shift() { return $this->belongsTo(PosShift::class, 'pos_shift_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function order() { return $this->belongsTo(PosOrder::class, 'pos_order_id'); }

    public function typeLabel(): string { return self::TYPES[$this->type][0] ?? label($this->type); }
    public function icon(): string { return self::TYPES[$this->type][2] ?? 'cash'; }
}
