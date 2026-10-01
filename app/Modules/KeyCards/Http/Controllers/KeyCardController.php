<?php

namespace App\Modules\KeyCards\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Booking;
use App\Models\BookingVilla;
use App\Models\Employee;
use App\Models\KeyCard;
use App\Models\KeyCardAssignment;
use App\Models\LockBridge;
use App\Models\LockJob;
use App\Models\Villa;
use App\Modules\Core\Services\AuditService;
use App\Modules\KeyCards\Services\KeyCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KeyCardController extends Controller
{
    public function __construct(private KeyCardService $cards) {}

    public function index(Request $request)
    {
        $cards = KeyCard::with(['activeAssignment.villa', 'activeAssignment.guest', 'activeAssignment.employee'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('uid', 'like', '%'.$request->query('q').'%')->orWhere('card_number', 'like', '%'.$request->query('q').'%')))
            ->orderBy('type')->orderBy('card_number')->paginate(40)->withQueryString();
        return view('admin.keycards.index', [
            'cards' => $cards,
            'counts' => KeyCard::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status'),
            'employees' => Employee::where('status', 'active')->orderBy('first_name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->fullName().' · '.$e->department?->name]),
            'zones' => Villa::whereNotNull('zone')->distinct()->pluck('zone', 'zone'),
            'villas' => Villa::orderBy('sort_order')->get(),
            'jobs' => LockJob::with('assignment.card')->latest()->limit(8)->get(),
        ]);
    }

    public function show(KeyCard $card)
    {
        return view('admin.keycards.show', [
            'card' => $card->load(['assignments.villa', 'assignments.guest', 'assignments.employee', 'assignments.booking', 'assignments.issuer', 'assignments.jobs']),
            'logs' => AccessLog::with('villa')->where('card_uid', $card->uid)->latest('occurred_at')->limit(50)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'uid' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9:\- ]+$/'],
            'card_number' => ['nullable', 'string', 'max:40', 'unique:key_cards,card_number'],
            'type' => ['required', Rule::in(['guest', 'staff', 'housekeeping', 'zone', 'master', 'maintenance'])],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);
        $uid = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['uid']));
        if (KeyCard::where('uid', $uid)->exists()) {
            return back()->withErrors(['uid' => 'A card with UID '.$uid.' is already registered.'])->withInput();
        }
        $card = $this->cards->register($uid, $data['card_number'] ?? null, $data['type'], $data['notes'] ?? null);
        return back()->with('success', 'Card '.$card->uid.' registered.');
    }

    public function issue(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'booking_villa_id' => ['required', 'integer'],
            'uid' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9:\- ]+$/'],
            'issue_type' => ['required', Rule::in(['new', 'duplicate'])],
        ]);
        $bv = BookingVilla::where('booking_id', $booking->id)->findOrFail($data['booking_villa_id']);
        $a = $this->cards->issueForBooking($bv, $data['uid'], $data['issue_type']);
        return $this->result($a);
    }

    public function issueStaff(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'uid' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9:\- ]+$/'],
            'access_level' => ['required', Rule::in(['master', 'zone', 'housekeeping', 'maintenance'])],
            'zone' => ['nullable', 'required_if:access_level,zone', 'string', 'max:60'],
            'villa_ids' => ['nullable', 'array'], 'villa_ids.*' => ['exists:villas,id'],
            'valid_to' => ['required', 'date', 'after:now'],
        ]);
        $a = $this->cards->issueForEmployee(Employee::findOrFail($data['employee_id']), $data['uid'], $data['access_level'], $data['zone'] ?? null,
            Carbon::parse($data['valid_to'])->endOfDay(), $data['villa_ids'] ?? []);
        return $this->result($a);
    }

    public function revoke(Request $request, KeyCardAssignment $assignment)
    {
        $this->cards->revoke($assignment, $request->input('reason', 'Revoked by '.auth()->user()->name));
        return back()->with('success', 'Card '.$assignment->card->uid.' revoked.');
    }

    public function extend(Request $request, KeyCardAssignment $assignment)
    {
        $data = $request->validate(['valid_to' => ['required', 'date', 'after:now']]);
        $this->cards->extend($assignment, Carbon::parse($data['valid_to']));
        return back()->with('success', 'Validity extended to '.fmt_dt(Carbon::parse($data['valid_to'])).'.');
    }

    public function lost(Request $request, KeyCard $card)
    {
        $data = $request->validate(['replacement_uid' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9:\- ]+$/']]);
        $new = $this->cards->reportLost($card, $data['replacement_uid'] ?? null);
        return back()->with('success', 'Card '.$card->uid.' blocked as lost.'.($new ? ' Replacement '.$new->card->uid.' '.($new->status === 'active' ? 'encoded.' : 'queued for the encoder.') : ''));
    }

    public function block(Request $request, KeyCard $card)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $this->cards->block($card, $data['reason']);
        return back()->with('success', 'Card blocked.');
    }

    public function unblock(KeyCard $card)
    {
        $this->cards->unblock($card);
        return back()->with('success', 'Card returned to available stock.');
    }

    public function retry(LockJob $job)
    {
        abort_unless($job->status === 'failed' && $job->assignment, 409);
        $job->assignment->update(['status' => 'pending']);
        $new = $this->cards->dispatch($job->assignment, $job->action);
        return back()->with($new->status === 'failed' ? 'error' : 'success', $new->status === 'failed' ? 'Encoder failed again: '.$new->error : 'Job re-sent to the encoder.');
    }

    public function logs(Request $request)
    {
        $logs = AccessLog::with(['villa', 'card', 'employee', 'device'])
            ->when($request->filled('villa'), fn ($q) => $q->where('villa_id', $request->query('villa')))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->query('event')))
            ->when($request->filled('uid'), fn ($q) => $q->where('card_uid', 'like', '%'.$request->query('uid').'%'))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('occurred_at', $request->query('date')))
            ->latest('occurred_at')->paginate(40)->withQueryString();
        return view('admin.keycards.logs', ['logs' => $logs, 'villas' => Villa::orderBy('sort_order')->pluck('code', 'id')]);
    }

    public function bridge()
    {
        return view('admin.keycards.bridge', [
            'bridges' => LockBridge::all(),
            'pending' => LockJob::where('status', 'pending')->count(),
            'failed' => LockJob::with('assignment.card')->where('status', 'failed')->latest()->limit(10)->get(),
            'jobs' => LockJob::with('assignment.card', 'requester')->latest()->limit(25)->get(),
            'driver' => config('vaasal.locks.driver'),
        ]);
    }

    public function rotateToken(Request $request)
    {
        $bridge = LockBridge::firstOrFail();
        $token = Str::random(48);
        $bridge->update(['token_hash' => hash('sha256', $token)]);
        AuditService::log('keycards', 'bridge_token_rotated', $bridge);
        return back()->with('success', 'New Lock Bridge token generated. Copy it into lock-bridge/.env now — it is shown only once.')->with('bridge_token', $token);
    }

    /** Development aid: record a door event as if a lock had reported it (same engine as the RFID API). */
    public function simulateAccess(Request $request, \App\Modules\KeyCards\Services\RfidAccessService $access)
    {
        $data = $request->validate(['uid' => ['required', 'string', 'max:60'], 'villa_id' => ['required', 'exists:villas,id']]);
        $villa = Villa::findOrFail($data['villa_id']);
        $r = $access->scan($data['uid'], null, $villa, null, now(), 'simulator');
        return back()->with($r['granted'] ? 'success' : 'warning', $r['granted']
            ? 'Door '.$villa->code.' opened for '.($r['holder'] ?? 'card holder').'.'
            : 'Access denied at '.$villa->code.': '.$r['reason'].'.');
    }

    private function result(KeyCardAssignment $a)
    {
        if ($a->status === 'active') {
            return back()->with('success', 'Card '.$a->card->uid.' encoded and active until '.fmt_dt($a->valid_to).'.');
        }
        if ($a->status === 'failed') {
            return back()->with('error', 'Encoding failed: '.($a->jobs->last()?->error ?? 'encoder error').' Check the card is on the encoder and retry.');
        }
        return back()->with('info', 'Card queued — place it on the encoder. The Lock Bridge will confirm within a few seconds.');
    }
}
