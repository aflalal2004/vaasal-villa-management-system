<?php

namespace App\Models;

class OperatorContract extends BaseModel
{
    protected $casts = ['valid_from' => 'date', 'valid_to' => 'date', 'is_active' => 'boolean'];

    public function operator() { return $this->belongsTo(TourOperator::class, 'tour_operator_id'); }
    public function rates() { return $this->hasMany(ContractRate::class); }

    public function netRateFor(int $villaTypeId): ?float
    {
        $r = $this->rates->firstWhere('villa_type_id', $villaTypeId);
        return $r ? (float) $r->net_rate : null;
    }
}
