<?php

namespace App\Modules\Billing\Gateways;

use App\Models\PaymentIntent;
use Illuminate\Http\Request;

/**
 * Hosted-checkout payment provider. The guest enters card details on the provider's page,
 * so raw card numbers never reach the Vaasal Villa server (PCI DSS SAQ-A scope).
 */
interface PaymentGateway
{
    public function name(): string;

    /** Create a provider checkout session and return the URL to redirect the guest to. */
    public function createCheckout(PaymentIntent $intent, string $successUrl, string $cancelUrl): string;

    /**
     * Verify and parse a provider webhook.
     * @return array{event_id:string, type:string, session_id:?string, paid:bool, gateway_ref:?string, amount:?float}|null
     */
    public function parseWebhook(Request $request): ?array;
}
