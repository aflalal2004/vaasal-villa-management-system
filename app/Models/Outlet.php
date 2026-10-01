<?php

namespace App\Models;

class Outlet extends BaseModel
{
    protected $casts = ['is_active' => 'boolean'];

    public function tables() { return $this->hasMany(PosTable::class)->orderBy('sort_order'); }
    public function categories() { return $this->hasMany(MenuCategory::class); }
    public function orders() { return $this->hasMany(PosOrder::class); }
    public function shifts() { return $this->hasMany(PosShift::class); }
}
