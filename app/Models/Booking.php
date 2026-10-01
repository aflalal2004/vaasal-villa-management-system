<?php

namespace App\Models;

class Booking extends BaseModel
{
    protected $attributes = ['adults' => 1, 'children' => 0, 'version' => 1, 'room_total' => 0, 'discount_total' => 0, 'tax_total' => 0, 'service_total' => 0, 'grand_total' => 0, 'deposit_due' => 0, 'commission_pct' => 0, 'commission_amount' => 0, 'cancellation_fee' => 0];

    protected $casts = [
        'arrival' => 'date', 'departure' => 'date', 'hold_expires_at' => 'datetime', 'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime',
    ];

    public const STATUSES = ['hold' => 'On hold', 'tentative' => 'Tentative', 'confirmed' => 'Confirmed', 'checked_in' => 'Checked in',
        'checked_out' => 'Checked out', 'cancelled' => 'Cancelled', 'no_show' => 'No-show', 'expired' => 'Expired'];
    public const SOURCES = ['website' => 'Website', 'admin' => 'Admin', 'walk_in' => 'Walk-in', 'phone' => 'Phone', 'email' => 'Email',
        'tour_operator' => 'Tour operator', 'ota' => 'OTA'];
    public const LIVE_STATUSES = ['hold', 'tentative', 'confirmed', 'checked_in'];

    public function property() { return $this->belongsTo(Property::class); }
    public function channel() { return $this->belongsTo(Channel::class); }
    public function guest() { return $this->belongsTo(Guest::class); }
    public function operator() { return $this->belongsTo(TourOperator::class, 'tour_operator_id'); }
    public function contract() { return $this->belongsTo(OperatorContract::class, 'operator_contract_id'); }
    public function ratePlan() { return $this->belongsTo(RatePlan::class); }
    public function offer() { return $this->belongsTo(Offer::class); }
    public function villas() { return $this->hasMany(BookingVilla::class); }
    public function activeVillas() { return $this->hasMany(BookingVilla::class)->where('status', 'active'); }
    public function folios() { return $this->hasMany(Folio::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function events() { return $this->hasMany(BookingEvent::class)->latest('id'); }
    public function cardAssignments() { return $this->hasMany(KeyCardAssignment::class); }
    public function commission() { return $this->hasOne(Commission::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function nights(): int { return $this->arrival->diffInDays($this->departure); }
    public function statusLabel(): string { return self::STATUSES[$this->status] ?? ucfirst($this->status); }
    public function sourceLabel(): string { return self::SOURCES[$this->source] ?? ucfirst($this->source); }
    public function isLive(): bool { return in_array($this->status, self::LIVE_STATUSES, true); }

    public function mainFolio(): ?Folio { return $this->folios->firstWhere('payer_type', 'guest') ?? $this->folios->first(); }

    public function paidTotal(): float { return (float) $this->payments()->where('status', 'completed')->sum('amount'); }

    public function scopeLive($q) { return $q->whereIn('status', self::LIVE_STATUSES); }
}
