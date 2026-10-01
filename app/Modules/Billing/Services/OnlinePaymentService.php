<?php

namespace App\Modules\Billing\Services;

use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Models\PaymentIntent;
use App\Modules\Billing\Gateways\PaymentGateway;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Online payments for website bookings, operator deposits and balance payment links.
 * Completion is idempotent: a webhook and a browser return can both arrive safely.
 */
class OnlinePaymentService
{
    public function __construct(
        private PaymentGateway $gateway,
        private FolioService $folios,
        private InvoiceService $invoices,
        private BookingService $bookings,
    ) {}

    public function createIntent(Booking $booking, float $amount, string $purpose = 'deposit'): PaymentIntent
    {
        if ($amount <= 0) {
            throw new BusinessRuleException('Nothing to pay for this booking.');
        }
        return PaymentIntent::create([
            'token' => Str::random(48),
            'booking_id' => $booking->id,
            'amount' => round($amount, 2),
            'currency' => $booking->currency,
            'purpose' => $purpose,
            'gateway' => $this->gateway->name(),
            'customer_email' => $booking->guest->email,
            'expires_at' => $booking->hold_expires_at ?? now()->addDays(3),
        ]);
    }

    public function checkoutUrl(PaymentIntent $intent): string
    {
        return $this->gateway->createCheckout($intent, route('pay.return', $intent->token), route('pay.cancel', $intent->token));
    }

    public function complete(PaymentIntent $intent, ?string $gatewayRef = null): PaymentIntent
    {
        $sendEmail = false;
        DB::transaction(function () use ($intent, $gatewayRef, &$sendEmail) {
            $intent = PaymentIntent::whereKey($intent->id)->lockForUpdate()->first();
            if ($intent->status === 'paid') {
                return; // already processed (idempotent)
            }
            $booking = $intent->booking()->lockForUpdate()->first();
            // Money arrived after the hold lapsed: re-reserve the villas if still free, otherwise alert staff to refund.
            if ($booking->status === 'expired' && $this->bookings->reinstate($booking)) {
                $booking->refresh();
            } elseif (in_array($booking->status, ['expired', 'cancelled'], true)) {
                NotificationService::notify('payment.late', "Payment received for {$booking->status} booking {$booking->reference}",
                    'The villa is no longer available for these dates. Offer alternative dates or refund the guest.', route('admin.bookings.show', $booking), 'danger', 'payments.refund');
            }
            $folio = $this->folios->open($booking, 'guest');
            $payment = $this->folios->recordPayment($folio, 'online', (float) $intent->amount, $intent->purpose === 'deposit' ? 'deposit' : 'payment',
                'Online '.$intent->purpose, $intent->gateway, $gatewayRef ?? $intent->gateway_session_id);
            $intent->update(['status' => 'paid', 'paid_at' => now(), 'payment_id' => $payment->id]);
            $this->invoices->issueReceipt($payment);

            if (in_array($booking->status, ['hold', 'tentative'], true)) {
                $this->bookings->confirm($booking, 'Confirmed by online payment '.$payment->reference);
                $sendEmail = true;
            }
            $this->bookings->event($booking, 'payment', 'Online payment '.money($payment->amount).' received ('.$intent->gateway.')');
        });

        if ($sendEmail) {
            $this->sendConfirmation($intent->booking->fresh(['guest', 'villas.villa.type']));
        }
        return $intent->fresh();
    }

    public function fail(PaymentIntent $intent): void
    {
        if ($intent->status === 'pending') {
            $intent->update(['status' => 'failed']);
        }
    }

    public function sendConfirmation(Booking $booking): void
    {
        if (! $booking->guest->email) return;
        try {
            Mail::to($booking->guest->email)->send(new BookingConfirmationMail($booking));
            $this->bookings->event($booking, 'email', 'Confirmation & voucher emailed to '.$booking->guest->email);
        } catch (\Throwable $e) {
            Log::error('Confirmation email failed', ['booking' => $booking->reference, 'error' => $e->getMessage()]);
        }
    }
}
