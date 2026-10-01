<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Booking confirmation with voucher details and the guest's manage-booking link. */
class BookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your stay at Vaasal Villa is confirmed — '.$this->booking->reference);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.booking-confirmation', with: ['b' => $this->booking, 'paid' => $this->booking->paidTotal()]);
    }
}
