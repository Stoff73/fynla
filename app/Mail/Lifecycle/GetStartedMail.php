<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class GetStartedMail extends LifecycleMail
{
    public function __construct(
        public string $firstName,
        public int $progressPercent = 50,
    ) {}

    protected function utmCampaign(): string
    {
        return 'get-started';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "You're on your way, {$this->firstName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.get-started',
            with: [
                'firstName' => $this->firstName,
                'progressPercent' => $this->progressPercent,
                'dashboardUrl' => $this->utm('https://fynla.org/dashboard', 'ready-to-start-cta'),
                'fynUrl' => $this->utm('https://fynla.org/fyn/onboarding', 'fyn-help-cta'),
                'protectionUrl' => $this->utm('https://fynla.org/protection', 'tile-protection'),
                'savingsUrl' => $this->utm('https://fynla.org/savings', 'tile-savings'),
                'investmentUrl' => $this->utm('https://fynla.org/investment', 'tile-investment'),
                'retirementUrl' => $this->utm('https://fynla.org/net-worth/retirement', 'tile-retirement'),
                'estateUrl' => $this->utm('https://fynla.org/estate', 'tile-estate'),
                'goalsUrl' => $this->utm('https://fynla.org/goals', 'tile-goals'),
            ],
        );
    }
}
