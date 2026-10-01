<?php

namespace App\Modules\Channel\Drivers;

use App\Modules\Channel\Contracts\ChannelManager;
use Illuminate\Http\Request;

/**
 * Used until a channel manager account is configured (CHANNEL_DRIVER=null).
 * ARI updates are still computed and logged so the queue can be replayed later, and the
 * built-in simulator (Admin → Channels → Simulate OTA booking) posts reservations in the
 * generic format below so the full ingest / conflict-queue flow can be tested locally.
 */
class NullChannelManager implements ChannelManager
{
    public function name(): string
    {
        return 'null';
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function pushAvailability(array $rows): array
    {
        return ['ok' => true, 'message' => 'Channel manager not configured — '.count($rows).' ARI rows logged only.'];
    }

    public function verifyWebhook(Request $request): bool
    {
        // Local simulator requests carry the bridge/app token.
        return hash_equals((string) config('vaasal.locks.bridge_token'), (string) $request->header('X-Simulator-Token'));
    }

    public function parseReservation(array $payload): array
    {
        $rooms = $payload['rooms'] ?? [$payload];
        return array_map(fn ($room) => [
            'event_id' => (string) ($payload['event_id'] ?? ($payload['external_ref'].'-'.($payload['action'] ?? 'new').'-'.md5(json_encode($payload)))),
            'action' => $payload['action'] ?? 'new',
            'channel_code' => $payload['channel_code'] ?? 'booking_com',
            'external_ref' => (string) $payload['external_ref'],
            'room_code' => $room['room_code'] ?? null,
            'rate_code' => $room['rate_code'] ?? null,
            'arrival' => $payload['arrival'],
            'departure' => $payload['departure'],
            'adults' => (int) ($room['adults'] ?? $payload['adults'] ?? 2),
            'children' => (int) ($room['children'] ?? $payload['children'] ?? 0),
            'amount' => (float) ($room['amount'] ?? $payload['amount'] ?? 0),
            'guest' => $payload['guest'] ?? [],
            'notes' => $payload['notes'] ?? null,
        ], $rooms);
    }
}
