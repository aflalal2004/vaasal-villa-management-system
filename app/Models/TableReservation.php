<?php

namespace App\Models;

/** Restaurant table reservation (Restaurant POS). Optional booking link = in-house hotel guest. */
class TableReservation extends BaseModel
{
    public const STATUSES = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'seated' => 'Seated', 'completed' => 'Completed',
        'cancelled' => 'Cancelled', 'no_show' => 'No-show'];
    /** Reservations that still hold a table. */
    public const ACTIVE = ['pending', 'confirmed', 'seated'];

    protected $attributes = ['status' => 'pending', 'party_size' => 2, 'duration_minutes' => 90];
    protected $casts = ['reserved_for' => 'datetime'];

    public function outlet() { return $this->belongsTo(Outlet::class); }
    public function table() { return $this->belongsTo(PosTable::class, 'pos_table_id'); }
    public function booking() { return $this->belongsTo(Booking::class); }
    public function order() { return $this->belongsTo(PosOrder::class, 'pos_order_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function endsAt(): \Illuminate\Support\Carbon { return $this->reserved_for->copy()->addMinutes($this->duration_minutes); }
}
