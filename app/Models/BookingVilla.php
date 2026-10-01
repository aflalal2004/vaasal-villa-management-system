<?php

namespace App\Models;

class BookingVilla extends BaseModel
{
    protected $attributes = ['status' => 'active'];

    protected $casts = ['arrival' => 'date', 'departure' => 'date', 'nightly_rates' => 'array'];

    public function booking() { return $this->belongsTo(Booking::class); }
    public function villa() { return $this->belongsTo(Villa::class); }
    public function villaType() { return $this->belongsTo(VillaType::class); }
    public function guests() { return $this->hasMany(StayGuest::class); }
    public function nights() { return $this->hasMany(InventoryNight::class); }
}
