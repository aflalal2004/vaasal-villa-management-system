<?php

namespace App\Models;

class Property extends BaseModel
{
    protected $casts = ['settings' => 'array'];

    public static function current(): self
    {
        if (! app()->bound('vv.property')) {
            app()->instance('vv.property', static::query()->firstOrFail());
        }
        return app('vv.property');
    }

    public function villas() { return $this->hasMany(Villa::class); }
    public function departments() { return $this->hasMany(Department::class); }
}
