<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CountdownMail extends LifecycleMail
{
    public function __construct(
        public string $firstName,
        public int $daysRemaining,
        public string $trialEndDate,
        public string $discountCode,
        public string $discountPercent,
    ) {}

    protected function utmCampaign(): string
    {
        return 'countdown';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "Time's running out, {$this->firstName} — {$this->discountPercent} off",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.countdown',
            with: [
                'firstName' => $this->firstName,
                'daysRemaining' => $this->daysRemaining,
                'trialEndDate' => $this->trialEndDate,
                'discountCode' => $this->discountCode,
                'discountPercent' => $this->discountPercent,
                'subscribeUrl' => $this->utm('https://fynla.org/subscribe?code='.$this->discountCode, 'discount-code-cta'),
            ],
        );
    }
}
