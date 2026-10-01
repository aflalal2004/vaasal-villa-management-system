<?php

namespace App\Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\RatePlan;
use App\Models\VillaType;
use App\Modules\Booking\Services\AvailabilityService;
use App\Modules\Villa\Services\PricingService;
use Illuminate\Http\Request;

/**
 * Read-only public API (rate-limited) — lets a future mobile app, meta-search or partner site
 * show villas, offers, live availability and prices. Writes always go through authenticated flows.
 */
class PublicApiController extends Controller
{
    public function villaTypes()
    {
        return response()->json(['data' => VillaType::with(['media', 'facilities'])->withCount('villas')->where('is_active', true)->orderBy('sort_order')->get()->map(fn ($t) => $this->type($t))]);
    }

    public function villaType(VillaType $type)
    {
        abort_unless($type->is_active, 404);
        return response()->json(['data' => $this->type($type->load(['media', 'facilities'])->loadCount('villas'), true)]);
    }

    public function offers()
    {
        return response()->json(['data' => Offer::live()->get()->map(fn ($o) => ['title' => $o->title, 'summary' => $o->summary, 'discount' => $o->label(), 'promo_code' => $o->promo_code,
            'min_nights' => $o->min_nights, 'valid_to' => $o->valid_to?->toDateString(), 'image' => $o->imageUrl()])]);
    }

    public function availability(Request $request, AvailabilityService $availability, PricingService $pricing)
    {
        $data = $request->validate(['arrival' => ['required', 'date', 'after_or_equal:today'], 'departure' => ['required', 'date', 'after:arrival', 'before:+2 years'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:12'], 'children' => ['nullable', 'integer', 'min:0', 'max:8'], 'promo' => ['nullable', 'string', 'max:40']]);
        $adults = (int) ($data['adults'] ?? 2);
        $children = (int) ($data['children'] ?? 0);
        $offer = $pricing->findPromo($data['promo'] ?? null);
        $plan = RatePlan::where('is_active', true)->where('is_public', true)->orderBy('id')->first();
        return response()->json([
            'arrival' => $data['arrival'], 'departure' => $data['departure'], 'currency' => config('vaasal.currency'),
            'data' => $availability->searchTypes($data['arrival'], $data['departure'], $adults, $children)->map(function ($t) use ($pricing, $data, $adults, $children, $plan, $offer) {
                $q = $pricing->quote($t, $data['arrival'], $data['departure'], min($adults, $t->max_adults), min($children, $t->max_children), $plan, $offer);
                return ['slug' => $t->slug, 'name' => $t->name, 'available' => $t->available_count, 'fits_party' => $t->fits,
                    'from_rate_plan' => $plan?->code, 'nights' => $q['nights'], 'total' => $q['grand_total'], 'avg_nightly' => $q['avg_nightly'], 'restrictions' => $q['errors'],
                    'book_url' => route('book.search', ['arrival' => $data['arrival'], 'departure' => $data['departure'], 'adults' => $adults, 'children' => $children, 'type' => $t->id])];
            }),
        ]);
    }

    public function quote(Request $request, PricingService $pricing)
    {
        $data = $request->validate(['type' => ['required', 'exists:villa_types,slug'], 'arrival' => ['required', 'date'], 'departure' => ['required', 'date', 'after:arrival'],
            'adults' => ['nullable', 'integer', 'min:1'], 'children' => ['nullable', 'integer', 'min:0'], 'plan' => ['nullable', 'exists:rate_plans,code'], 'promo' => ['nullable', 'string']]);
        $type = VillaType::where('slug', $data['type'])->firstOrFail();
        $plan = RatePlan::where('code', $data['plan'] ?? null)->first() ?? RatePlan::where('is_active', true)->first();
        $q = $pricing->quote($type, $data['arrival'], $data['departure'], (int) ($data['adults'] ?? 2), (int) ($data['children'] ?? 0), $plan, $pricing->findPromo($data['promo'] ?? null));
        unset($q['offer']);
        return response()->json(['currency' => config('vaasal.currency'), 'rate_plan' => $plan?->code] + $q);
    }

    private function type(VillaType $t, bool $full = false): array
    {
        return array_filter([
            'slug' => $t->slug, 'name' => $t->name, 'summary' => $t->short_description, 'description' => $full ? $t->description : null,
            'max_adults' => $t->max_adults, 'max_children' => $t->max_children, 'bedrooms' => $t->bedrooms, 'size_sqm' => $t->size_sqm, 'villas' => $t->villas_count,
            'base_rate' => (float) $t->base_rate, 'currency' => config('vaasal.currency'), 'facilities' => $t->facilities->pluck('name'),
            'images' => $t->media->where('type', 'image')->map->url()->values(), 'url' => route('site.villa', $t),
        ], fn ($v) => $v !== null);
    }
}
