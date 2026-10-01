<?php

namespace App\Models;

class Recipe extends BaseModel
{
    public function menuItem() { return $this->belongsTo(MenuItem::class); }
    public function stockItem() { return $this->belongsTo(StockItem::class); }
}
