<?php

namespace App\Models;

class LeaveType extends BaseModel
{
    protected $casts = ['is_paid' => 'boolean'];
}
