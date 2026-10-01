<?php

namespace App\Models;

class MenuItem extends BaseModel
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $casts = ['is_available' => 'boolean', 'is_active' => 'boolean', 'price' => 'decimal:2', 'cost' => 'decimal:2'];

    public function category() { return $this->belongsTo(MenuCategory::class, 'menu_category_id'); }
    public function modifierGroups() { return $this->belongsToMany(ModifierGroup::class); }
    public function recipe() { return $this->hasMany(Recipe::class); }

    public function effectiveStation(): string { return $this->station ?: ($this->category->station ?? 'kitchen'); }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) return null;
        return str_starts_with($this->image_path, 'http') ? $this->image_path : asset('storage/'.$this->image_path);
    }
}
