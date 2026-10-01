<?php

namespace App\Models;

class VillaBlock extends BaseModel
{
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function villa() { return $this->belongsTo(Villa::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
