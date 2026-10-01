<?php

namespace App\Models;

class MenuCategory extends BaseModel
{
    protected $casts = ['is_active' => 'boolean'];

    public function outlet() { return $this->belongsTo(Outlet::class); }
    public function items() { return $this->hasMany(MenuItem::class)->orderBy('sort_order')->orderBy('name'); }
}
