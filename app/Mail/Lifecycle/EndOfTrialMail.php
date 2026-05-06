<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EndOfTrialMail extends LifecycleMail
{
    public function __construct(
        public string $firstName,
        public string $discountPercent = '15%',
    ) {}

    protected function utmCampaign(): string
    {
        return 'end-of-trial';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "Your free trial has ended, {$this->firstName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.end-of-trial',
            with: [
                'firstName' => $this->firstName,
                'discountPercent' => $this->discountPercent,
                'reassureUrl' => $this->utm('https://fynla.org/subscribe', 'but-dont-worry-cta'),
                'finalSubscribeUrl' => $this->utm('https://fynla.org/subscribe', 'final-subscribe-cta'),
            ],
        );
    }
}
