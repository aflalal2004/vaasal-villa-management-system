<?php

namespace App\Models;

class JobRole extends BaseModel
{
    public function department() { return $this->belongsTo(Department::class); }
    public function defaultRole() { return $this->belongsTo(Role::class, 'default_role_id'); }
    public function employees() { return $this->hasMany(Employee::class); }
}
