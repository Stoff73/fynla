<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SubscribeMaxDiscountMail extends LifecycleMail
{
    public function __construct(
        public string $firstName,
        public int $daysRemaining,
        public string $trialEndDate,
    ) {}

    protected function utmCampaign(): string
    {
        return 'subscribe-max-discount';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "Your trial is ending — lock in your 20%, {$this->firstName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.subscribe-max-discount',
            with: [
                'firstName'       => $this->firstName,
                'daysRemaining'   => $this->daysRemaining,
                'trialEndDate'    => $this->trialEndDate,
                'countdownUrl'    => $this->utm('https://fynla.org/subscribe', 'countdown-cta'),
                'discountUrl'     => $this->utm('https://fynla.org/subscribe', 'discount-panel-cta'),
            ],
        );
    }
}
