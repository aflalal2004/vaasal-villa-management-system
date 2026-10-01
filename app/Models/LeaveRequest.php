<?php

namespace App\Models;

class LeaveRequest extends BaseModel
{
    protected $attributes = ['status' => 'pending'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'approved_at' => 'datetime'];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function type() { return $this->belongsTo(LeaveType::class, 'leave_type_id'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
