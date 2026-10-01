<?php

namespace App\Models;

class Payment extends BaseModel
{
    protected $attributes = ['status' => 'completed', 'type' => 'payment'];

    protected $casts = ['paid_at' => 'datetime'];

    public const METHODS = ['cash' => 'Cash', 'card' => 'Card (terminal)', 'bank_transfer' => 'Bank transfer', 'online' => 'Online (gateway)',
        'cheque' => 'Cheque', 'city_ledger' => 'City ledger transfer'];

    public function folio() { return $this->belongsTo(Folio::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
    public function operator() { return $this->belongsTo(TourOperator::class, 'tour_operator_id'); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }

    public function methodLabel(): string { return self::METHODS[$this->method] ?? ucfirst($this->method); }
}
