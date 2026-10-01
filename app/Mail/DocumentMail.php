<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Emails any printable document (invoice, receipt, statement) as an HTML message. */
class DocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $subjectLine, public string $documentView, public array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine.' — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.document', with: ['documentView' => $this->documentView, 'data' => $this->data]);
    }
}
