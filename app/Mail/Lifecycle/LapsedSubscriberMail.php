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

class LapsedSubscriberMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public ?string $updatePaymentUrl = null,
        public array $feedbackUrls = [],
        public ?string $gracePeriodEnd = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "Your Fynla payment didn't go through — let's get you back on track",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.lapsed-subscriber',
            with: [
                'firstName' => $this->user->first_name ?: 'there',
                'updatePaymentUrl' => $this->updatePaymentUrl,
                'feedbackUrls' => $this->feedbackUrls,
                'gracePeriodEnd' => $this->gracePeriodEnd,
            ],
        );
    }
}
