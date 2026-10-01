<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingVilla;
use App\Models\ChargeItem;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\StayGuest;
use App\Models\TourOperator;
use App\Models\Villa;
use App\Modules\Billing\Services\FolioService;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Billing\Services\OnlinePaymentService;
use App\Modules\Booking\Http\Requests\StoreBookingRequest;
use App\Modules\Booking\Services\AvailabilityService;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Core\Services\AuditService;
use App\Modules\Villa\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(Request $request)
    {
        $q = Booking::with(['guest', 'channel', 'operator', 'activeVillas.villa'])
            ->when($request->filled('q'), function ($w) use ($request) {
                $term = trim($request->query('q'));
                $w->where(fn ($s) => $s->where('reference', 'like', "%{$term}%")->orWhere('external_ref', 'like', "%{$term}%")->orWhere('group_name', 'like', "%{$term}%")
                    ->orWhereHas('guest', fn ($g) => $g->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")
                        ->orWhereRaw("CONCAT(first_name,' ',last_name) like ?", ["%{$term}%"])));
            })
            ->when($request->filled('status'), fn ($w) => $w->where('status', $request->query('status')))
            ->when($request->filled('source'), fn ($w) => $w->where('source', $request->query('source')))
            ->when($request->filled('from'), fn ($w) => $w->where('departure', '>', $request->query('from')))
            ->when($request->filled('to'), fn ($w) => $w->where('arrival', '<=', $request->query('to')))
            ->when($request->filled('operator'), fn ($w) => $w->where('tour_operator_id', $request->query('operator')));

        $sort = $request->query('sort', 'arrival_desc');
        match ($sort) {
            'arrival_asc' => $q->orderBy('arrival'),
            'created' => $q->latest(),
            default => $q->orderByDesc('arrival'),
        };

        return view('admin.bookings.index', [
            'bookings' => $q->paginate(20)->withQueryString(),
            'operators' => TourOperator::orderBy('company_name')->pluck('company_name', 'id'),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.bookings.create', [
            'plans' => RatePlan::where('is_active', true)->get(),
            'operators' => TourOperator::where('status', 'active')->orderBy('company_name')->pluck('company_name', 'id'),
            'guest' => $request->filled('guest') ? Guest::find($request->query('guest')) : null,
            'source' => $request->query('source', 'phone'),
            'arrival' => $request->query('arrival', now()->toDateString()),
            'departure' => $request->query('departure', now()->addDays(2)->toDateString()),
            'preselect' => $request->query('villa'),
        ]);
    }

    /** JSON: available villas + price for the requested stay. */
    public function quote(Request $request, AvailabilityService $availability, PricingService $pricing)
    {
        $data = $request->validate([
            'arrival' => ['required', 'date'], 'departure' => ['required', 'date', 'after:arrival'],
            'adults' => ['nullable', 'integer', 'min:1'], 'children' => ['nullable', 'integer', 'min:0'],
            'rate_plan_id' => ['nullable', 'exists:rate_plans,id'], 'promo_code' => ['nullable', 'string'], 'tour_operator_id' => ['nullable', 'exists:tour_operators,id'],
        ]);
        $plan = RatePlan::find($data['rate_plan_id'] ?? null);
        $offer = $pricing->findPromo($data['promo_code'] ?? null);
        $contract = ! empty($data['tour_operator_id']) ? TourOperator::find($data['tour_operator_id'])->activeContract($data['arrival'])?->load('rates') : null;

        $villas = $availability->availableVillas($data['arrival'], $data['departure'])->map(function (Villa $v) use ($data, $pricing, $plan, $offer, $contract) {
            $adults = min((int) ($data['adults'] ?? 2), $v->type->max_adults);
            $q = $pricing->quote($v->type, $data['arrival'], $data['departure'], $adults, 0, $plan, $offer, $contract, $v);
            return [
                'id' => $v->id, 'code' => $v->code, 'name' => $v->name, 'type' => $v->type->name, 'max_adults' => $v->type->max_adults,
                'max_children' => $v->type->max_children, 'hk_status' => $v->hk_status, 'maintenance' => $v->maintenance_status,
                'nights' => $q['nights'], 'avg' => $q['avg_nightly'], 'room_total' => $q['room_total'], 'discount' => $q['discount'],
                'total' => $q['grand_total'], 'errors' => $q['errors'],
            ];
        })->values();

        return response()->json([
            'villas' => $villas,
            'promo' => $offer ? ['title' => $offer->title, 'label' => $offer->label()] : null,
            'promo_invalid' => ! empty($data['promo_code']) && ! $offer,
            'contract' => $contract ? $contract->name.' · '.$contract->commission_pct.'% commission' : null,
        ]);
    }

    public function store(StoreBookingRequest $request, FolioService $folios, InvoiceService $invoices)
    {
        $data = $request->validated();
        $booking = $this->bookings->create([
            'source' => $data['source'],
            'channel_code' => $data['source'],
            'status' => $data['status'],
            'guest' => ! empty($data['guest']['id']) ? Guest::findOrFail($data['guest']['id']) : $data['guest'],
            'arrival' => $data['arrival'],
            'departure' => $data['departure'],
            'villas' => $data['villas'],
            'rate_plan_id' => $data['rate_plan_id'] ?? null,
            'promo_code' => $data['promo_code'] ?? null,
            'tour_operator_id' => $data['tour_operator_id'] ?? null,
            'group_name' => $data['group_name'] ?? null,
            'rate_override' => $data['rate_override'] ?? null,
            'special_requests' => $data['special_requests'] ?? null,
            'arrival_time' => $data['arrival_time'] ?? null,
        ]);
        if (! empty($data['rate_override'])) {
            $this->bookings->event($booking, 'rate_override', 'Rate overridden to '.money($data['rate_override']).'/night: '.$data['override_reason']);
            AuditService::log('booking', 'rate_override', $booking, $data['override_reason']);
        }
        if (! empty($data['deposit_amount']) && $data['deposit_amount'] > 0) {
            $p = $folios->recordPayment($folios->folioFor($booking, 'room'), $data['deposit_method'] ?? 'cash', (float) $data['deposit_amount'], 'deposit', 'Deposit at booking');
            $invoices->issueReceipt($p);
        }

        if ($data['source'] === 'walk_in' && $request->boolean('go_checkin')) {
            return redirect()->route('admin.frontdesk.checkin', $booking)->with('success', "Booking {$booking->reference} created. Complete the registration below.");
        }
        return redirect()->route('admin.bookings.show', $booking)->with('success', "Booking {$booking->reference} created.");
    }

    public function show(Booking $booking, FolioService $folios, AvailabilityService $availability)
    {
        $booking->load(['guest', 'channel', 'operator', 'contract', 'ratePlan', 'offer', 'creator', 'villas.villa.type', 'villas.guests',
            'folios.lines.poster', 'folios.payments', 'invoices', 'events.user', 'cardAssignments.card', 'cardAssignments.jobs', 'commission']);

        $moveOptions = [];
        foreach ($booking->villas->where('status', 'active') as $bv) {
            $moveOptions[$bv->id] = $availability->availableVillas($booking->status === 'checked_in' ? now() : $bv->arrival, $bv->departure, null, $bv->id)
                ->reject(fn ($v) => $v->id === $bv->villa_id)->mapWithKeys(fn ($v) => [$v->id => $v->code.' — '.$v->name])->all();
        }

        return view('admin.bookings.show', [
            'b' => $booking,
            'summaries' => $booking->folios->mapWithKeys(fn ($f) => [$f->id => $folios->summary($f)]),
            'chargeItems' => ChargeItem::where('is_active', true)->orderBy('department')->orderBy('name')->get(),
            'moveOptions' => $moveOptions,
            'cancelFee' => $booking->isLive() && $booking->status !== 'checked_in' ? $this->bookings->cancellationFee($booking) : 0,
            'posOrders' => \App\Models\PosOrder::with('outlet')->where('booking_id', $booking->id)->latest()->get(),
        ]);
    }

    public function confirm(Booking $booking)
    {
        $this->bookings->confirm($booking, 'Confirmed by '.auth()->user()->name);
        $this->bookings->event($booking, 'status', 'Status set to confirmed');
        return back()->with('success', 'Booking confirmed.');
    }

    public function changeDates(Request $request, Booking $booking)
    {
        $data = $request->validate(['arrival' => ['required', 'date'], 'departure' => ['required', 'date', 'after:arrival'], 'reason' => ['nullable', 'string', 'max:200']]);
        $this->bookings->changeDates($booking, $data['arrival'], $data['departure'], $data['reason'] ?? null);
        return back()->with('success', 'Stay dates updated and re-priced.');
    }

    public function moveVilla(Request $request, Booking $booking, BookingVilla $bookingVilla)
    {
        abort_unless($bookingVilla->booking_id === $booking->id, 404);
        $data = $request->validate(['villa_id' => ['required', 'exists:villas,id'], 'reason' => ['nullable', 'string', 'max:200']]);
        $this->bookings->changeVilla($bookingVilla, Villa::findOrFail($data['villa_id']), $data['reason'] ?? null);
        if ($booking->status === 'checked_in') {
            return back()->with('success', 'Villa changed. Issue new key cards for the new villa.')->with('warning', 'The old villa is now Dirty and a housekeeping task was created.');
        }
        return back()->with('success', 'Villa changed.');
    }

    public function updateNotes(Request $request, Booking $booking)
    {
        $data = $request->validate(['special_requests' => ['nullable', 'string', 'max:1000'], 'arrival_time' => ['nullable', 'string', 'max:20'], 'group_name' => ['nullable', 'string', 'max:120']]);
        $booking->fill($data);
        AuditService::logChanges('booking', $booking, 'notes_updated');
        $booking->save();
        return back()->with('success', 'Booking details saved.');
    }

    public function addStayGuest(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'booking_villa_id' => ['required', 'integer'], 'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'],
            'nationality' => ['nullable', 'string', 'max:80'], 'passport_no' => ['nullable', 'string', 'max:40'], 'date_of_birth' => ['nullable', 'date'],
            'is_child' => ['nullable', 'boolean'], 'flight_details' => ['nullable', 'string', 'max:120'],
        ]);
        $bv = $booking->villas()->findOrFail($data['booking_villa_id']);
        $bv->guests()->create($data);
        return back()->with('success', 'Guest added to the rooming list.');
    }

    public function removeStayGuest(Booking $booking, StayGuest $stayGuest)
    {
        abort_unless($stayGuest->bookingVilla->booking_id === $booking->id, 404);
        $stayGuest->delete();
        return back()->with('success', 'Guest removed from the rooming list.');
    }

    public function cancel(Request $request, Booking $booking)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:300'], 'fee' => ['nullable', 'numeric', 'min:0']]);
        $fee = $request->filled('fee') ? (float) $data['fee'] : null;
        $this->bookings->cancel($booking, $data['reason'], $fee);
        return back()->with('success', 'Booking cancelled and inventory released to all channels.');
    }

    public function noShow(Booking $booking)
    {
        $this->bookings->markNoShow($booking);
        return back()->with('success', 'Marked as no-show; remaining nights released.');
    }

    public function sendConfirmation(Booking $booking, OnlinePaymentService $payments)
    {
        if (! $booking->guest->email) {
            return back()->with('error', 'The guest has no email address. Add one on the guest profile first.');
        }
        $payments->sendConfirmation($booking->load(['guest', 'villas.villa.type']));
        return back()->with('success', 'Confirmation and voucher emailed to '.$booking->guest->email.'.');
    }

    public function paymentLink(Request $request, Booking $booking, OnlinePaymentService $payments)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:1']]);
        $intent = $payments->createIntent($booking, (float) $data['amount'], 'balance');
        $url = route('manage.show', $booking->manage_token).'?pay='.$intent->token;
        $this->bookings->event($booking, 'payment_link', 'Payment link created for '.money($data['amount']));
        return back()->with('success', 'Payment link created. Share it with the guest: '.$url)->with('payment_link', $url);
    }

    public function proforma(Booking $booking, InvoiceService $invoices)
    {
        $inv = $invoices->issueProforma($booking);
        return redirect()->route('admin.invoices.show', $inv);
    }

    public function voucher(Booking $booking)
    {
        return view('print.voucher', ['b' => $booking->load(['guest', 'villas.villa.type', 'villas.guests', 'ratePlan', 'operator'])]);
    }

    public function confirmation(Booking $booking)
    {
        return view('print.confirmation', ['b' => $booking->load(['guest', 'villas.villa.type', 'ratePlan', 'operator', 'offer']), 'paid' => $booking->paidTotal()]);
    }

    public function registrationCard(Booking $booking)
    {
        return view('print.registration', ['b' => $booking->load(['guest', 'villas.villa', 'villas.guests', 'ratePlan'])]);
    }
}
