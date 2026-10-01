<?php

namespace LockBridge\Adapters;

use LockBridge\LockAdapter;

/**
 * Template for a real lock system. Copy to e.g. VisionlineAdapter.php / AmbianceAdapter.php /
 * SaltoSpaceAdapter.php once the installed lock brand and its PMS-interface SDK are confirmed.
 *
 * Typical integration styles (the vendor decides which one is available):
 *  - TCP/IP "PMS interface" to the vendor lock server (e.g. Visionline, Salto SHIP, dormakaba FIAS-style)
 *  - Vendor DLL / COM SDK called on the encoder PC (use a small .NET helper exe and call it via proc_open)
 *  - Vendor REST API on the local lock server
 *
 * Until it is implemented this adapter fails loudly, so no job is ever reported as done by mistake.
 */
class VendorSdkAdapter implements LockAdapter
{
    public function __construct(private array $config) {}

    public function name(): string
    {
        return $this->config['LOCK_VENDOR'] ?? 'vendor';
    }

    public function encodeNew(array $payload): array
    {
        // Example for a TCP PMS-interface:
        //   $cmd = sprintf("CI|%s|%s|%s|NEW", $payload['lock_refs'][0], date('YmdHi', strtotime($payload['valid_from'])), date('YmdHi', strtotime($payload['valid_to'])));
        //   $reply = $this->send($cmd);   // wait for the operator to place a card on the encoder
        //   return ['success' => str_starts_with($reply, 'OK'), 'message' => $reply, 'card_uid' => $this->parseUid($reply), 'encoder' => $this->config['ENCODER_ID'] ?? null];
        return $this->notConfigured();
    }

    public function encodeDuplicate(array $payload): array
    {
        return $this->notConfigured();
    }

    public function revoke(array $payload): array
    {
        return $this->notConfigured();
    }

    public function extend(array $payload): array
    {
        return $this->notConfigured();
    }

    public function readCard(array $payload): array
    {
        return $this->notConfigured();
    }

    public function fetchEvents(): array
    {
        // Poll the lock server's audit trail / event stream and map to:
        // ['lock_ref' => 'LOCK-101', 'card_uid' => '04A1...', 'event' => 'open'|'denied'|'low_battery', 'occurred_at' => ISO-8601, 'source' => 'online']
        return [];
    }

    private function notConfigured(): array
    {
        return ['success' => false, 'message' => 'Lock vendor adapter "'.$this->name().'" is not implemented yet. See lock-bridge/README.md.', 'card_uid' => null];
    }
}
