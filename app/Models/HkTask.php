<?php

namespace App\Models;

class HkTask extends BaseModel
{
    protected $attributes = ['status' => 'pending', 'priority' => 'normal', 'type' => 'departure', 'rejection_count' => 0, 'paused_minutes' => 0];

    protected $casts = ['scheduled_date' => 'date', 'accepted_at' => 'datetime', 'started_at' => 'datetime', 'paused_at' => 'datetime',
        'finished_at' => 'datetime', 'inspected_at' => 'datetime'];

    public const TYPES = ['departure' => 'Departure clean', 'stayover' => 'Stay-over service', 'adhoc' => 'Ad-hoc', 'deep' => 'Deep clean'];
    public const STATUSES = ['pending' => 'Pending', 'in_progress' => 'Cleaning', 'paused' => 'Paused', 'inspection' => 'Awaiting inspection',
        'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    /** Statuses in which a task is still open work. */
    public const OPEN = ['pending', 'in_progress', 'paused', 'inspection'];

    public function villa() { return $this->belongsTo(Villa::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
    public function assignee() { return $this->belongsTo(Employee::class, 'assigned_to'); }
    public function inspector() { return $this->belongsTo(User::class, 'inspected_by'); }
    public function items() { return $this->hasMany(HkTaskItem::class)->orderBy('sort_order'); }
    public function linen() { return $this->hasMany(LinenMovement::class); }

    /** Working minutes from start to finish, excluding pauses. */
    public function durationMinutes(): ?int
    {
        return ($this->started_at && $this->finished_at)
            ? max(0, (int) $this->started_at->diffInMinutes($this->finished_at) - (int) $this->paused_minutes) : null;
    }
}
