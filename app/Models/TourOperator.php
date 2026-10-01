<?php

namespace App\Models;

class TourOperator extends BaseModel
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $attributes = ['status' => 'pending', 'credit_limit' => 0, 'payment_terms_days' => 30];

    protected $casts = ['approved_at' => 'datetime', 'credit_limit' => 'decimal:2'];

    public function users() { return $this->hasMany(User::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function contracts() { return $this->hasMany(OperatorContract::class); }
    public function bookings() { return $this->hasMany(Booking::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function commissions() { return $this->hasMany(Commission::class); }

    public function activeContract(?string $date = null): ?OperatorContract
    {
        $date ??= now()->toDateString();
        return $this->contracts()->where('is_active', true)->where('valid_from', '<=', $date)->where('valid_to', '>=', $date)->latest('valid_from')->first();
    }

    /** Outstanding = unpaid balance on operator invoices. */
    public function outstanding(): float
    {
        return (float) $this->invoices()->whereIn('status', ['issued', 'partially_paid'])->sum('balance');
    }
}
