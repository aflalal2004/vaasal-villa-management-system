<?php

namespace App\Modules\KeyCards\Providers;

use App\Modules\KeyCards\Contracts\LockProvider;

/**
 * Development stand-in for a real encoder. Always succeeds (unless the UID starts with "FAIL",
 * which lets testers exercise the failure path).
 */
class SimulatorLockProvider implements LockProvider
{
    public function encodeNew(array $payload): array
    {
        return $this->result($payload, 'Card encoded (new key — previous cards for these locks are invalidated).');
    }

    public function encodeDuplicate(array $payload): array
    {
        return $this->result($payload, 'Duplicate card encoded.');
    }

    public function revoke(array $payload): array
    {
        return $this->result($payload, 'Card access revoked.');
    }

    public function extend(array $payload): array
    {
        return $this->result($payload, 'Card validity extended to '.($payload['valid_to'] ?? '?').'.');
    }

    public function readCard(array $payload): array
    {
        return ['success' => true, 'message' => 'Card read', 'card_uid' => $payload['card_uid'] ?? strtoupper(bin2hex(random_bytes(4)))];
    }

    private function result(array $payload, string $message): array
    {
        if (str_starts_with(strtoupper((string) ($payload['card_uid'] ?? '')), 'FAIL')) {
            return ['success' => false, 'message' => 'Encoder error: card not detected (simulated failure).', 'card_uid' => $payload['card_uid'] ?? null];
        }
        return ['success' => true, 'message' => $message, 'card_uid' => $payload['card_uid'] ?? null];
    }
}
