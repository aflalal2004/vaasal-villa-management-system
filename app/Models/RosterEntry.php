<?php

namespace App\Models;

class RosterEntry extends BaseModel
{
    protected $casts = ['work_date' => 'date'];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function shift() { return $this->belongsTo(Shift::class); }
}
