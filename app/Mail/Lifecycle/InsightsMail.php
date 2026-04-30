<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InsightsMail extends LifecycleMail
{
    public function __construct(
        public string $firstName,
        public string $journeyPhrase, // e.g. "saving tax?"
        public int $progressPercent = 50,
    ) {}

    protected function utmCampaign(): string
    {
        return 'insights';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: 'Why are you ' . trim($this->journeyPhrase, '?') . '?',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.insights',
            with: [
                'firstName'        => $this->firstName,
                'journeyPhrase'    => $this->journeyPhrase,
                'progressPercent'  => $this->progressPercent,
                'finishUrl'        => $this->utm('https://fynla.org/dashboard', 'did-you-know-cta'),
                'continueUrl'      => $this->utm('https://fynla.org/dashboard', 'continue-my-plan-cta'),
                'protectionUrl'    => $this->utm('https://fynla.org/protection', 'tile-protection'),
                'savingsUrl'       => $this->utm('https://fynla.org/savings', 'tile-savings'),
                'investmentUrl'    => $this->utm('https://fynla.org/investment', 'tile-investment'),
                'retirementUrl'    => $this->utm('https://fynla.org/net-worth/retirement', 'tile-retirement'),
                'estateUrl'        => $this->utm('https://fynla.org/estate', 'tile-estate'),
                'goalsUrl'         => $this->utm('https://fynla.org/goals', 'tile-goals'),
            ],
        );
    }
}
