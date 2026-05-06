<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WellDoneMail extends LifecycleMail
{
    public function __construct(
        public string $firstName,
    ) {}

    protected function utmCampaign(): string
    {
        return 'well-done';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "Well done, {$this->firstName} — you've earned the maximum discount",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.well-done',
            with: [
                'firstName' => $this->firstName,
                'subscribeUrl' => $this->utm('https://fynla.org/subscribe', 'claim-20-cta'),
                'checkFeaturesUrl' => $this->utm('https://fynla.org/dashboard', 'check-features-cta'),
                'scenarioUrl' => $this->utm('https://fynla.org/planning/what-if', 'feature-scenario'),
                'monteCarloUrl' => $this->utm('https://fynla.org/net-worth/retirement', 'feature-monte-carlo'),
                'willBuilderUrl' => $this->utm('https://fynla.org/estate/will-builder', 'feature-will-builder'),
                'fynChatUrl' => $this->utm('https://fynla.org/dashboard', 'feature-fyn-chat'),
            ],
        );
    }
}
