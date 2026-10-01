<?php

namespace App\Models;

class Channel extends BaseModel
{
    protected $casts = ['is_active' => 'boolean'];

    public function bookings() { return $this->hasMany(Booking::class); }
    public function mappings() { return $this->hasMany(ChannelMapping::class); }

    public static function byCode(string $code): self { return static::where('code', $code)->firstOrFail(); }
}
