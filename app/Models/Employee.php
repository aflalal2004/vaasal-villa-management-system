<?php

namespace App\Models;

class Employee extends BaseModel
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $attributes = ['status' => 'active', 'employment_type' => 'full_time'];

    protected $casts = ['date_of_birth' => 'date', 'hire_date' => 'date', 'termination_date' => 'date', 'national_id' => 'encrypted'];
    protected $hidden = ['attendance_pin', 'national_id'];

    public function property() { return $this->belongsTo(Property::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function jobRole() { return $this->belongsTo(JobRole::class); }
    public function attendance() { return $this->hasMany(AttendanceRecord::class); }
    public function leaveRequests() { return $this->hasMany(LeaveRequest::class); }
    public function roster() { return $this->hasMany(RosterEntry::class); }
    public function hkTasks() { return $this->hasMany(HkTask::class, 'assigned_to'); }

    public function fullName(): string { return $this->first_name.' '.$this->last_name; }

    public function photoUrl(): ?string { return $this->photo_path ? asset('storage/'.$this->photo_path) : null; }
}
