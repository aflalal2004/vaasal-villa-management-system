<?php

namespace App\Models;

class GalleryItem extends BaseModel
{
    protected $casts = ['is_active' => 'boolean'];

    public function url(): string { return str_starts_with($this->path, 'http') ? $this->path : asset('storage/'.$this->path); }

    public function thumbUrl(): string
    {
        $t = $this->thumbnail ?: $this->path;
        return str_starts_with($t, 'http') ? $t : asset('storage/'.$t);
    }
}
