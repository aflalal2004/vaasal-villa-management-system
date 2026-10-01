<?php

namespace App\Models;

class BookingEvent extends BaseModel
{
    public const UPDATED_AT = null;
    protected $casts = ['data' => 'array', 'created_at' => 'datetime'];

    public function booking() { return $this->belongsTo(Booking::class); }
    public function user() { return $this->belongsTo(User::class); }
}
