<?php

namespace App\Models;

class ModifierGroup extends BaseModel
{
    public function modifiers() { return $this->hasMany(Modifier::class); }
    public function menuItems() { return $this->belongsToMany(MenuItem::class); }
}
