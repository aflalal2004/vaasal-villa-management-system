<?php

namespace App\Modules\Billing\Gateways;

use App\Models\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Local development gateway. It imitates a hosted provider page (approve / decline) and
 * never asks for card data. Replace with PAYMENT_DRIVER=stripe (or a regional gateway) in production.
 */
class SandboxGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'sandbox';
    }

    public function createCheckout(PaymentIntent $intent, string $successUrl, string $cancelUrl): string
    {
        $intent->update(['gateway_session_id' => 'sbx_'.Str::random(24), 'payload' => ['success_url' => $successUrl, 'cancel_url' => $cancelUrl]]);
        return route('pay.sandbox', $intent->token);
    }

    public function parseWebhook(Request $request): ?array
    {
        return null; // Sandbox completes synchronously from its own page.
    }
}
