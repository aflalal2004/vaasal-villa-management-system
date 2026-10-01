<?php

namespace App\Modules\Channel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Channel\Contracts\ChannelManager;
use App\Modules\Channel\Services\OtaReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Inbound reservation webhooks from the channel manager (booking.new / modified / cancelled).
 * Responds 200 only after the reservation is committed or safely queued as a conflict,
 * so the channel manager retries anything we failed to store.
 */
class ChannelWebhookController extends Controller
{
    public function handle(Request $request, string $provider, ChannelManager $manager, OtaReservationService $ota)
    {
        if ($provider !== $manager->name() && ! ($provider === 'simulator' && $manager->name() === 'null')) {
            return response()->json(['message' => 'Unknown provider'], 404);
        }
        if (! $manager->verifyWebhook($request)) {
            Log::warning('Channel webhook rejected', ['provider' => $provider, 'ip' => $request->ip()]);
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $results = [];
        try {
            foreach ($manager->parseReservation($request->json()->all()) as $i => $r) {
                $r['event_id'] .= $i ? '-'.$i : '';
                $results[] = $ota->ingest($r, $provider);
            }
        } catch (\Throwable $e) {
            Log::error('Channel webhook processing failed', ['provider' => $provider, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Processing error — please retry'], 500);
        }
        return response()->json(['ok' => true, 'results' => $results]);
    }
}
