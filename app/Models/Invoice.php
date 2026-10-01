<?php

namespace App\Models;

class Invoice extends BaseModel
{
    protected $attributes = ['status' => 'issued', 'discount_total' => 0, 'tax_total' => 0, 'service_total' => 0];

    protected $casts = ['lines' => 'array', 'issued_at' => 'datetime', 'due_date' => 'date'];

    public const TYPES = ['invoice' => 'Tax Invoice', 'receipt' => 'Receipt', 'proforma' => 'Pro-forma Invoice', 'credit_note' => 'Credit Note', 'operator_invoice' => 'Invoice'];

    public function folio() { return $this->belongsTo(Folio::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
    public function operator() { return $this->belongsTo(TourOperator::class, 'tour_operator_id'); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function issuer() { return $this->belongsTo(User::class, 'issued_by'); }

    public function typeLabel(): string { return self::TYPES[$this->type] ?? ucfirst($this->type); }
}
