<?php

namespace App\Modules\Website\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\RatePlan;
use App\Models\VillaType;
use App\Modules\Billing\Services\OnlinePaymentService;
use App\Modules\Booking\Services\AvailabilityService;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Villa\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Direct website booking engine. The same availability ledger and pricing rules as the
 * front desk; inventory is HELD for a few minutes while the guest pays on the provider page.
 */
class BookingFlowController extends Controller
{
    public function __construct(private AvailabilityService $availability, private PricingService $pricing) {}

    public function search(Request $request)
    {
        $data = $request->validate([
            'arrival' => ['nullable', 'date', 'after_or_equal:today'], 'departure' => ['nullable', 'date', 'after:arrival'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:12'], 'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'promo' => ['nullable', 'string', 'max:40'], 'type' => ['nullable', 'integer'],
        ]);
        // Release abandoned checkout holds first (also scheduled every minute) so availability is exact.
        app(BookingService::class)->expireHolds();
        $arrival = Carbon::parse($data['arrival'] ?? now()->addDays(14));
        $departure = Carbon::parse($data['departure'] ?? $arrival->copy()->addDays(3));
        $adults = (int) ($data['adults'] ?? 2);
        $children = (int) ($data['children'] ?? 0);
        $offer = $this->pricing->findPromo($data['promo'] ?? null);
        $plans = RatePlan::where('is_active', true)->where('is_public', true)->orderBy('id')->get();

        // Same-day guests only see villas that are clean, inspected and empty right now.
        $types = $this->availability->searchTypes($arrival, $departure, $adults, $children, true)->map(function ($t) use ($plans, $arrival, $departure, $adults, $children, $offer) {
            $t->quotes = $plans->mapWithKeys(fn ($p) => [$p->id => $this->pricing->quote($t, $arrival, $departure, min($adults, $t->max_adults), min($children, $t->max_children), $p, $offer)]);
            return $t;
        })->sortByDesc(fn ($t) => ($t->fits ? 2 : 0) + ($t->available_count > 0 ? 1 : 0))->values();

        if (! empty($data['type'])) {
            $types = $types->sortByDesc(fn ($t) => $t->id === (int) $data['type'])->values();
        }

        return view('website.book.search', [
            'types' => $types, 'plans' => $plans, 'arrival' => $arrival, 'departure' => $departure, 'adults' => $adults, 'children' => $children,
            'nights' => $arrival->diffInDays($departure), 'offer' => $offer, 'promo' => $data['promo'] ?? null, 'promoInvalid' => ! empty($data['promo']) && ! $offer,
        ]);
    }

    public function details(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'exists:villa_types,id'], 'plan' => ['required', 'exists:rate_plans,id'],
            'arrival' => ['required', 'date', 'after_or_equal:today'], 'departure' => ['required', 'date', 'after:arrival'],
            'adults' => ['required', 'integer', 'min:1'], 'children' => ['nullable', 'integer', 'min:0'], 'promo' => ['nullable', 'string', 'max:40'],
        ]);
        $type = VillaType::with('media', 'facilities')->findOrFail($data['type']);
        $plan = RatePlan::findOrFail($data['plan']);
        $offer = $this->pricing->findPromo($data['promo'] ?? null);
        $free = $this->availability->availableVillas($data['arrival'], $data['departure'], $type->id, null, true)->count();
        if (! $free) {
            return redirect()->route('book.search', $request->only('arrival', 'departure', 'adults', 'children', 'promo'))->with('error', 'Sorry — '.$type->name.' has just sold out for those dates. Please choose another villa.');
        }
        $quote = $this->pricing->quote($type, $data['arrival'], $data['departure'], (int) $data['adults'], (int) ($data['children'] ?? 0), $plan, $offer);
        $extras = \App\Models\ChargeItem::whereIn('code', ['TRF-CMB', 'SPA-60', 'EXC-DELFT'])->where('is_active', true)->get();
        return view('website.book.details', compact('type', 'plan', 'quote', 'data', 'offer', 'extras'));
    }

    public function hold(Request $request, BookingService $bookings, OnlinePaymentService $payments)
    {
        $data = $request->validate([
            'type' => ['required', 'exists:villa_types,id'], 'plan' => ['required', 'exists:rate_plans,id'],
            'arrival' => ['required', 'date', 'after_or_equal:today'], 'departure' => ['required', 'date', 'after:arrival'],
            'adults' => ['required', 'integer', 'min:1'], 'children' => ['nullable', 'integer', 'min:0'], 'promo' => ['nullable', 'string', 'max:40'],
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'], 'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:40'], 'country' => ['required', 'string', 'max:80'], 'arrival_time' => ['nullable', 'string', 'max:20'],
            'special_requests' => ['nullable', 'string', 'max:1000'], 'extras' => ['nullable', 'array'], 'extras.*' => ['exists:charge_items,id'],
            'pay' => ['required', Rule::in(['deposit', 'full'])], 'marketing_consent' => ['nullable', 'boolean'], 'terms' => ['accepted'],
        ], ['terms.accepted' => 'Please accept the booking conditions to continue.']);

        $plan = RatePlan::findOrFail($data['plan']);
        $extras = \App\Models\ChargeItem::whereIn('id', $data['extras'] ?? [])->get();
        $requests = trim(($data['special_requests'] ?? '').($extras->isNotEmpty() ? "\nRequested add-ons: ".$extras->pluck('name')->implode(', ') : ''));

        $booking = $bookings->create([
            'source' => 'website', 'channel_code' => 'website', 'status' => 'hold',
            'guest' => ['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'],
                'country' => $data['country'], 'nationality' => $data['country'], 'marketing_consent' => $request->boolean('marketing_consent')],
            'arrival' => $data['arrival'], 'departure' => $data['departure'],
            'villas' => [['villa_type_id' => (int) $data['type'], 'adults' => (int) $data['adults'], 'children' => (int) ($data['children'] ?? 0)]],
            'rate_plan_id' => $plan->id, 'promo_code' => $data['promo'] ?? null, 'arrival_time' => $data['arrival_time'] ?? null,
            'special_requests' => $requests ?: null, 'payment_mode' => $data['pay'],
        ]);

        // Non-refundable plans charge in full; otherwise deposit or full, as chosen.
        $amount = (! $plan->is_refundable || $data['pay'] === 'full') ? (float) $booking->grand_total : (float) $booking->deposit_due;
        $intent = $payments->createIntent($booking, $amount, $data['pay'] === 'full' || ! $plan->is_refundable ? 'full' : 'deposit');
        return redirect()->away($payments->checkoutUrl($intent));
    }

    public function confirmation(string $token)
    {
        $booking = Booking::with(['guest', 'villas.villa.type.media', 'ratePlan', 'payments'])->where('manage_token', $token)->firstOrFail();
        return view('website.book.confirmation', ['b' => $booking, 'paid' => $booking->paidTotal()]);
    }
}
