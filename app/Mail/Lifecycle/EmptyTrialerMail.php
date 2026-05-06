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

class EmptyTrialerMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public ?string $magicUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "It's been a while — come back and try Fynla again",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.empty-trialer',
            with: [
                'user' => $this->user,
                'magicUrl' => $this->magicUrl,
                'firstName' => $this->user->first_name ?: 'there',
            ],
        );
    }
}
