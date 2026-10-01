<?php

namespace App\Modules\KeyCards\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\KeyCard;
use App\Models\LockBridge;
use App\Models\LockJob;
use App\Models\Villa;
use App\Modules\KeyCards\Services\KeyCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Machine API for the on-premise Lock Bridge (see /lock-bridge).
 * The bridge connects OUTBOUND over HTTPS, authenticates with a bearer token (stored hashed),
 * long-polls for encoder jobs, reports results and uploads lock audit trails.
 */
class LockBridgeApiController extends Controller
{
    public function heartbeat(Request $request)
    {
        $bridge = $this->bridge($request);
        $bridge->update(['last_seen_at' => now(), 'ip_address' => $request->ip(), 'version' => $request->input('version'), 'vendor' => $request->input('vendor', $bridge->vendor)]);
        return response()->json(['ok' => true, 'server_time' => now()->toIso8601String(), 'pending_jobs' => LockJob::where('status', 'pending')->count()]);
    }

    /** Claim up to N pending jobs atomically so two bridges never encode the same card. */
    public function jobs(Request $request)
    {
        $bridge = $this->bridge($request);
        $bridge->update(['last_seen_at' => now()]);
        $limit = min(10, max(1, (int) $request->query('limit', 5)));

        $jobs = DB::transaction(function () use ($bridge, $limit) {
            // Re-offer jobs a bridge picked but never answered (e.g. crashed) after 2 minutes.
            LockJob::where('status', 'processing')->where('picked_at', '<', now()->subMinutes(2))->update(['status' => 'pending']);
            $jobs = LockJob::where('status', 'pending')->orderBy('id')->lockForUpdate()->limit($limit)->get();
            foreach ($jobs as $job) {
                $job->update(['status' => 'processing', 'picked_at' => now(), 'lock_bridge_id' => $bridge->id, 'attempts' => $job->attempts + 1]);
            }
            return $jobs;
        });

        return response()->json(['jobs' => $jobs->map(fn ($j) => ['uuid' => $j->uuid, 'action' => $j->action, 'payload' => $j->payload, 'created_at' => $j->created_at->toIso8601String()])]);
    }

    public function result(Request $request, string $uuid, KeyCardService $cards)
    {
        $this->bridge($request);
        $data = $request->validate(['success' => ['required', 'boolean'], 'message' => ['nullable', 'string', 'max:500'], 'card_uid' => ['nullable', 'string', 'max:60'], 'encoder' => ['nullable', 'string', 'max:60']]);
        $job = LockJob::where('uuid', $uuid)->firstOrFail();
        if (in_array($job->status, ['completed', 'failed'], true)) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }
        $cards->completeJob($job, $data, 'online');
        return response()->json(['ok' => true, 'status' => $job->fresh()->status]);
    }

    /** Lock audit trail / real-time door events. */
    public function events(Request $request)
    {
        $this->bridge($request);
        $data = $request->validate([
            'events' => ['required', 'array', 'max:500'],
            'events.*.lock_ref' => ['required', 'string', 'max:60'],
            'events.*.card_uid' => ['nullable', 'string', 'max:60'],
            'events.*.event' => ['required', 'string', 'max:30'],
            'events.*.occurred_at' => ['required', 'date'],
            'events.*.source' => ['nullable', 'in:online,audit_import'],
            'events.*.details' => ['nullable', 'array'],
        ]);
        $villas = Villa::whereNotNull('lock_ref')->pluck('id', 'lock_ref');
        $n = 0;
        foreach ($data['events'] as $e) {
            $uid = isset($e['card_uid']) ? strtoupper($e['card_uid']) : null;
            AccessLog::create([
                'villa_id' => $villas[$e['lock_ref']] ?? null, 'lock_ref' => $e['lock_ref'], 'card_uid' => $uid,
                'key_card_id' => $uid ? KeyCard::where('uid', $uid)->value('id') : null, 'event' => $e['event'],
                'source' => $e['source'] ?? 'online', 'details' => $e['details'] ?? null, 'occurred_at' => Carbon::parse($e['occurred_at']),
            ]);
            $n++;
        }
        return response()->json(['ok' => true, 'stored' => $n]);
    }

    private function bridge(Request $request): LockBridge
    {
        $token = (string) $request->bearerToken();
        $bridge = $token !== '' ? LockBridge::where('token_hash', hash('sha256', $token))->where('is_active', true)->first() : null;
        abort_unless($bridge, 401, 'Invalid Lock Bridge token.');
        return $bridge;
    }
}
