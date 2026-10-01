<?php

namespace App\Modules\FrontDesk\Services;

use App\Models\Booking;
use App\Models\FolioLine;
use App\Modules\Billing\Services\FolioService;
use Illuminate\Support\Carbon;

/**
 * Posts room-night charges to the folio (night audit and checkout top-up). Idempotent:
 * a night is posted once per booking line (source_type=room_night, source_id=booking_villa_id).
 */
class RoomChargeService
{
    public function __construct(private FolioService $folios) {}

    /** Post every unposted night strictly before $until (exclusive). Returns lines posted. */
    public function postNights(Booking $booking, Carbon|string $until): int
    {
        $until = Carbon::parse($until)->toDateString();
        $folio = $this->folios->folioFor($booking, 'room');
        $factor = (float) $booking->room_total > 0 ? ((float) $booking->room_total - (float) $booking->discount_total) / (float) $booking->room_total : 1;
        $posted = 0;

        foreach ($booking->activeVillas()->with('villa')->get() as $bv) {
            $already = FolioLine::where('source_type', 'room_night')->where('source_id', $bv->id)
                ->whereNull('reverses_id')->where('is_reversed', false)
                ->pluck('business_date')->map(fn ($d) => $d->toDateString())->all();

            foreach ($bv->nightly_rates as $date => $rate) {
                if ($date >= $until || in_array($date, $already, true)) continue;
                $net = round((float) $rate * $factor, 2);
                $this->folios->post($folio, 'room', "Villa {$bv->villa->code} — {$bv->villa->name} · night of ".Carbon::parse($date)->format('d M Y'),
                    1, $net, null, 'room_night', $bv->id, applyTax: true, applyService: true, businessDate: $date);
                $posted++;
            }
        }
        return $posted;
    }
}
