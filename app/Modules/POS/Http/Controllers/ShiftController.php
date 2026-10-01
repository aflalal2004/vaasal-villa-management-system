<?php

namespace App\Modules\POS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Outlet;
use App\Models\PosOrder;
use App\Models\PosShift;
use App\Models\User;
use App\Modules\POS\Services\PosShiftService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Cashier shifts: open, drawer movements, day-end closing wizard, X/Z reports (A4 + thermal), manager review,
 * audited reopen, "Today" cash & bank summary and the cash-movement ledger.
 * Cashiers see and act on their own shifts; pos.reports sees all; pos.shift_review may review, reopen and close any shift.
 */
class ShiftController extends Controller
{
    public function __construct(private PosShiftService $shifts) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $q = PosShift::with(['outlet', 'user', 'reviewer'])->latest('opened_at')
            ->when(! $user->hasPermission('pos.reports'), fn ($q) => $q->where('user_id', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('review'), fn ($q) => $q->where('status', 'closed')->where('review_status', $request->query('review')))
            ->when($request->filled('outlet'), fn ($q) => $q->where('outlet_id', $request->query('outlet')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('opened_at', $request->query('date')));
        $mine = PosShift::with('outlet')->where('user_id', $user->id)->where('status', 'open')->get();
        return view('pos.shifts', [
            'shifts' => $q->paginate(20)->withQueryString(), 'mine' => $mine, 'outlets' => Outlet::where('is_active', true)->pluck('name', 'id'),
            'reports' => $mine->mapWithKeys(fn ($s) => [$s->id => $this->shifts->report($s)]),
            'pendingReview' => $user->hasPermission('pos.shift_review') ? PosShift::where('status', 'closed')->where('review_status', 'pending')->count() : 0,
        ]);
    }

    public function show(Request $request, PosShift $shift)
    {
        $this->authorizeView($request, $shift);
        return view('pos.shift-show', [
            'shift' => $shift->load(['outlet', 'user', 'closer', 'reviewer', 'reopener', 'movements.user']),
            'r' => $this->shifts->report($shift),
            'billedOpen' => PosOrder::where('pos_shift_id', $shift->id)->where('status', 'billed')->count(),
        ]);
    }

    public function open(Request $request)
    {
        $data = $request->validate(['outlet_id' => ['required', 'exists:outlets,id'], 'opening_float' => ['required', 'numeric', 'min:0', 'max:10000000'], 'notes' => ['nullable', 'string', 'max:300']]);
        $this->shifts->open(Outlet::findOrFail($data['outlet_id']), $request->user(), (float) $data['opening_float'], $data['notes'] ?? null);
        return back()->with('success', 'Shift opened. Float '.money($data['opening_float']).'.');
    }

    public function cash(Request $request, PosShift $shift)
    {
        $this->authorizeAct($request, $shift);
        $data = $request->validate([
            'type' => ['required', Rule::in(CashMovement::MANUAL)],
            'amount' => ['required', 'numeric', $request->input('type') === 'adjustment' ? 'not_in:0' : 'min:0.01', 'max:10000000'],
            'reason' => ['required', 'string', 'max:200'],
            'reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        abort_if($data['type'] === 'adjustment' && ! $request->user()->hasPermission('pos.shift_review'), 403, 'Only a manager can post drawer adjustments.');
        $this->shifts->cashMovement($shift, $data['type'], (float) $data['amount'], $data['reason'], $data['reference'] ?? null, $data['notes'] ?? null);
        return back()->with('success', CashMovement::TYPES[$data['type']][0].' of '.money(abs((float) $data['amount'])).' recorded.');
    }

    /** Day-end closing wizard: select shift → expected → count → variance → notes → confirm → print → manager review. */
    public function dayEnd(Request $request)
    {
        $user = $request->user();
        $candidates = PosShift::with(['outlet', 'user'])->where('status', 'open')
            ->when(! $user->hasPermission('pos.shift_review'), fn ($q) => $q->where('user_id', $user->id))->latest('opened_at')->get();
        $shift = $request->filled('shift') ? $candidates->firstWhere('id', (int) $request->query('shift')) : ($candidates->count() === 1 ? $candidates->first() : null);
        return view('pos.day-end', [
            'candidates' => $candidates, 'shift' => $shift, 'r' => $shift ? $this->shifts->report($shift) : null,
            'denominations' => PosShiftService::DENOMINATIONS,
            'billedOpen' => $shift ? PosOrder::where('pos_shift_id', $shift->id)->where('status', 'billed')->count() : 0,
        ]);
    }

    public function close(Request $request, PosShift $shift)
    {
        $this->authorizeAct($request, $shift);
        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:300'],
            'denominations' => ['nullable', 'array'], 'denominations.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Tick the box to confirm the drawer count before closing.']);
        $open = PosOrder::where('pos_shift_id', $shift->id)->where('status', 'billed')->count();
        $this->shifts->close($shift, (float) $data['counted_cash'], $data['notes'] ?? null, $data['denominations'] ?? []);
        return redirect()->route('pos.shifts.show', $shift)->with('success', 'Shift closed and locked. Print the closing report below.'.($open ? " Note: {$open} billed check(s) remain unpaid." : ''));
    }

    public function review(Request $request, PosShift $shift)
    {
        $data = $request->validate(['review_status' => ['required', Rule::in(['approved', 'flagged'])], 'review_notes' => ['nullable', 'required_if:review_status,flagged', 'string', 'max:500']]);
        abort_if($shift->user_id === $request->user()->id && ! $request->user()->isSuperAdmin(), 403, 'You cannot review your own shift.');
        $this->shifts->review($shift, $data['review_status'], $data['review_notes'] ?? null);
        return back()->with('success', 'Shift '.($data['review_status'] === 'approved' ? 'approved' : 'flagged for follow-up').'.');
    }

    public function reopen(Request $request, PosShift $shift)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']]);
        $this->shifts->reopen($shift, $data['reason']);
        return back()->with('success', 'Shift reopened. It must be counted and closed again.');
    }

    /** Printable closing report: A4 (default) or 80 mm thermal. */
    public function print(Request $request, PosShift $shift)
    {
        $this->authorizeView($request, $shift);
        $thermal = $request->query('format') === 'thermal';
        return view($thermal ? 'pos.print.shift-thermal' : 'pos.print.shift', [
            'shift' => $shift->load(['outlet', 'user', 'closer', 'reviewer', 'movements.user']), 'r' => $this->shifts->report($shift),
        ]);
    }

    /** Today's cash / bank summary (own shift for cashiers, all shifts and outlets for managers). */
    public function today(Request $request)
    {
        $date = $request->filled('date') ? Carbon::parse($request->query('date')) : now();
        return view('pos.today', $this->shifts->today($request->user(), $date, $request->integer('outlet') ?: null) + [
            'outlets' => Outlet::where('is_active', true)->pluck('name', 'id'),
            'mine' => $this->shifts->current($request->user()),
        ]);
    }

    /** Cash movement ledger across shifts (own for cashiers). */
    public function movements(Request $request)
    {
        $user = $request->user();
        $all = $user->hasPermission('pos.reports');
        $q = CashMovement::with(['shift.outlet', 'shift.user', 'user', 'order'])
            ->when(! $all, fn ($q) => $q->whereHas('shift', fn ($s) => $s->where('user_id', $user->id)))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('shift'), fn ($q) => $q->where('pos_shift_id', $request->query('shift')))
            ->when($request->filled('user') && $all, fn ($q) => $q->whereHas('shift', fn ($s) => $s->where('user_id', $request->query('user'))))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', Carbon::parse($request->query('from'))->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', Carbon::parse($request->query('to'))->endOfDay()))
            ->latest('id');
        $totals = (clone $q)->reorder()->selectRaw('type, SUM(amount) total, COUNT(*) n')->groupBy('type')->get()->keyBy('type');
        return view('pos.cash-movements', [
            'movements' => $q->paginate(40)->withQueryString(), 'totals' => $totals,
            'mine' => $this->shifts->current($user),
            'cashiers' => $all ? User::whereIn('id', PosShift::distinct()->pluck('user_id'))->orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    private function authorizeView(Request $request, PosShift $shift): void
    {
        abort_unless($shift->user_id === $request->user()->id || $request->user()->hasPermission('pos.reports|pos.shift_review'), 403);
    }

    private function authorizeAct(Request $request, PosShift $shift): void
    {
        abort_unless($shift->user_id === $request->user()->id || $request->user()->hasPermission('pos.shift_review'), 403);
    }
}
