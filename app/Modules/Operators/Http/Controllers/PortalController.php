<?php

namespace App\Modules\Operators\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\RatePlan;
use App\Models\TourOperator;
use App\Modules\Booking\Services\AvailabilityService;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\UploadService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Operators\Http\Requests\OperatorRequest;
use App\Modules\Operators\Services\OperatorService;
use App\Modules\Villa\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Tour operator self-service portal. Every query is scoped to the signed-in user's company.
 */
class PortalController extends Controller
{
    public function __construct(private OperatorService $operators) {}

    public function dashboard(Request $request)
    {
        $op = $this->op($request);
        return view('operator.dashboard', [
            'op' => $op,
            'contract' => $op->activeContract()?->load('rates.villaType'),
            'upcoming' => $op->bookings()->with(['guest', 'activeVillas.villa'])->whereIn('status', ['confirmed', 'tentative'])->where('arrival', '>=', now()->toDateString())->orderBy('arrival')->limit(8)->get(),
            'inHouse' => $op->bookings()->with(['guest', 'activeVillas.villa'])->where('status', 'checked_in')->get(),
            'statement' => $this->operators->statement($op),
            'invoices' => $op->invoices()->where('type', 'operator_invoice')->whereIn('status', ['issued', 'partially_paid'])->orderBy('due_date')->get(),
            'nightsYtd' => $op->bookings()->whereIn('status', ['checked_out', 'checked_in', 'confirmed'])->whereYear('arrival', now()->year)->get()->sum(fn ($b) => $b->nights() * max(1, $b->villas()->count())),
        ]);
    }

    public function availability(Request $request, AvailabilityService $availability, PricingService $pricing)
    {
        $op = $this->op($request);
        $arrival = Carbon::parse($request->query('arrival', now()->addDays(14)->toDateString()));
        $departure = Carbon::parse($request->query('departure', $arrival->copy()->addDays(3)->toDateString()));
        if ($departure->lte($arrival)) $departure = $arrival->copy()->addDay();
        $contract = $op->activeContract($arrival->toDateString())?->load('rates');
        $types = $availability->searchTypes($arrival, $departure)->map(function ($t) use ($pricing, $arrival, $departure, $contract) {
            $t->quote = $pricing->quote($t, $arrival, $departure, min(2, $t->max_adults), 0, null, null, $contract);
            return $t;
        });
        return view('operator.availability', ['types' => $types, 'arrival' => $arrival, 'departure' => $departure, 'contract' => $contract]);
    }

    public function bookings(Request $request)
    {
        $op = $this->op($request);
        $q = $op->bookings()->with(['guest', 'activeVillas.villa'])
            ->when($request->filled('status'), fn ($w) => $w->where('status', $request->query('status')))
            ->when($request->filled('q'), fn ($w) => $w->where(fn ($s) => $s->where('reference', 'like', '%'.$request->query('q').'%')->orWhere('group_name', 'like', '%'.$request->query('q').'%')))
            ->orderByDesc('arrival');
        return view('operator.bookings', ['bookings' => $q->paginate(20)->withQueryString()]);
    }

    public function create(Request $request, AvailabilityService $availability)
    {
        $op = $this->op($request);
        $arrival = $request->query('arrival', now()->addDays(14)->toDateString());
        $departure = $request->query('departure', Carbon::parse($arrival)->addDays(3)->toDateString());
        return view('operator.booking-create', [
            'op' => $op,
            'arrival' => $arrival,
            'departure' => $departure,
            'types' => $availability->searchTypes($arrival, $departure),
            'plans' => RatePlan::where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request, BookingService $bookings)
    {
        $op = $this->op($request);
        if ($op->status !== 'active') {
            throw new BusinessRuleException('Your company account is not yet approved for bookings.');
        }
        $data = $request->validate([
            'arrival' => ['required', 'date', 'after_or_equal:today'], 'departure' => ['required', 'date', 'after:arrival'],
            'group_name' => ['nullable', 'string', 'max:120'], 'rate_plan_id' => ['nullable', 'exists:rate_plans,id'],
            'lead_first_name' => ['required', 'string', 'max:80'], 'lead_last_name' => ['required', 'string', 'max:80'],
            'lead_email' => ['nullable', 'email'], 'lead_nationality' => ['nullable', 'string', 'max:80'],
            'rooms' => ['required', 'array'], 'rooms.*.qty' => ['nullable', 'integer', 'min:0', 'max:10'], 'rooms.*.adults' => ['nullable', 'integer', 'min:1', 'max:8'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
        ]);
        $lines = [];
        foreach ($data['rooms'] as $typeId => $r) {
            for ($i = 0; $i < (int) ($r['qty'] ?? 0); $i++) {
                $lines[] = ['villa_type_id' => (int) $typeId, 'adults' => (int) ($r['adults'] ?? 2)];
            }
        }
        if (! $lines) {
            return back()->withInput()->with('error', 'Choose at least one villa.');
        }
        // Credit check against limit
        if ((float) $op->credit_limit > 0 && $op->outstanding() > (float) $op->credit_limit) {
            throw new BusinessRuleException('Your outstanding balance exceeds your credit limit. Please settle open invoices or contact reservations.');
        }

        $booking = $bookings->create([
            'source' => 'tour_operator', 'channel_code' => 'tour_operator', 'tour_operator_id' => $op->id,
            'guest' => ['first_name' => $data['lead_first_name'], 'last_name' => $data['lead_last_name'], 'email' => $data['lead_email'] ?? null, 'nationality' => $data['lead_nationality'] ?? null],
            'arrival' => $data['arrival'], 'departure' => $data['departure'], 'villas' => $lines, 'rate_plan_id' => $data['rate_plan_id'] ?? null,
            'group_name' => $data['group_name'] ?? null, 'special_requests' => $data['special_requests'] ?? null, 'status' => 'confirmed',
        ]);
        AuditService::log('operators', 'portal_booking', $booking, $op->company_name);
        return redirect()->route('operator.bookings.show', $booking)->with('success', 'Booking '.$booking->reference.' confirmed. Add your rooming list below.');
    }

    public function show(Request $request, Booking $booking)
    {
        $this->own($request, $booking);
        $booking->load(['guest', 'villas.villa.type', 'villas.guests', 'ratePlan', 'contract', 'payments', 'invoices']);
        $cutoff = $booking->contract?->rooming_cutoff_days ?? 3;
        return view('operator.booking-show', ['b' => $booking, 'locked' => now()->startOfDay()->diffInDays($booking->arrival, false) < $cutoff, 'cutoff' => $cutoff]);
    }

    public function roomingList(Request $request, Booking $booking)
    {
        $this->own($request, $booking);
        $data = $request->validate([
            'guests' => ['required', 'array'], 'guests.*.booking_villa_id' => ['required', 'integer'],
            'guests.*.first_name' => ['nullable', 'string', 'max:80'], 'guests.*.last_name' => ['nullable', 'string', 'max:80'],
            'guests.*.nationality' => ['nullable', 'string', 'max:80'], 'guests.*.passport_no' => ['nullable', 'string', 'max:40'],
            'guests.*.flight_details' => ['nullable', 'string', 'max:120'], 'guests.*.is_child' => ['nullable', 'boolean'],
        ]);
        $rows = collect($data['guests'])->filter(fn ($g) => ! empty($g['first_name']))->map(fn ($g) => $g + ['villa' => $g['booking_villa_id']])->all();
        $n = $this->operators->importRoomingList($booking, $rows);
        return back()->with('success', $n.' guest(s) added to the rooming list.');
    }

    public function uploadRoomingList(Request $request, Booking $booking)
    {
        $this->own($request, $booking);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:512']]);
        $fh = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim($h)), fgetcsv($fh) ?: []);
        $required = ['villa', 'first_name', 'last_name'];
        if (array_diff($required, $header)) {
            fclose($fh);
            return back()->with('error', 'The CSV must have columns: villa, first_name, last_name (optional: nationality, passport_no, flight_details, is_child). Download the template.');
        }
        $rows = [];
        while (($r = fgetcsv($fh)) !== false) {
            if (count($r) === count($header)) $rows[] = array_combine($header, array_map('trim', $r));
            if (count($rows) >= 200) break;
        }
        fclose($fh);
        $n = $this->operators->importRoomingList($booking, $rows);
        return back()->with('success', $n.' guest(s) imported from CSV.');
    }

    public function template()
    {
        return response("villa,first_name,last_name,nationality,passport_no,flight_details,is_child\nP1,Anna,Muller,German,C01X00T47,UL 504 ARR 14:10,0\n", 200, [
            'Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="rooming-list-template.csv"',
        ]);
    }

    public function cancel(Request $request, Booking $booking, BookingService $bookings)
    {
        $this->own($request, $booking);
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);
        $fee = $bookings->cancellationFee($booking);
        $bookings->cancel($booking, 'Operator: '.$data['reason'], $fee);
        NotificationService::notify('operator.cancelled', 'Operator cancelled '.$booking->reference, $booking->operator->company_name.': '.$data['reason'], route('admin.bookings.show', $booking), 'warning', 'operators.view');
        return back()->with('success', 'Booking cancelled.'.($fee > 0 ? ' A cancellation fee of '.money($fee).' applies per contract.' : ''));
    }

    public function voucher(Request $request, Booking $booking)
    {
        $this->own($request, $booking);
        return view('print.voucher', ['b' => $booking->load(['guest', 'villas.villa.type', 'villas.guests', 'ratePlan', 'operator'])]);
    }

    public function invoices(Request $request)
    {
        $op = $this->op($request);
        return view('operator.invoices', [
            'invoices' => $op->invoices()->whereIn('type', ['operator_invoice', 'proforma', 'receipt'])->latest('issued_at')->paginate(20),
            'payments' => $op->payments()->where('method', '!=', 'city_ledger')->latest('paid_at')->limit(15)->get(),
            'open' => $op->invoices()->where('type', 'operator_invoice')->whereIn('status', ['issued', 'partially_paid'])->get(),
        ]);
    }

    public function invoice(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->tour_operator_id === $this->op($request)->id, 404);
        return view('print.invoice', ['inv' => $invoice->load(['booking.guest', 'operator', 'payments']), 'copy' => true]);
    }

    /** Operator notifies a bank transfer with remittance proof; finance verifies it. */
    public function payment(Request $request)
    {
        $op = $this->op($request);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'], 'reference' => ['required', 'string', 'max:80'],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('tour_operator_id', $op->id)],
            'proof' => ['required', 'file', 'max:'.config('vaasal.security.upload_max_kb')],
        ]);
        $path = UploadService::privateDocument($request->file('proof'), 'payment-proofs');
        NotificationService::notify('operator.payment_advice', 'Payment advice from '.$op->company_name, money($data['amount']).' ref '.$data['reference'].($data['invoice_id'] ? ' for invoice #'.$data['invoice_id'] : '').'. Verify and record it.',
            route('admin.operators.show', $op), 'info', 'operators.finance');
        AuditService::log('operators', 'payment_advice', $op, money($data['amount']).' ref '.$data['reference'].' proof '.$path);
        return back()->with('success', 'Thank you — our accounts team will confirm the payment once it reaches our bank.');
    }

    public function statement(Request $request)
    {
        $op = $this->op($request);
        return view('operator.statement', ['op' => $op, 's' => $this->operators->statement($op, $request->query('from'), $request->query('to'))]);
    }

    public function company(Request $request)
    {
        return view('operator.company', ['op' => $this->op($request)]);
    }

    public function updateCompany(OperatorRequest $request)
    {
        $op = $this->op($request);
        $data = collect($request->validated())->except(['credit_limit', 'payment_terms_days', 'notes', 'logo'])->all();
        if ($request->hasFile('logo')) $data['logo_path'] = UploadService::image($request->file('logo'), 'operators');
        $op->fill($data);
        AuditService::logChanges('operators', $op, 'portal_profile_updated');
        $op->save();
        return back()->with('success', 'Company profile saved.');
    }

    private function op(Request $request): TourOperator
    {
        return $request->user()->tourOperator ?? abort(403, 'Your login is not linked to a company.');
    }

    private function own(Request $request, Booking $booking): void
    {
        abort_unless($booking->tour_operator_id === $this->op($request)->id, 404);
    }
}
