<?php

namespace App\Models;

class LockBridge extends BaseModel
{
    protected $casts = ['is_active' => 'boolean', 'last_seen_at' => 'datetime'];
    protected $hidden = ['token_hash'];

    public function isOnline(): bool { return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(2)); }
}
