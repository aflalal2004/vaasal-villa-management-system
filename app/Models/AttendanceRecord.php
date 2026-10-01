<?php

namespace App\Models;

class AttendanceRecord extends BaseModel
{
    protected $attributes = ['status' => 'incomplete', 'worked_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'overtime_minutes' => 0];

    protected $casts = ['work_date' => 'date', 'clock_in' => 'datetime', 'clock_out' => 'datetime', 'original_values' => 'array'];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function shift() { return $this->belongsTo(Shift::class); }
    public function device() { return $this->belongsTo(AttendanceDevice::class); }
    public function corrector() { return $this->belongsTo(User::class, 'corrected_by'); }
}
