<?php

namespace App\Models;

class PosPayment extends BaseModel
{
    /** POS tender types. "Grouped" totals on shift and Today reports use GROUPS. */
    public const METHODS = [
        'cash' => 'Cash',
        'card' => 'Card',
        'bank_transfer' => 'Bank transfer',
        'digital' => 'Digital wallet / QR',
        'online' => 'Online payment',
        'room_charge' => 'Charge to villa',
    ];

    /** Methods that belong to a cashier's drawer session and therefore need an open shift. */
    public const SHIFT_METHODS = ['cash', 'card', 'bank_transfer', 'digital'];

    public const ICONS = ['cash' => 'cash', 'card' => 'card', 'bank_transfer' => 'bank', 'digital' => 'qr', 'online' => 'globe', 'room_charge' => 'villa'];

    public function order() { return $this->belongsTo(PosOrder::class, 'pos_order_id'); }
    public function shift() { return $this->belongsTo(PosShift::class, 'pos_shift_id'); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }

    public static function label(string $method): string
    {
        return self::METHODS[$method] ?? ucfirst(str_replace('_', ' ', $method));
    }
}
