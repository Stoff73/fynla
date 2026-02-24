<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DataDeletionConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $firstName,
        public string $email
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: 'Your Fynla data has been permanently deleted',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.data-deletion-confirmation',
            with: [
                'firstName' => $this->firstName,
                'email' => $this->email,
            ],
        );
    }
}
