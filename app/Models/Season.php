<?php

namespace App\Models;

class Season extends BaseModel
{
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function rates() { return $this->hasMany(Rate::class); }
}
