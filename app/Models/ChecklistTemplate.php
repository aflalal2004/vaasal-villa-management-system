<?php

namespace App\Models;

class ChecklistTemplate extends BaseModel
{
    protected $casts = ['items' => 'array', 'is_active' => 'boolean'];
}
