<?php

namespace App\Models;

class PosOrder extends BaseModel
{
    protected $attributes = ['status' => 'open', 'type' => 'dine_in', 'covers' => 1, 'subtotal' => 0, 'discount_value' => 0, 'discount_amount' => 0, 'service_charge' => 0, 'tax_amount' => 0, 'total' => 0, 'paid_amount' => 0, 'refunded_amount' => 0, 'version' => 1];

    protected $casts = ['opened_at' => 'datetime', 'billed_at' => 'datetime', 'closed_at' => 'datetime'];

    public const STATUSES = ['open' => 'Open', 'billed' => 'Bill printed', 'paid' => 'Paid', 'charged_to_room' => 'Charged to villa',
        'void' => 'Void', 'merged' => 'Merged', 'refunded' => 'Refunded'];

    public function outlet() { return $this->belongsTo(Outlet::class); }
    public function table() { return $this->belongsTo(PosTable::class, 'pos_table_id'); }
    public function waiter() { return $this->belongsTo(User::class, 'waiter_id'); }
    public function cashier() { return $this->belongsTo(User::class, 'cashier_id'); }
    public function items() { return $this->hasMany(PosOrderItem::class); }
    public function liveItems() { return $this->hasMany(PosOrderItem::class)->where('status', '!=', 'void'); }
    public function kots() { return $this->hasMany(Kot::class); }
    public function payments() { return $this->hasMany(PosPayment::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
    public function villa() { return $this->belongsTo(Villa::class); }

    public function isEditable(): bool { return in_array($this->status, ['open', 'billed'], true); }
    public function balance(): float { return round((float) $this->total - (float) $this->paid_amount, 2); }
    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }
}
