<?php

namespace App\Modules\Website\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PaymentIntent;
use App\Modules\Billing\Services\OnlinePaymentService;
use Illuminate\Http\Request;

/** Guests view their booking with reference + email (or the secret link in their email), download the voucher, and pay balances. */
class ManageBookingController extends Controller
{
    public function lookup()
    {
        return view('website.book.lookup');
    }

    public function find(Request $request)
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:20'], 'email' => ['required', 'email']]);
        $booking = Booking::where('reference', strtoupper(trim($data['reference'])))
            ->whereHas('guest', fn ($q) => $q->where('email', strtolower($data['email'])))->first();
        if (! $booking) {
            return back()->withInput()->withErrors(['reference' => 'We could not find a booking with that reference and email.']);
        }
        return redirect()->route('manage.show', $booking->manage_token);
    }

    public function show(Request $request, string $token)
    {
        $b = Booking::with(['guest', 'villas.villa.type.media', 'ratePlan', 'payments', 'invoices'])->where('manage_token', $token)->firstOrFail();
        $pending = $request->filled('pay') ? PaymentIntent::where('token', $request->query('pay'))->where('booking_id', $b->id)->where('status', 'pending')->first() : null;
        return view('website.book.manage', ['b' => $b, 'paid' => $b->paidTotal(), 'pending' => $pending]);
    }

    public function voucher(string $token)
    {
        $b = Booking::with(['guest', 'villas.villa.type', 'villas.guests', 'ratePlan', 'operator'])->where('manage_token', $token)->firstOrFail();
        abort_if(in_array($b->status, ['hold', 'expired', 'cancelled'], true), 404);
        return view('print.voucher', ['b' => $b]);
    }

    public function pay(Request $request, string $token, OnlinePaymentService $payments)
    {
        $b = Booking::with('guest')->where('manage_token', $token)->firstOrFail();
        abort_unless(in_array($b->status, ['hold', 'tentative', 'confirmed', 'checked_in'], true), 409);
        $existing = $request->filled('intent') ? PaymentIntent::where('token', $request->input('intent'))->where('booking_id', $b->id)->where('status', 'pending')->first() : null;
        $balance = round((float) $b->grand_total - $b->paidTotal(), 2);
        $intent = $existing ?? $payments->createIntent($b, $b->status === 'hold' ? (float) $b->deposit_due : $balance, $b->status === 'hold' ? 'deposit' : 'balance');
        return redirect()->away($payments->checkoutUrl($intent));
    }
}
