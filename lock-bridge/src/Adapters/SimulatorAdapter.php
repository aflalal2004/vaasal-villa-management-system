<?php

namespace LockBridge\Adapters;

use LockBridge\LockAdapter;

/** Behaves like an encoder that always works — for testing the bridge ↔ server loop without hardware. */
class SimulatorAdapter implements LockAdapter
{
    public function name(): string
    {
        return 'simulator';
    }

    public function encodeNew(array $payload): array
    {
        return $this->ok('Encoded new key for '.implode(',', $payload['lock_refs'] ?? []), $payload);
    }

    public function encodeDuplicate(array $payload): array
    {
        return $this->ok('Encoded duplicate', $payload);
    }

    public function revoke(array $payload): array
    {
        return $this->ok('Revoked', $payload);
    }

    public function extend(array $payload): array
    {
        return $this->ok('Extended to '.($payload['valid_to'] ?? '?'), $payload);
    }

    public function readCard(array $payload): array
    {
        return ['success' => true, 'message' => 'Read card', 'card_uid' => strtoupper(bin2hex(random_bytes(4))), 'encoder' => 'SIM-ENC-1'];
    }

    public function fetchEvents(): array
    {
        return [];
    }

    private function ok(string $message, array $payload): array
    {
        return ['success' => true, 'message' => $message, 'card_uid' => $payload['card_uid'] ?? null, 'encoder' => 'SIM-ENC-1'];
    }
}
