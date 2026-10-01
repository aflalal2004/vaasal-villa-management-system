<?php

namespace App\Modules\Booking\Services;

use App\Models\BookingVilla;
use App\Models\InventoryNight;
use App\Models\Villa;
use App\Models\VillaBlock;
use App\Models\VillaType;
use App\Modules\Booking\Exceptions\InventoryConflictException;
use Carbon\CarbonPeriod;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Central availability ledger shared by every booking source (website, admin, walk-in, phone,
 * tour operator, OTA). One inventory_nights row per villa per night; UNIQUE(villa_id, stay_date)
 * makes the database reject any double booking atomically.
 */
class AvailabilityService
{
    /**
     * Villas free for every night in [arrival, departure).
     * For a same-day arrival, villas that are ready right now are listed first (so auto-assignment picks them);
     * with $readyNowForToday the others (occupied, dirty, cleaning, awaiting inspection) are left out entirely.
     */
    public function availableVillas(Carbon|string $arrival, Carbon|string $departure, ?int $villaTypeId = null, ?int $ignoreBookingVillaId = null, bool $readyNowForToday = false): Collection
    {
        [$from, $toExclusive] = $this->range($arrival, $departure);

        $villas = Villa::query()
            ->with('type')
            ->where('is_active', true)
            ->where('maintenance_status', '!=', 'out_of_order')
            ->when($villaTypeId, fn ($q) => $q->where('villa_type_id', $villaTypeId))
            ->whereDoesntHave('nights', function ($q) use ($from, $toExclusive, $ignoreBookingVillaId) {
                $q->where('stay_date', '>=', $from)->where('stay_date', '<', $toExclusive);
                if ($ignoreBookingVillaId) {
                    $q->where(fn ($w) => $w->whereNull('booking_villa_id')->orWhere('booking_villa_id', '!=', $ignoreBookingVillaId));
                }
            })
            ->orderBy('sort_order')->orderBy('code')
            ->get();

        if ($from === now()->toDateString()) {
            $villas = $villas->sortBy(fn (Villa $v) => $v->isReadyNow() ? 0 : 1)->values();
            if ($readyNowForToday) {
                $villas = $villas->filter(fn (Villa $v) => $v->isReadyNow())->values();
            }
        }
        return $villas;
    }

    public function isVillaFree(int $villaId, Carbon|string $arrival, Carbon|string $departure, ?int $ignoreBookingVillaId = null): bool
    {
        [$from, $toExclusive] = $this->range($arrival, $departure);
        return ! InventoryNight::where('villa_id', $villaId)
            ->where('stay_date', '>=', $from)->where('stay_date', '<', $toExclusive)
            ->when($ignoreBookingVillaId, fn ($q) => $q->where(fn ($w) => $w->whereNull('booking_villa_id')->orWhere('booking_villa_id', '!=', $ignoreBookingVillaId)))
            ->exists();
    }

    /** Availability grouped by villa type, with capacity filter — used by website and booking forms. */
    public function searchTypes(Carbon|string $arrival, Carbon|string $departure, int $adults = 1, int $children = 0, bool $readyNowForToday = false): Collection
    {
        $free = $this->availableVillas($arrival, $departure, null, null, $readyNowForToday)->groupBy('villa_type_id');

        return VillaType::with(['media', 'facilities'])->where('is_active', true)->orderBy('sort_order')->get()
            ->map(function (VillaType $type) use ($free, $adults, $children) {
                $type->available_villas = $free->get($type->id, collect());
                $type->available_count = $type->available_villas->count();
                $type->fits = $adults <= $type->max_adults && $children <= $type->max_children;
                return $type;
            });
    }

    /** Count of free villas per type per date — the ARI payload pushed to the channel manager. */
    public function availabilityMatrix(Carbon|string $from, Carbon|string $to): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        $villas = Villa::where('is_active', true)->get(['id', 'villa_type_id', 'maintenance_status']);
        $taken = InventoryNight::whereBetween('stay_date', [$from->toDateString(), $to->toDateString()])
            ->get(['villa_id', 'stay_date'])->groupBy(fn ($n) => $n->stay_date->toDateString());

        $matrix = [];
        foreach (CarbonPeriod::create($from, $to) as $date) {
            $d = $date->toDateString();
            $busy = $taken->get($d, collect())->pluck('villa_id')->flip();
            foreach ($villas->groupBy('villa_type_id') as $typeId => $typeVillas) {
                $matrix[$typeId][$d] = $typeVillas->filter(fn ($v) => $v->maintenance_status !== 'out_of_order' && ! $busy->has($v->id))->count();
            }
        }
        return $matrix;
    }

    /** Write the ledger rows for a booking line. Must run inside the caller's transaction. */
    public function reserve(BookingVilla $bv): void
    {
        $rows = [];
        foreach ($this->nights($bv->arrival, $bv->departure) as $d) {
            $rows[] = ['villa_id' => $bv->villa_id, 'stay_date' => $d, 'booking_villa_id' => $bv->id, 'created_at' => now()];
        }
        $this->insertOrFail($rows, $bv->villa->code ?? ('#'.$bv->villa_id));
    }

    public function release(BookingVilla $bv, Carbon|string|null $fromDate = null): int
    {
        return InventoryNight::where('booking_villa_id', $bv->id)
            ->when($fromDate, fn ($q) => $q->where('stay_date', '>=', Carbon::parse($fromDate)->toDateString()))
            ->delete();
    }

    public function block(Villa $villa, Carbon|string $start, Carbon|string $endExclusive, string $reason = 'maintenance', ?string $notes = null): VillaBlock
    {
        return DB::transaction(function () use ($villa, $start, $endExclusive, $reason, $notes) {
            $block = VillaBlock::create([
                'villa_id' => $villa->id, 'start_date' => Carbon::parse($start)->toDateString(),
                'end_date' => Carbon::parse($endExclusive)->toDateString(), 'reason' => $reason, 'notes' => $notes, 'created_by' => auth()->id(),
            ]);
            $rows = [];
            foreach ($this->nights($start, $endExclusive) as $d) {
                $rows[] = ['villa_id' => $villa->id, 'stay_date' => $d, 'villa_block_id' => $block->id, 'created_at' => now()];
            }
            $this->insertOrFail($rows, $villa->code, 'Cannot block: '.$villa->code.' already has bookings on some of these nights.');
            return $block;
        });
    }

    public function unblock(VillaBlock $block): void
    {
        $block->delete(); // inventory rows cascade
    }

    /** Villa × date grid for the central calendar. */
    public function calendar(Carbon $from, int $days): array
    {
        $to = $from->copy()->addDays($days - 1);
        $nights = InventoryNight::with(['bookingVilla.booking.guest', 'bookingVilla.booking.channel', 'block'])
            ->whereBetween('stay_date', [$from->toDateString(), $to->toDateString()])->get();

        $grid = [];
        foreach ($nights as $n) {
            $grid[$n->villa_id][$n->stay_date->toDateString()] = $n;
        }
        return $grid;
    }

    /** @return string[] Y-m-d of every night in [arrival, departure) */
    public function nights(Carbon|string $arrival, Carbon|string $departure): array
    {
        [$from, $toExclusive] = $this->range($arrival, $departure);
        $out = [];
        for ($d = Carbon::parse($from); $d->toDateString() < $toExclusive; $d->addDay()) {
            $out[] = $d->toDateString();
        }
        return $out;
    }

    private function insertOrFail(array $rows, string $villaLabel, ?string $message = null): void
    {
        if (! $rows) return;
        try {
            InventoryNight::insert($rows);
        } catch (QueryException $e) {
            // 23000 = integrity constraint violation (duplicate villa/night)
            if (($e->errorInfo[0] ?? null) === '23000') {
                throw new InventoryConflictException($message ?? "Villa {$villaLabel} is already booked for one or more of the selected nights.");
            }
            throw $e;
        }
    }

    private function range(Carbon|string $arrival, Carbon|string $departure): array
    {
        return [Carbon::parse($arrival)->toDateString(), Carbon::parse($departure)->toDateString()];
    }
}
