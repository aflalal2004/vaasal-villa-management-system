<?php

namespace App\Models;

class PaymentIntent extends BaseModel
{
    protected $attributes = ['status' => 'pending', 'purpose' => 'deposit'];

    protected $casts = ['payload' => 'array', 'expires_at' => 'datetime', 'paid_at' => 'datetime'];

    public function booking() { return $this->belongsTo(Booking::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
}
