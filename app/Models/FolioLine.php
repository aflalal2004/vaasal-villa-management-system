<?php

namespace App\Models;

class FolioLine extends BaseModel
{
    protected $casts = ['business_date' => 'date', 'is_reversed' => 'boolean'];

    public function folio() { return $this->belongsTo(Folio::class); }
    public function chargeItem() { return $this->belongsTo(ChargeItem::class); }
    public function poster() { return $this->belongsTo(User::class, 'posted_by'); }
    public function reverses() { return $this->belongsTo(FolioLine::class, 'reverses_id'); }

    public function departmentLabel(): string { return config('vaasal.departments.'.$this->department, ucfirst($this->department)); }
}
