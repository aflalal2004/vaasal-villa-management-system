<?php

namespace App\Modules\KeyCards\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\Villa;
use App\Modules\KeyCards\Services\RfidAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * RFID reader API — hardware-ready, vendor-neutral.
 *
 * POST /api/v1/rfid/scan   Authorization: Bearer <device token>
 * { "identifier_type": "rfid", "identifier": "04AABBCC1122", "occurred_at": "2026-09-29T10:30:00+05:30" }
 * Optional for readers without a fixed door: "villa_code": "G1" and/or "zone": "Villa Area".
 *
 * 200 { "decision": "allow"|"deny", "granted": bool, "reason": "...", "holder": "...", "holder_type": "guest"|"staff",
 *       "target": "Villa G1", "attendance": {...}|null, "event_id": 123 }
 * The reader should open the relay only when "decision" is "allow". See docs/RFID.md.
 */
class RfidApiController extends Controller
{
    public function scan(Request $request, RfidAccessService $access)
    {
        $token = (string) $request->bearerToken();
        $device = $token !== '' ? AttendanceDevice::with('villa')->where('api_token_hash', hash('sha256', $token))->where('is_active', true)->first() : null;
        if (! $device) {
            return response()->json(['decision' => 'deny', 'message' => 'Unknown or inactive device.'], 401);
        }
        if ($device->mode === 'simulator' && ! config('vaasal.locks.rfid_simulator')) {
            return response()->json(['decision' => 'deny', 'message' => 'Simulator devices are disabled on this server.'], 403);
        }
        $data = $request->validate([
            'identifier_type' => ['required', 'in:rfid,nfc'],
            'identifier' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9:\- ]+$/'],
            'occurred_at' => ['nullable', 'date'],
            'villa_code' => ['nullable', 'string', 'max:20'],
            'zone' => ['nullable', 'string', 'max:60'],
        ]);
        $device->update(['last_seen_at' => now()]);

        $at = isset($data['occurred_at']) ? Carbon::parse($data['occurred_at'])->setTimezone(config('app.timezone')) : now();
        if ($at->gt(now()->addMinutes(5)) || $at->lt(now()->subDays(2))) {
            return response()->json(['decision' => 'deny', 'message' => 'Scan time outside the accepted window.'], 422);
        }
        // A reader bolted to a door always reports that door; only floating readers may name one.
        $villa = $device->villa ?? (! empty($data['villa_code']) ? Villa::where('code', $data['villa_code'])->first() : null);
        $zone = $device->zone ?: ($data['zone'] ?? null);

        $r = $access->scan($data['identifier'], $device, $villa, $zone, $at, $device->mode === 'simulator' ? 'simulator' : 'device');
        return response()->json([
            'decision' => $r['decision'], 'granted' => $r['granted'], 'reason' => $r['reason'],
            'holder' => $r['holder'], 'holder_type' => $r['holder_type'], 'target' => $r['target'],
            'attendance' => $r['attendance'], 'event_id' => $r['log_id'], 'device' => $device->name,
        ]);
    }
}
