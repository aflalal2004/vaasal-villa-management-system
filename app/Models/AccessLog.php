<?php

namespace App\Models;

class AccessLog extends BaseModel
{
    public const UPDATED_AT = null;
    protected $casts = ['details' => 'array', 'occurred_at' => 'datetime', 'granted' => 'boolean'];

    public function villa() { return $this->belongsTo(Villa::class); }
    public function card() { return $this->belongsTo(KeyCard::class, 'key_card_id'); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function device() { return $this->belongsTo(AttendanceDevice::class, 'device_id'); }
}
