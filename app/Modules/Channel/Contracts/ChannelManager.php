<?php

namespace App\Modules\Channel\Contracts;

use Illuminate\Http\Request;

/**
 * Channel manager (SiteMinder, Channex, …) adapter. OTAs (Booking.com, Agoda, Airbnb, Expedia)
 * are reached through ONE certified channel manager rather than separate direct integrations.
 */
interface ChannelManager
{
    public function name(): string;

    public function isEnabled(): bool;

    /**
     * Push availability counts. $rows = [ ['room_code'=>, 'rate_code'=>?, 'date'=>Y-m-d, 'available'=>int, 'rate'=>?float], … ]
     * @return array{ok:bool, message:string}
     */
    public function pushAvailability(array $rows): array;

    /** Verify webhook authenticity. */
    public function verifyWebhook(Request $request): bool;

    /**
     * Normalise a provider reservation payload to:
     * [event_id, action(new|modified|cancelled), channel_code, external_ref, room_code, rate_code,
     *  arrival, departure, adults, children, amount, guest=>[first_name,last_name,email,phone,country], notes]
     * @return array[] one entry per room in the reservation
     */
    public function parseReservation(array $payload): array;
}
