<?php

namespace App\Modules\KeyCards\Contracts;

/**
 * Vendor-neutral door-lock / card-encoder interface.
 *
 * The same contract is implemented by the on-premise Lock Bridge adapters (lock-bridge/src/Adapters)
 * for VingCard / dormakaba / Salto / Onity etc. once the lock brand and SDK are confirmed.
 * In development, LOCK_DRIVER=simulator processes jobs immediately inside the web app.
 *
 * Every method receives the lock job payload:
 *   card_uid, lock_refs[], valid_from, valid_to, access_level, zone, guest_name, villa_code
 * and returns ['success' => bool, 'message' => string, 'card_uid' => ?string].
 */
interface LockProvider
{
    public function encodeNew(array $payload): array;

    public function encodeDuplicate(array $payload): array;

    public function revoke(array $payload): array;

    public function extend(array $payload): array;

    public function readCard(array $payload): array;
}
