<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PaymentIntent;
use App\Models\WebhookInbox;
use App\Modules\Billing\Gateways\StripeGateway;
use App\Modules\Billing\Services\OnlinePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Payment provider webhooks. Signature is verified by the gateway adapter; events are
 * de-duplicated in webhook_inbox and completion is idempotent.
 */
class WebhookController extends Controller
{
    public function payment(Request $request, string $provider, OnlinePaymentService $payments)
    {
        $gateway = match ($provider) {
            'stripe' => new StripeGateway(),
            default => abort(404),
        };
        $event = $gateway->parseWebhook($request);
        if (! $event) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $inbox = WebhookInbox::firstOrCreate(['provider' => $provider, 'event_id' => $event['event_id']], ['event_type' => $event['type'], 'payload' => $request->json()->all()]);
        if ($inbox->processed_at) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        try {
            if ($event['paid'] && $event['session_id']) {
                $intent = PaymentIntent::where('gateway_session_id', $event['session_id'])->first();
                if ($intent) {
                    $payments->complete($intent, $event['gateway_ref']);
                }
            }
            $inbox->update(['processed_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Payment webhook failed', ['provider' => $provider, 'error' => $e->getMessage()]);
            $inbox->update(['error' => mb_substr($e->getMessage(), 0, 500)]);
            return response()->json(['message' => 'Processing error'], 500); // provider will retry
        }
        return response()->json(['ok' => true]);
    }
}
