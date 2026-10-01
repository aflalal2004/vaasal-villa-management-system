<?php

namespace App\Models;

class StockItem extends BaseModel
{
    protected $attributes = ['current_qty' => 0, 'reorder_level' => 0, 'unit_cost' => 0, 'is_active' => true];

    protected $casts = ['is_active' => 'boolean', 'current_qty' => 'float', 'reorder_level' => 'float'];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function movements() { return $this->hasMany(StockMovement::class)->latest('id'); }

    public function isLow(): bool { return $this->current_qty <= $this->reorder_level; }
    public function scopeLow($q) { return $q->whereColumn('current_qty', '<=', 'reorder_level')->where('is_active', true); }
}
