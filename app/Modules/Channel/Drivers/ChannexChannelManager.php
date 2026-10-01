<?php

namespace App\Modules\Channel\Drivers;

use App\Modules\Channel\Contracts\ChannelManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Channex.io adapter (https://docs.channex.io). Channex distributes ARI to Booking.com,
 * Agoda, Airbnb, Expedia and others, and sends booking revisions by webhook.
 * Room/rate codes are the Channex room_type_id / rate_plan_id configured in Channel mappings.
 */
class ChannexChannelManager implements ChannelManager
{
    public function name(): string
    {
        return 'channex';
    }

    public function isEnabled(): bool
    {
        return (bool) config('vaasal.channel.channex.api_key') && (bool) config('vaasal.channel.channex.property_id');
    }

    public function pushAvailability(array $rows): array
    {
        $values = array_map(fn ($r) => [
            'property_id' => config('vaasal.channel.channex.property_id'),
            'room_type_id' => $r['room_code'],
            'date' => $r['date'],
            'availability' => $r['available'],
        ], $rows);

        $response = Http::withHeaders(['user-api-key' => config('vaasal.channel.channex.api_key')])
            ->timeout(20)->acceptJson()
            ->post(rtrim(config('vaasal.channel.channex.base_url'), '/').'/availability', ['values' => $values]);

        return ['ok' => $response->successful(), 'message' => $response->successful() ? 'Pushed '.count($values).' rows' : 'HTTP '.$response->status().': '.$response->body()];
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) config('vaasal.channel.channex.webhook_secret');
        return $secret !== '' && hash_equals($secret, (string) $request->header('X-Channex-Secret', $request->query('secret')));
    }

    public function parseReservation(array $payload): array
    {
        // Channex booking revision: data.attributes { ota_reservation_code, status, arrival_date, departure_date, customer, rooms[] }
        $attr = $payload['payload'] ?? ($payload['data']['attributes'] ?? $payload);
        $action = match ($attr['status'] ?? 'new') {
            'cancelled' => 'cancelled',
            'modified' => 'modified',
            default => 'new',
        };
        $customer = $attr['customer'] ?? [];
        $channelCode = strtolower(str_replace([' ', '.'], ['_', '_'], $attr['ota_name'] ?? 'booking_com'));

        return array_map(fn ($room) => [
            'event_id' => (string) ($payload['id'] ?? $attr['revision_id'] ?? md5(json_encode($payload))),
            'action' => $action,
            'channel_code' => $channelCode === 'booking_com' || $channelCode === 'bookingcom' ? 'booking_com' : $channelCode,
            'external_ref' => (string) ($attr['ota_reservation_code'] ?? $attr['booking_id']),
            'room_code' => $room['room_type_id'] ?? null,
            'rate_code' => $room['rate_plan_id'] ?? null,
            'arrival' => $room['checkin_date'] ?? $attr['arrival_date'],
            'departure' => $room['checkout_date'] ?? $attr['departure_date'],
            'adults' => (int) ($room['occupancy']['adults'] ?? 2),
            'children' => (int) ($room['occupancy']['children'] ?? 0),
            'amount' => (float) ($room['amount'] ?? 0),
            'guest' => [
                'first_name' => $customer['name'] ?? 'OTA',
                'last_name' => $customer['surname'] ?? 'Guest',
                'email' => $customer['mail'] ?? null,
                'phone' => $customer['phone'] ?? null,
                'country' => $customer['country'] ?? null,
            ],
            'notes' => $attr['notes'] ?? null,
        ], $attr['rooms'] ?? []);
    }
}
