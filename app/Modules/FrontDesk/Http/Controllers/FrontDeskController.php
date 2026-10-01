<?php

namespace App\Modules\FrontDesk\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ChargeItem;
use App\Models\KeyCard;
use App\Models\PosOrder;
use App\Models\Setting;
use App\Models\Villa;
use App\Modules\Billing\Services\FolioService;
use App\Modules\Core\Services\AuditService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Booking\Services\AvailabilityService;
use App\Modules\FrontDesk\Http\Requests\CheckInRequest;
use App\Modules\FrontDesk\Services\CheckInService;
use App\Modules\FrontDesk\Services\CheckOutService;
use App\Modules\FrontDesk\Services\NightAuditService;
use App\Modules\FrontDesk\Services\RoomChargeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FrontDeskController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->toDateString();
        return view('admin.frontdesk.index', [
            'arrivals' => Booking::with(['guest', 'channel', 'operator', 'activeVillas.villa'])->where('arrival', '<=', $today)->whereIn('status', ['confirmed', 'tentative'])->orderBy('arrival')->get(),
            'inHouse' => Booking::with(['guest', 'channel', 'activeVillas.villa', 'folios'])->where('status', 'checked_in')->orderBy('departure')->get(),
            'departures' => Booking::with(['guest', 'activeVillas.villa'])->where('status', 'checked_in')->where('departure', '<=', $today)->get(),
            'upcoming' => Booking::with(['guest', 'activeVillas.villa'])->whereIn('status', ['confirmed', 'tentative'])->whereBetween('arrival', [now()->addDay()->toDateString(), now()->addDays(3)->toDateString()])->orderBy('arrival')->get(),
            'villas' => Villa::with('type')->where('is_active', true)->orderBy('sort_order')->get(),
            'lastAudit' => json_decode((string) Setting::get('night_audit_last'), true),
            'tab' => $request->query('tab', 'arrivals'),
        ]);
    }

    public function walkIn()
    {
        return redirect()->route('admin.bookings.create', ['source' => 'walk_in', 'arrival' => now()->toDateString(), 'departure' => now()->addDay()->toDateString()]);
    }

    public function checkInForm(Booking $booking, AvailabilityService $availability)
    {
        $booking->load(['guest', 'activeVillas.villa.type', 'activeVillas.guests', 'folios.payments', 'ratePlan', 'operator']);
        $alternatives = [];
        foreach ($booking->activeVillas as $bv) {
            $alternatives[$bv->id] = $availability->availableVillas(now(), $bv->departure, null, $bv->id)
                ->filter(fn ($v) => $v->occupancy_status === 'vacant')
                ->mapWithKeys(fn ($v) => [$v->id => $v->code.' — '.$v->name.' · '.\App\Models\Villa::HK_LABELS[$v->hk_status]])->all();
        }
        return view('admin.frontdesk.checkin', [
            'b' => $booking,
            'alternatives' => $alternatives,
            'paid' => $booking->paidTotal(),
            'freeCards' => KeyCard::where('status', 'available')->where('type', 'guest')->orderBy('card_number')->limit(6)->get(),
        ]);
    }

    public function checkIn(CheckInRequest $request, Booking $booking, CheckInService $service)
    {
        $data = $request->validated();
        if ($request->hasFile('id_document')) {
            $data['id_document'] = $request->file('id_document');
        }
        $data['allow_not_ready'] = $request->boolean('allow_not_ready') && $request->user()->hasPermission('housekeeping.manage|bookings.override_rate');
        $service->checkIn($booking, $data);
        $cards = $booking->cardAssignments()->with('card')->where('status', 'active')->get();
        return redirect()->route('admin.bookings.show', $booking)->with('success', 'Checked in. '.($cards->count() ? $cards->count().' key card(s) active: '.$cards->pluck('card.uid')->implode(', ') : 'No key card issued yet.'));
    }

    public function checkOutForm(Booking $booking, FolioService $folios, RoomChargeService $roomCharges)
    {
        abort_unless($booking->status === 'checked_in', 404);
        // Post any room nights still owed (up to departure, or today for an early departure) so the balance shown is final.
        $until = $booking->departure->lte(now()) ? $booking->departure : now()->startOfDay()->max($booking->arrival->copy()->addDay());
        $unposted = $roomCharges->postNights($booking, $until);
        $booking->load(['guest', 'activeVillas.villa', 'folios.lines.poster', 'folios.payments', 'folios.invoices', 'cardAssignments.card', 'operator', 'channel', 'events']);
        return view('admin.frontdesk.checkout', [
            'b' => $booking,
            'summaries' => $booking->folios->mapWithKeys(fn ($f) => [$f->id => $folios->summary($f)]),
            'chargeItems' => ChargeItem::where('is_active', true)->orderBy('department')->get(),
            'openChecks' => PosOrder::with('outlet', 'table')->where('booking_id', $booking->id)->whereIn('status', ['open', 'billed'])->get(),
            'unposted' => max(0, $unposted),
            'early' => $booking->departure->gt(now()->startOfDay()),
        ]);
    }

    public function checkOut(Request $request, Booking $booking, CheckOutService $service)
    {
        $data = $request->validate([
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['nullable', Rule::in(['cash', 'card', 'bank_transfer'])],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'confirm_early' => ['nullable', 'boolean'],
        ]);
        if ($booking->departure->gt(now()->startOfDay()) && ! $request->boolean('confirm_early')) {
            return back()->with('error', 'This is an early departure. Tick “Confirm early departure” to release the remaining nights.');
        }
        $invoices = $service->checkOut($booking, ['payments' => $data['payments'] ?? []]);
        $msg = 'Checked out. Key cards revoked, villa set to Dirty and a housekeeping task created.';
        return redirect()->route('admin.bookings.show', $booking)->with('success', $msg.($invoices ? ' Final invoice(s): '.collect($invoices)->pluck('number')->implode(', ').'.' : ''));
    }

    public function nightAudit(NightAuditService $audit)
    {
        $r = $audit->run(now());
        return back()->with('success', "Night audit complete for {$r['business_date']}: {$r['room_nights']} room night(s) posted, {$r['no_shows']} no-show(s), {$r['holds_expired']} hold(s) expired, {$r['cards_expired']} card(s) expired, {$r['stayovers']} stay-over task(s).");
    }
    /**
     * Integration point PMS → POS: reception cannot operate the restaurant POS, so it asks the restaurant to
     * settle (pay or charge to the villa) every open check linked to this stay. POS cashiers get a notification
     * that opens each check directly.
     */
    public function requestRestaurantSettlement(Booking $booking)
    {
        $open = PosOrder::with(['outlet', 'table'])->where('booking_id', $booking->id)->whereIn('status', ['open', 'billed'])->get();
        if ($open->isEmpty()) {
            return back()->with('info', 'There are no open restaurant checks for this stay.');
        }
        foreach ($open as $o) {
            NotificationService::notify('pos.settlement_request', "Settle check {$o->order_no} — guest checking out",
                $booking->guest->fullName().' · Villa '.$booking->activeVillas->pluck('villa.code')->implode(', ').' · '.$o->outlet->name.($o->table ? ' table '.$o->table->name : '').' · '.money($o->total),
                route('pos.order', $o), 'warning', 'pos.bill');
        }
        AuditService::log('frontdesk', 'restaurant_settlement_requested', $booking, $open->pluck('order_no')->implode(', '));
        return back()->with('success', 'The restaurant has been asked to settle '.$open->count().' check(s). Refresh this page once they are closed or charged to the villa.');
    }
}