<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WeHaventSeenYouMail extends LifecycleMail
{
    public function __construct(
        public string $firstName,
    ) {}

    protected function utmCampaign(): string
    {
        return 'we-havent-seen-you';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "We haven't seen you, {$this->firstName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.we-havent-seen-you',
            with: [
                'firstName'                  => $this->firstName,
                'getMoreRecommendationsUrl'  => $this->utm('https://fynla.org/dashboard', 'get-more-recommendations-cta'),
                'subscribeUrl'               => $this->utm('https://fynla.org/subscribe', 'subscribe-retention-cta'),
            ],
        );
    }
}
