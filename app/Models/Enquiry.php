<?php

namespace App\Models;

class Enquiry extends BaseModel
{
    protected $attributes = ['status' => 'new'];

    protected $table = 'enquiries';
    protected $casts = ['arrival' => 'date', 'departure' => 'date'];

    public function handler() { return $this->belongsTo(User::class, 'handled_by'); }
}
