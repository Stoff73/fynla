<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CancelledTrialerMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public array $feedbackUrls = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: 'Sorry to see you go — what could we have done better?',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.cancelled-trialer',
            with: [
                'firstName' => $this->user->first_name ?: 'there',
                'feedbackUrls' => $this->feedbackUrls,
            ],
        );
    }
}
