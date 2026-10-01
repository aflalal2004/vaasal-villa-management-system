<?php

namespace App\Modules\POS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Outlet;
use App\Models\PosTable;
use App\Models\TableReservation;
use App\Modules\POS\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Restaurant POS → Reservations. */
class ReservationController extends Controller
{
    public function __construct(private ReservationService $reservations) {}

    public function index(Request $request)
    {
        $date = Carbon::parse($request->query('date', now()->toDateString()));
        // Seated parties whose check has been settled are completed automatically.
        TableReservation::where('status', 'seated')->whereHas('order', fn ($q) => $q->whereIn('status', ['paid', 'charged_to_room', 'void', 'refunded']))->update(['status' => 'completed']);

        $list = TableReservation::with(['table', 'outlet', 'booking.guest', 'order'])->whereDate('reserved_for', $date)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('reserved_for')->get();
        return view('pos.reservations', [
            'date' => $date, 'list' => $list,
            'counts' => TableReservation::whereDate('reserved_for', $date)->selectRaw('status, COUNT(*) n, SUM(party_size) pax')->groupBy('status')->get()->keyBy('status'),
            'outlets' => Outlet::where('is_active', true)->where('type', '!=', 'room_service')->pluck('name', 'id'),
            'tables' => PosTable::with('outlet')->where('is_active', true)->orderBy('outlet_id')->orderBy('sort_order')->get()
                ->mapWithKeys(fn ($t) => [$t->id => $t->outlet->name.' · '.$t->name.' ('.$t->seats.' seats)']),
            'inHouse' => Booking::with(['guest', 'activeVillas.villa'])->where('status', 'checked_in')->get()
                ->mapWithKeys(fn ($b) => [$b->id => $b->activeVillas->pluck('villa.code')->implode(', ').' · '.$b->guest->fullName()]),
            'edit' => $request->filled('edit') ? TableReservation::find($request->query('edit')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $r = $this->reservations->save($this->validated($request));
        return redirect()->route('pos.reservations.index', ['date' => $r->reserved_for->toDateString()])->with('success', 'Reservation saved for '.$r->guest_name.'.');
    }

    public function update(Request $request, TableReservation $reservation)
    {
        abort_unless(in_array($reservation->status, ['pending', 'confirmed'], true), 409, 'Only pending or confirmed reservations can be edited.');
        $r = $this->reservations->save($this->validated($request), $reservation);
        return redirect()->route('pos.reservations.index', ['date' => $r->reserved_for->toDateString()])->with('success', 'Reservation updated.');
    }

    public function status(Request $request, TableReservation $reservation)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(TableReservation::STATUSES))]]);
        $r = $this->reservations->setStatus($reservation, $data['status']);
        if ($r->status === 'seated' && $r->pos_order_id) {
            return redirect()->route('pos.order', $r->pos_order_id)->with('success', $r->guest_name.' seated at table '.$r->table->name.'. Check opened.');
        }
        return back()->with('success', $r->guest_name.': '.TableReservation::STATUSES[$r->status].'.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'outlet_id' => ['required', 'exists:outlets,id'],
            'pos_table_id' => ['nullable', 'exists:pos_tables,id'],
            'booking_id' => ['nullable', 'exists:bookings,id'],
            'guest_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 +()-]+$/'],
            'party_size' => ['required', 'integer', 'min:1', 'max:40'],
            'reserved_for' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:30', 'max:360'],
            'status' => ['nullable', Rule::in(['pending', 'confirmed'])],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);
    }
}
