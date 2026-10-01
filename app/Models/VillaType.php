<?php

namespace App\Models;

class VillaType extends BaseModel
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $casts = ['is_active' => 'boolean', 'base_rate' => 'decimal:2', 'extra_adult_rate' => 'decimal:2', 'extra_child_rate' => 'decimal:2'];

    public function property() { return $this->belongsTo(Property::class); }
    public function villas() { return $this->hasMany(Villa::class); }
    public function facilities() { return $this->belongsToMany(Facility::class); }
    public function rates() { return $this->hasMany(Rate::class); }
    public function media() { return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order'); }

    public function coverUrl(): string
    {
        $m = $this->media->firstWhere('is_cover', true) ?? $this->media->first();
        return $m ? $m->url() : asset('assets/img/placeholder-villa.svg');
    }

    public function maxGuests(): int { return $this->max_adults + $this->max_children; }
}
