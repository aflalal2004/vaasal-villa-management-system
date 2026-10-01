<?php

namespace App\Modules\Website\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PaymentIntent;
use App\Modules\Billing\Services\OnlinePaymentService;
use Illuminate\Http\Request;

/**
 * Browser round-trip for hosted payments. Card details are only ever entered on the provider's
 * page; this server receives the result by webhook (Stripe) or, for the local sandbox, by the
 * sandbox page's approve/decline button.
 */
class PublicPaymentController extends Controller
{
    public function sandbox(string $token)
    {
        abort_unless(config('vaasal.payments.driver') === 'sandbox', 404);
        $intent = PaymentIntent::with('booking.guest')->where('token', $token)->firstOrFail();
        return view('website.book.sandbox', ['intent' => $intent]);
    }

    public function sandboxComplete(Request $request, string $token, OnlinePaymentService $payments)
    {
        abort_unless(config('vaasal.payments.driver') === 'sandbox', 404);
        $intent = PaymentIntent::where('token', $token)->firstOrFail();
        if ($request->input('result') === 'approve') {
            $payments->complete($intent, 'sbx_'.bin2hex(random_bytes(6)));
            return redirect()->route('pay.return', $token);
        }
        $payments->fail($intent);
        return redirect()->route('pay.cancel', $token);
    }

    /** Provider success URL. With Stripe the webhook confirms; we show the current state. */
    public function return(string $token)
    {
        $intent = PaymentIntent::with('booking')->where('token', $token)->firstOrFail();
        if ($intent->status === 'paid') {
            $confirmed = in_array($intent->booking->status, ['confirmed', 'checked_in', 'checked_out'], true);
            return redirect()->route('book.confirmation', $intent->booking->manage_token)->with($confirmed ? 'success' : 'error', $confirmed
                ? 'Payment received — your booking is confirmed.'
                : 'Payment received, but your hold had expired and the villa is no longer free for these dates. Our reservations team will contact you within 24 hours to offer alternatives or a full refund.');
        }
        return view('website.book.pending', ['intent' => $intent]);
    }

    public function cancel(string $token)
    {
        $intent = PaymentIntent::with('booking.villas.villaType')->where('token', $token)->firstOrFail();
        return view('website.book.cancelled', ['intent' => $intent, 'retryUrl' => route('manage.show', $intent->booking->manage_token)]);
    }
}
