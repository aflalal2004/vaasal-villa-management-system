<?php

namespace App\Models;

class Commission extends BaseModel
{
    protected $attributes = ['status' => 'accrued'];

    protected $casts = ['settled_at' => 'datetime'];

    public function booking() { return $this->belongsTo(Booking::class); }
    public function operator() { return $this->belongsTo(TourOperator::class, 'tour_operator_id'); }
    public function channel() { return $this->belongsTo(Channel::class); }
}
