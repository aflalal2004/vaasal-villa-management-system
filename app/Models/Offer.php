<?php

namespace App\Models;

class Offer extends BaseModel
{
    protected $casts = ['valid_from' => 'date', 'valid_to' => 'date', 'is_active' => 'boolean', 'is_featured' => 'boolean'];

    public function scopeLive($q)
    {
        $today = now()->toDateString();
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
            ->where(fn ($w) => $w->whereNull('valid_to')->orWhere('valid_to', '>=', $today));
    }

    public function imageUrl(): string
    {
        if (! $this->image) return asset('assets/img/placeholder-villa.svg');
        return str_starts_with($this->image, 'http') ? $this->image : asset('storage/'.$this->image);
    }

    public function label(): string
    {
        return $this->discount_type === 'percent' ? rtrim(rtrim($this->discount_value, '0'), '.').'% off' : money($this->discount_value).' off';
    }
}
