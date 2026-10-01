<?php

namespace LockBridge;

/**
 * Contract every lock-vendor adapter implements. Mirrors the web app's
 * App\Modules\KeyCards\Contracts\LockProvider, plus audit-trail collection.
 *
 * $payload keys: card_uid, lock_refs[], valid_from (ISO-8601), valid_to (ISO-8601),
 *                access_level (guest|zone|master|housekeeping|maintenance), zone, holder, villa_code
 * Return: ['success' => bool, 'message' => string, 'card_uid' => ?string, 'encoder' => ?string]
 */
interface LockAdapter
{
    public function name(): string;

    /** Encode a card as a NEW key (invalidates earlier guest cards for the same lock). */
    public function encodeNew(array $payload): array;

    /** Encode an additional card with the same access as the current key. */
    public function encodeDuplicate(array $payload): array;

    /** Cancel a card (online locks) or mark it for cancellation (offline locks). */
    public function revoke(array $payload): array;

    /** Change the validity end time. */
    public function extend(array $payload): array;

    /** Read the card currently on the encoder (returns its UID). */
    public function readCard(array $payload): array;

    /**
     * Door events since the last call (online locks / imported audit trails).
     * @return array<int, array{lock_ref:string, card_uid:?string, event:string, occurred_at:string, source?:string, details?:array}>
     */
    public function fetchEvents(): array;
}
