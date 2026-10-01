<?php

namespace App\Models;

class BookingConflict extends BaseModel
{
    protected $attributes = ['status' => 'open'];

    protected $casts = ['arrival' => 'date', 'departure' => 'date', 'payload' => 'array', 'resolved_at' => 'datetime'];

    public function channel() { return $this->belongsTo(Channel::class); }
    public function villaType() { return $this->belongsTo(VillaType::class); }
    public function villa() { return $this->belongsTo(Villa::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
    public function resolver() { return $this->belongsTo(User::class, 'resolved_by'); }
}
