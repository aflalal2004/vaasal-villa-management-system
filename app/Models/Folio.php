<?php

namespace App\Models;

class Folio extends BaseModel
{
    protected $attributes = ['status' => 'open', 'payer_type' => 'guest'];

    protected $casts = ['closed_at' => 'datetime'];

    public function booking() { return $this->belongsTo(Booking::class); }
    public function guest() { return $this->belongsTo(Guest::class); }
    public function operator() { return $this->belongsTo(TourOperator::class, 'tour_operator_id'); }
    public function lines() { return $this->hasMany(FolioLine::class)->orderBy('business_date')->orderBy('id'); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }

    public function chargesTotal(): float { return round((float) $this->lines()->sum('total'), 2); }
    public function paymentsTotal(): float { return round((float) $this->payments()->where('status', 'completed')->sum('amount'), 2); }
    public function balance(): float { return round($this->chargesTotal() - $this->paymentsTotal(), 2); }
    public function isOpen(): bool { return $this->status === 'open'; }

    public function payerName(): string
    {
        return $this->payer_type === 'operator' ? ($this->operator?->company_name ?? 'Tour operator') : ($this->guest?->fullName() ?? $this->booking->guest->fullName());
    }
}
