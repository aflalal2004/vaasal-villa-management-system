<?php

namespace App\Models;

class Kot extends BaseModel
{
    protected $attributes = ['status' => 'new'];

    protected $casts = ['started_at' => 'datetime', 'ready_at' => 'datetime', 'served_at' => 'datetime'];

    public function order() { return $this->belongsTo(PosOrder::class, 'pos_order_id'); }
    public function items() { return $this->hasMany(PosOrderItem::class); }
}
