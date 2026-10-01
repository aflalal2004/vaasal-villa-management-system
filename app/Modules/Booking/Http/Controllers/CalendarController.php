<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Villa;
use App\Models\VillaBlock;
use App\Modules\Booking\Services\AvailabilityService;
use App\Modules\Channel\Services\ChannelSyncService;
use App\Modules\Core\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Central availability calendar: villas × nights across every channel. */
class CalendarController extends Controller
{
    public function index(Request $request, AvailabilityService $availability)
    {
        $days = (int) $request->query('days', 21);
        $days = in_array($days, [14, 21, 31, 45], true) ? $days : 21;
        $from = Carbon::parse($request->query('from', now()->subDays(2)->toDateString()))->startOfDay();

        return view('admin.bookings.calendar', [
            'from' => $from,
            'days' => $days,
            'dates' => collect(range(0, $days - 1))->map(fn ($i) => $from->copy()->addDays($i)),
            'villas' => Villa::with('type')->where('is_active', true)->orderBy('sort_order')->get(),
            'grid' => $availability->calendar($from, $days),
            'matrix' => $availability->availabilityMatrix($from, $from->copy()->addDays($days - 1)),
            'types' => \App\Models\VillaType::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function block(Request $request, AvailabilityService $availability, ChannelSyncService $channel)
    {
        $data = $request->validate([
            'villa_id' => ['required', 'exists:villas,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'reason' => ['required', 'in:maintenance,owner,other'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);
        $block = $availability->block(Villa::findOrFail($data['villa_id']), $data['start_date'], $data['end_date'], $data['reason'], $data['notes'] ?? null);
        AuditService::log('calendar', 'blocked', $block, $block->villa->code.' '.$data['start_date'].' → '.$data['end_date']);
        $channel->queueAri($data['start_date'], $data['end_date']);
        return back()->with('success', 'Villa blocked; channels updated.');
    }

    public function unblock(VillaBlock $block, AvailabilityService $availability, ChannelSyncService $channel)
    {
        [$s, $e] = [$block->start_date, $block->end_date];
        AuditService::log('calendar', 'unblocked', $block, $block->villa->code);
        $availability->unblock($block);
        $channel->queueAri($s, $e);
        return back()->with('success', 'Block removed; nights are sellable again.');
    }
}
