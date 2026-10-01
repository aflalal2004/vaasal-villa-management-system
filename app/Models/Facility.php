<?php

namespace App\Models;

class Facility extends BaseModel
{
    public function villaTypes() { return $this->belongsToMany(VillaType::class); }
}
