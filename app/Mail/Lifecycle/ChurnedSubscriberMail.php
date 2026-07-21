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

class ChurnedSubscriberMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public array $feedbackUrls = [],
        public ?string $subscriptionDuration = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "Thank you for being a Fynla subscriber — we'd love your feedback",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.churned-subscriber',
            with: [
                'firstName' => $this->user->first_name ?: 'there',
                'feedbackUrls' => $this->feedbackUrls,
                'subscriptionDuration' => $this->subscriptionDuration,
            ],
        );
    }
}
