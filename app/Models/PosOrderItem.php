<?php

namespace App\Models;

class PosOrderItem extends BaseModel
{
    protected $attributes = ['status' => 'pending', 'modifiers_total' => 0, 'station' => 'kitchen'];

    protected $casts = ['modifiers' => 'array', 'fired_at' => 'datetime'];

    public function order() { return $this->belongsTo(PosOrder::class, 'pos_order_id'); }
    public function menuItem() { return $this->belongsTo(MenuItem::class); }
    public function kot() { return $this->belongsTo(Kot::class); }
}
