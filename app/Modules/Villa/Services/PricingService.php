<?php

namespace App\Modules\Villa\Services;

use App\Models\Offer;
use App\Models\OperatorContract;
use App\Models\Property;
use App\Models\Rate;
use App\Models\RatePlan;
use App\Models\Season;
use App\Models\Villa;
use App\Models\VillaType;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

/**
 * Prices a stay night by night.
 *
 * Nightly base = villa rate override → seasonal rate for the villa type (highest-priority season
 * covering the night) → villa type base rate. Then: rate-plan adjustment, extra-guest charges,
 * tour-operator contract (net rate or discount), offer / promo discount, service charge, tax.
 */
class PricingService
{
    public function quote(
        VillaType $type,
        Carbon|string $arrival,
        Carbon|string $departure,
        int $adults,
        int $children = 0,
        ?RatePlan $plan = null,
        ?Offer $offer = null,
        ?OperatorContract $contract = null,
        ?Villa $villa = null,
    ): array {
        $arrival = Carbon::parse($arrival)->startOfDay();
        $departure = Carbon::parse($departure)->startOfDay();
        $errors = [];
        $property = Property::current();

        $nights = (int) $arrival->diffInDays($departure);
        if ($nights < 1) {
            return $this->empty(['Departure must be after arrival.']);
        }
        if ($adults > $type->max_adults || $children > $type->max_children) {
            $errors[] = "{$type->name} sleeps up to {$type->max_adults} adults and {$type->max_children} children.";
        }

        $seasons = Season::where('start_date', '<', $departure)->where('end_date', '>=', $arrival)->orderByDesc('priority')->get();
        $rates = Rate::where('villa_type_id', $type->id)->whereIn('season_id', $seasons->pluck('id'))->get()->keyBy('season_id');

        $nightly = [];
        $minStay = 1;
        foreach (CarbonPeriod::create($arrival, $departure->copy()->subDay()) as $date) {
            $d = $date->toDateString();
            $season = $seasons->first(fn ($s) => $s->start_date->toDateString() <= $d && $s->end_date->toDateString() >= $d && $rates->has($s->id));
            $rate = $season ? $rates[$season->id] : null;
            $minStay = max($minStay, $rate?->min_stay ?? 1);

            if ($contract && ($net = $contract->netRateFor($type->id)) !== null) {
                $amount = $net;
            } else {
                $amount = (float) ($villa?->rate_override ?? $rate?->amount ?? $type->base_rate);
                if ($plan && (float) $plan->price_adjust_pct !== 0.0) {
                    $amount *= 1 + ((float) $plan->price_adjust_pct / 100);
                }
                if ($contract && (float) $contract->discount_pct > 0) {
                    $amount *= 1 - ((float) $contract->discount_pct / 100);
                }
            }

            $extraAdults = max(0, $adults - $type->base_occupancy);
            $amount += $extraAdults * (float) $type->extra_adult_rate + $children * (float) $type->extra_child_rate;
            $nightly[$d] = round($amount, 2);
        }

        if ($nights < $minStay) {
            $errors[] = "A minimum stay of {$minStay} nights applies to these dates.";
        }

        $roomTotal = round(array_sum($nightly), 2);
        $discount = 0.0;
        $offerApplied = null;
        if ($offer) {
            $offerError = $this->offerError($offer, $arrival, $nights);
            if ($offerError) {
                $errors[] = $offerError;
            } else {
                $discount = $offer->discount_type === 'percent'
                    ? round($roomTotal * (float) $offer->discount_value / 100, 2)
                    : min($roomTotal, (float) $offer->discount_value);
                $offerApplied = $offer;
            }
        }

        $taxable = $roomTotal - $discount;
        $service = round($taxable * (float) $property->service_charge_pct / 100, 2);
        $tax = round(($taxable + $service) * (float) $property->tax_pct / 100, 2);
        $grand = round($taxable + $service + $tax, 2);

        $depositPct = $contract ? (float) $contract->deposit_pct : (float) ($plan->deposit_pct ?? 30);

        return [
            'ok' => empty($errors),
            'errors' => $errors,
            'nights' => $nights,
            'nightly' => $nightly,
            'room_total' => $roomTotal,
            'discount' => $discount,
            'offer' => $offerApplied,
            'service' => $service,
            'tax' => $tax,
            'grand_total' => $grand,
            'deposit_pct' => $depositPct,
            'deposit_due' => round($grand * $depositPct / 100, 2),
            'avg_nightly' => $nights ? round($roomTotal / $nights, 2) : 0,
        ];
    }

    public function offerError(Offer $offer, Carbon $arrival, int $nights): ?string
    {
        $today = now()->startOfDay();
        if (! $offer->is_active) return 'This offer is no longer active.';
        if ($offer->valid_from && $offer->valid_from->gt($today)) return 'This offer is not yet valid.';
        if ($offer->valid_to && $offer->valid_to->lt($today)) return 'This offer has expired.';
        if ($offer->max_uses && $offer->used_count >= $offer->max_uses) return 'This offer has reached its usage limit.';
        if ($nights < $offer->min_nights) return "This offer requires a minimum of {$offer->min_nights} nights.";
        return null;
    }

    public function findPromo(?string $code): ?Offer
    {
        $code = trim((string) $code);
        return $code === '' ? null : Offer::whereRaw('UPPER(promo_code) = ?', [strtoupper($code)])->first();
    }

    private function empty(array $errors): array
    {
        return ['ok' => false, 'errors' => $errors, 'nights' => 0, 'nightly' => [], 'room_total' => 0, 'discount' => 0, 'offer' => null,
            'service' => 0, 'tax' => 0, 'grand_total' => 0, 'deposit_pct' => 0, 'deposit_due' => 0, 'avg_nightly' => 0];
    }
}
