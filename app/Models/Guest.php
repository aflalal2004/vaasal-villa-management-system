<?php

namespace App\Models;

class Guest extends BaseModel
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $attributes = ['is_vip' => false, 'is_blacklisted' => false];

    protected $casts = ['date_of_birth' => 'date', 'id_expiry' => 'date', 'id_number' => 'encrypted',
        'is_vip' => 'boolean', 'is_blacklisted' => 'boolean', 'marketing_consent' => 'boolean'];

    public function bookings() { return $this->hasMany(Booking::class); }

    public function fullName(): string { return trim(($this->title ? $this->title.' ' : '').$this->first_name.' '.$this->last_name); }

    public function maskedId(): ?string
    {
        if (! $this->id_number) return null;
        return str_repeat('•', max(0, strlen($this->id_number) - 4)).substr($this->id_number, -4);
    }
}
