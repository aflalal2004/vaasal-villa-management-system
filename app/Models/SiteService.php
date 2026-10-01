<?php

namespace App\Models;

class SiteService extends BaseModel
{
    protected $casts = ['is_active' => 'boolean'];

    public function chargeItem() { return $this->belongsTo(ChargeItem::class); }

    public function imageUrl(): string
    {
        if (! $this->image) return asset('assets/img/placeholder-villa.svg');
        return str_starts_with($this->image, 'http') ? $this->image : asset('storage/'.$this->image);
    }
}
