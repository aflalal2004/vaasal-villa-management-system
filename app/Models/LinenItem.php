<?php

namespace App\Models;

class LinenItem extends BaseModel
{
    public function movements() { return $this->hasMany(LinenMovement::class); }
}
