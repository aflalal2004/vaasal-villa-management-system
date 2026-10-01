<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BookingConflict;
use App\Models\Villa;
use App\Modules\Booking\Services\AvailabilityService;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Core\Services\AuditService;
use Illuminate\Http\Request;

/**
 * OTA conflict queue: channel reservations that could not be placed automatically.
 * Managers either assign a villa (creating the booking) or reject/relocate with notes.
 */
class ConflictController extends Controller
{
    public function index(Request $request, AvailabilityService $availability)
    {
        $status = $request->query('status', 'open');
        $conflicts = BookingConflict::with(['channel', 'villaType', 'booking', 'resolver'])->where('status', $status)->latest()->paginate(20)->withQueryString();
        $options = [];
        foreach ($conflicts as $c) {
            if ($c->status === 'open') {
                $options[$c->id] = $availability->availableVillas($c->arrival, $c->departure)->mapWithKeys(fn ($v) => [$v->id => $v->code.' — '.$v->name.' ('.$v->type->name.')'])->all();
            }
        }
        return view('admin.bookings.conflicts', ['conflicts' => $conflicts, 'options' => $options, 'status' => $status]);
    }

    public function assign(Request $request, BookingConflict $conflict, BookingService $bookings)
    {
        $data = $request->validate(['villa_id' => ['required', 'exists:villas,id'], 'notes' => ['nullable', 'string', 'max:500']]);
        abort_unless($conflict->status === 'open', 409);
        $p = $conflict->payload;
        $booking = $conflict->booking;

        if ($booking) {
            // Modification that did not fit: move to the chosen villa, then apply dates
            $bv = $booking->activeVillas()->first();
            $bookings->changeVilla($bv, Villa::findOrFail($data['villa_id']), 'OTA conflict resolution');
            $bookings->changeDates($booking->fresh(), $conflict->arrival->toDateString(), $conflict->departure->toDateString(), 'OTA modification (resolved)');
        } else {
            $booking = $bookings->create([
                'source' => 'ota', 'channel_code' => $conflict->channel->code, 'external_ref' => $conflict->external_ref,
                'guest' => ($p['guest'] ?? []) + ['first_name' => 'OTA', 'last_name' => 'Guest'],
                'arrival' => $conflict->arrival->toDateString(), 'departure' => $conflict->departure->toDateString(),
                'villas' => [['villa_id' => $data['villa_id'], 'adults' => $p['adults'] ?? 2, 'children' => $p['children'] ?? 0]],
                'status' => 'confirmed', 'payment_mode' => 'pay_at_property', 'special_requests' => $p['notes'] ?? null, 'ignore_rules' => true,
            ]);
        }
        $conflict->update(['status' => 'resolved', 'booking_id' => $booking->id, 'resolved_by' => auth()->id(), 'resolved_at' => now(), 'resolution_notes' => $data['notes'] ?? 'Villa assigned']);
        AuditService::log('channels', 'conflict_resolved', $conflict, 'Assigned to booking '.$booking->reference);
        return redirect()->route('admin.bookings.show', $booking)->with('success', 'Conflict resolved: booking '.$booking->reference.' created in the assigned villa.');
    }

    public function reject(Request $request, BookingConflict $conflict)
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:500']]);
        $conflict->update(['status' => 'rejected', 'resolved_by' => auth()->id(), 'resolved_at' => now(), 'resolution_notes' => $data['notes']]);
        AuditService::log('channels', 'conflict_rejected', $conflict, $data['notes']);
        return back()->with('success', 'Conflict closed. Remember to cancel or relocate the reservation in the OTA extranet if needed.');
    }
}
