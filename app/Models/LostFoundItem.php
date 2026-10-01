<?php

namespace App\Models;

class LostFoundItem extends BaseModel
{
    protected $attributes = ['status' => 'stored', 'category' => 'other'];

    protected $casts = ['found_at' => 'datetime', 'resolved_at' => 'datetime'];

    public const STATUSES = ['stored' => 'Stored', 'claimed' => 'Claimed', 'returned' => 'Returned', 'disposed' => 'Disposed'];

    public function villa() { return $this->belongsTo(Villa::class); }
    public function finder() { return $this->belongsTo(Employee::class, 'found_by'); }
    public function guest() { return $this->belongsTo(Guest::class); }
}
