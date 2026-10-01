<?php

namespace App\Modules\Billing\Gateways;

use App\Models\PaymentIntent;
use App\Modules\Core\Exceptions\BusinessRuleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Checkout (hosted payment page) over Stripe's REST API — no SDK dependency.
 * Configure STRIPE_SECRET and STRIPE_WEBHOOK_SECRET; point the Stripe webhook to
 * POST /webhooks/payments/stripe (event: checkout.session.completed).
 */
class StripeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'stripe';
    }

    public function createCheckout(PaymentIntent $intent, string $successUrl, string $cancelUrl): string
    {
        $secret = config('vaasal.payments.stripe.secret');
        if (! $secret) {
            throw new BusinessRuleException('Online payment is not configured (STRIPE_SECRET missing).');
        }
        // Zero-decimal handling kept simple: Stripe expects the smallest currency unit.
        $amountMinor = (int) round($intent->amount * 100);

        $response = Http::asForm()->withToken($secret)->timeout(20)->post('https://api.stripe.com/v1/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $intent->token,
            'customer_email' => $intent->customer_email,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($intent->currency),
            'line_items[0][price_data][unit_amount]' => $amountMinor,
            'line_items[0][price_data][product_data][name]' => 'Vaasal Villa booking '.($intent->booking?->reference ?? ''),
            'metadata[intent]' => $intent->token,
        ]);

        if (! $response->successful()) {
            Log::error('Stripe checkout failed', ['status' => $response->status(), 'body' => $response->json('error.message')]);
            throw new BusinessRuleException('The payment provider is unavailable. Please try again in a moment.');
        }

        $intent->update(['gateway_session_id' => $response->json('id')]);
        return $response->json('url');
    }

    public function parseWebhook(Request $request): ?array
    {
        $secret = config('vaasal.payments.stripe.webhook_secret');
        $header = (string) $request->header('Stripe-Signature');
        $payload = $request->getContent();
        if (! $secret || ! $header) {
            return null;
        }

        $parts = collect(explode(',', $header))->mapWithKeys(function ($p) {
            [$k, $v] = array_pad(explode('=', $p, 2), 2, null);
            return [$k => $v];
        });
        $timestamp = (int) $parts->get('t');
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        if (! hash_equals($expected, (string) $parts->get('v1')) || abs(time() - $timestamp) > 300) {
            Log::warning('Stripe webhook signature rejected');
            return null;
        }

        $event = json_decode($payload, true);
        $object = $event['data']['object'] ?? [];
        return [
            'event_id' => $event['id'] ?? '',
            'type' => $event['type'] ?? '',
            'session_id' => $object['id'] ?? null,
            'paid' => ($event['type'] ?? '') === 'checkout.session.completed' && ($object['payment_status'] ?? '') === 'paid',
            'gateway_ref' => $object['payment_intent'] ?? ($object['id'] ?? null),
            'amount' => isset($object['amount_total']) ? $object['amount_total'] / 100 : null,
        ];
    }
}
