<?php

namespace App\Models;

class Rate extends BaseModel
{
    public function villaType() { return $this->belongsTo(VillaType::class); }
    public function season() { return $this->belongsTo(Season::class); }
}
