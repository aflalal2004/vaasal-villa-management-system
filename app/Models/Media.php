<?php

namespace App\Models;

class Media extends BaseModel
{
    protected $table = 'media';
    protected $casts = ['is_cover' => 'boolean'];

    public function mediable() { return $this->morphTo(); }

    public function url(): string
    {
        return str_starts_with($this->path, 'http') ? $this->path : asset('storage/'.$this->path);
    }
}
