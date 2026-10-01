<?php

namespace App\Models;

class LoginLog extends BaseModel
{
    public $timestamps = false;
    protected $casts = ['success' => 'boolean', 'created_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
