<?php

namespace App\Models;

/**
 * A reader that posts RFID / QR / PIN / biometric scans: time clocks (purpose attendance), door readers
 * (purpose access) or both. Authenticates with a bearer token whose SHA-256 hash is stored here.
 */
class AttendanceDevice extends BaseModel
{
    public const TYPES = ['rfid' => 'RFID reader', 'kiosk' => 'PIN kiosk', 'qr' => 'QR scanner', 'biometric' => 'Biometric'];
    public const PURPOSES = ['attendance' => 'Time clock (attendance)', 'access' => 'Door / zone access', 'both' => 'Access + attendance'];

    protected $attributes = ['purpose' => 'attendance', 'mode' => 'hardware', 'is_active' => true];
    protected $casts = ['is_active' => 'boolean', 'last_seen_at' => 'datetime'];
    protected $hidden = ['api_token_hash'];

    public function villa() { return $this->belongsTo(Villa::class); }
    public function accessLogs() { return $this->hasMany(AccessLog::class, 'device_id'); }

    public function handlesAccess(): bool { return in_array($this->purpose, ['access', 'both'], true); }
    public function handlesAttendance(): bool { return in_array($this->purpose, ['attendance', 'both'], true); }

    public function doorLabel(): string
    {
        return collect([$this->villa ? 'Villa '.$this->villa->code : null, $this->zone])->filter()->implode(' · ') ?: ($this->location ?: '—');
    }
}
