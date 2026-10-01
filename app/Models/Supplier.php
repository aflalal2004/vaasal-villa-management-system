<?php

namespace App\Models;

class Supplier extends BaseModel
{
    protected $casts = ['is_active' => 'boolean'];

    public function stockItems() { return $this->hasMany(StockItem::class); }
    public function movements() { return $this->hasMany(StockMovement::class); }
}
