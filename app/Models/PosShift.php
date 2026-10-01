<?php

namespace App\Models;

class PosShift extends BaseModel
{
    protected $attributes = ['status' => 'open', 'opening_float' => 0, 'review_status' => 'pending'];

    protected $casts = [
        'opened_at' => 'datetime', 'closed_at' => 'datetime', 'reviewed_at' => 'datetime', 'reopened_at' => 'datetime',
        'denominations' => 'array', 'closing_report' => 'array',
    ];

    public function outlet() { return $this->belongsTo(Outlet::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function closer() { return $this->belongsTo(User::class, 'closed_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function reopener() { return $this->belongsTo(User::class, 'reopened_by'); }
    public function movements() { return $this->hasMany(CashMovement::class); }
    public function payments() { return $this->hasMany(PosPayment::class); }

    /** Cash that should physically be in the drawer: every movement is stored with its signed effect. */
    public function expectedCash(): float { return round((float) $this->movements()->sum('amount'), 2); }

    public function isOpen(): bool { return $this->status === 'open'; }
}
