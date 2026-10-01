<?php

namespace App\Models;

class KeyCardAssignment extends BaseModel
{
    protected $attributes = ['status' => 'pending', 'issue_type' => 'new', 'access_level' => 'guest'];

    protected $casts = ['valid_from' => 'datetime', 'valid_to' => 'datetime', 'revoked_at' => 'datetime', 'lock_refs' => 'array'];

    public function card() { return $this->belongsTo(KeyCard::class, 'key_card_id'); }
    public function booking() { return $this->belongsTo(Booking::class); }
    public function bookingVilla() { return $this->belongsTo(BookingVilla::class); }
    public function villa() { return $this->belongsTo(Villa::class); }
    public function guest() { return $this->belongsTo(Guest::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function issuer() { return $this->belongsTo(User::class, 'issued_by'); }
    public function jobs() { return $this->hasMany(LockJob::class); }

    public function holderName(): string
    {
        return $this->guest?->fullName() ?? $this->employee?->fullName() ?? '—';
    }
}
