<?php

namespace App\Models;

class Department extends BaseModel
{
    public function property() { return $this->belongsTo(Property::class); }
    public function jobRoles() { return $this->hasMany(JobRole::class); }
    public function employees() { return $this->hasMany(Employee::class); }
}
