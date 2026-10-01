<?php

namespace App\Models;

class KeyCard extends BaseModel
{
    protected $attributes = ['status' => 'available', 'type' => 'guest'];

    public function property() { return $this->belongsTo(Property::class); }
    public function assignments() { return $this->hasMany(KeyCardAssignment::class)->latest('id'); }
    public function activeAssignment() { return $this->hasOne(KeyCardAssignment::class)->where('status', 'active')->latestOfMany(); }
    public function accessLogs() { return $this->hasMany(AccessLog::class); }
}
