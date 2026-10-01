<?php

namespace App\Models;

class Role extends BaseModel
{
    protected $casts = ['is_system' => 'boolean'];

    public function permissions() { return $this->belongsToMany(Permission::class); }
    public function users() { return $this->belongsToMany(User::class); }
}
