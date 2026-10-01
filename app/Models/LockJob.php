<?php

namespace App\Models;

class LockJob extends BaseModel
{
    protected $attributes = ['status' => 'pending', 'attempts' => 0];

    protected $casts = ['payload' => 'array', 'result' => 'array', 'picked_at' => 'datetime', 'completed_at' => 'datetime'];

    public function assignment() { return $this->belongsTo(KeyCardAssignment::class, 'key_card_assignment_id'); }
    public function bridge() { return $this->belongsTo(LockBridge::class, 'lock_bridge_id'); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
}
