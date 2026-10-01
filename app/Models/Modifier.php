<?php

namespace App\Models;

class Modifier extends BaseModel
{
    protected $casts = ['is_active' => 'boolean'];

    public function group() { return $this->belongsTo(ModifierGroup::class, 'modifier_group_id'); }
}
