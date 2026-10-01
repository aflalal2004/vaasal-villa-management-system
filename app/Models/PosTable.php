<?php

namespace App\Models;

class PosTable extends BaseModel
{
    protected $casts = ['is_active' => 'boolean'];

    public function outlet() { return $this->belongsTo(Outlet::class); }
    public function orders() { return $this->hasMany(PosOrder::class); }
    public function reservations() { return $this->hasMany(TableReservation::class); }
    public function openOrder() { return $this->hasOne(PosOrder::class)->whereIn('status', ['open', 'billed'])->latestOfMany(); }
}
