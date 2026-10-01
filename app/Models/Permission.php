<?php

namespace App\Models;

class Permission extends BaseModel
{
    public $timestamps = false;

    public function roles() { return $this->belongsToMany(Role::class); }
}
