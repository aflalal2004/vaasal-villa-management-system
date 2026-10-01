<?php

namespace App\Models;

class InventoryNight extends BaseModel
{
    public const UPDATED_AT = null;
    protected $casts = ['stay_date' => 'date'];

    public function villa() { return $this->belongsTo(Villa::class); }
    public function bookingVilla() { return $this->belongsTo(BookingVilla::class); }
    public function block() { return $this->belongsTo(VillaBlock::class, 'villa_block_id'); }
}
