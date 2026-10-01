<?php

namespace App\Models;

class StockMovement extends BaseModel
{
    public function item() { return $this->belongsTo(StockItem::class, 'stock_item_id'); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function user() { return $this->belongsTo(User::class); }
}
