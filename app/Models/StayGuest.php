<?php

namespace App\Models;

class StayGuest extends BaseModel
{
    protected $casts = ['date_of_birth' => 'date', 'passport_no' => 'encrypted', 'is_child' => 'boolean', 'is_primary' => 'boolean'];

    public function bookingVilla() { return $this->belongsTo(BookingVilla::class); }
    public function guest() { return $this->belongsTo(Guest::class); }
    public function fullName(): string { return $this->first_name.' '.$this->last_name; }
}
