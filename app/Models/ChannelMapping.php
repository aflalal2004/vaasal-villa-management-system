<?php

namespace App\Models;

class ChannelMapping extends BaseModel
{
    public function channel() { return $this->belongsTo(Channel::class); }
    public function villaType() { return $this->belongsTo(VillaType::class); }
    public function ratePlan() { return $this->belongsTo(RatePlan::class); }
}
