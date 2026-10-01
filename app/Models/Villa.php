<?php

namespace App\Models;

class Villa extends BaseModel
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $attributes = ['occupancy_status' => 'vacant', 'hk_status' => 'ready', 'maintenance_status' => 'ok', 'is_active' => true];

    protected $casts = ['is_active' => 'boolean', 'rate_override' => 'decimal:2'];

    public const HK_LABELS = ['dirty' => 'Dirty', 'cleaning' => 'Cleaning', 'inspection' => 'Inspection', 'clean' => 'Clean', 'ready' => 'Ready'];

    /** One easy status for boards and dashboards: key => [label, icon, badge tone]. */
    public const BOARD = [
        'available' => ['Available', 'check-circle', 'success'],
        'reserved' => ['Reserved', 'calendar', 'info'],
        'occupied' => ['Occupied', 'user', 'info'],
        'dirty' => ['Needs cleaning', 'broom', 'danger'],
        'cleaning' => ['Cleaning', 'sparkles', 'warning'],
        'inspection' => ['Awaiting inspection', 'eye', 'warning'],
        'inspected' => ['Clean / inspected', 'check', 'success'],
        'out_of_order' => ['Out of order', 'wrench', 'danger'],
    ];

    /**
     * AVAILABLE · RESERVED · OCCUPIED · DIRTY · CLEANING · INSPECTION · INSPECTED · OUT_OF_ORDER, derived from
     * occupancy_status, hk_status and maintenance_status. "Inspected" means approved but not yet sellable
     * (e.g. maintenance still open); "reserved" means ready with a confirmed arrival today.
     */
    public function boardStatus(bool $arrivingToday = false): string
    {
        return match (true) {
            $this->maintenance_status === 'out_of_order' || ! $this->is_active => 'out_of_order',
            $this->occupancy_status === 'occupied' => 'occupied',
            $this->hk_status === 'dirty' => 'dirty',
            $this->hk_status === 'cleaning' => 'cleaning',
            $this->hk_status === 'inspection' => 'inspection',
            $this->hk_status === 'clean' => 'inspected',
            $arrivingToday => 'reserved',
            default => 'available',
        };
    }

    /** Can a guest be checked in / sold for tonight right now? */
    public function isReadyNow(): bool
    {
        return $this->isSellable() && $this->occupancy_status !== 'occupied' && $this->hk_status === 'ready';
    }

    public function property() { return $this->belongsTo(Property::class); }
    public function type() { return $this->belongsTo(VillaType::class, 'villa_type_id'); }
    public function media() { return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order'); }
    public function bookingVillas() { return $this->hasMany(BookingVilla::class); }
    public function blocks() { return $this->hasMany(VillaBlock::class); }
    public function hkTasks() { return $this->hasMany(HkTask::class); }
    public function tickets() { return $this->hasMany(MaintenanceTicket::class); }
    public function nights() { return $this->hasMany(InventoryNight::class); }

    public function currentStay()
    {
        return $this->hasOne(BookingVilla::class)->where('status', 'active')
            ->whereHas('booking', fn ($q) => $q->where('status', 'checked_in'))->latestOfMany();
    }

    public function isSellable(): bool
    {
        return $this->is_active && $this->maintenance_status !== 'out_of_order';
    }

    public function coverUrl(): string
    {
        $m = $this->media->firstWhere('is_cover', true) ?? $this->media->first();
        return $m ? $m->url() : $this->type?->coverUrl() ?? asset('assets/img/placeholder-villa.svg');
    }
}
