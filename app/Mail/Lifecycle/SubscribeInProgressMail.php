<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SubscribeInProgressMail extends LifecycleMail
{
    public function __construct(
        public string $firstName,
        public int $daysRemaining,
        public string $trialEndDate,
        public string $currentDiscount,
        public int $progressPercent,
    ) {}

    protected function utmCampaign(): string
    {
        return 'subscribe-in-progress';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "Your trial is ending — {$this->currentDiscount} is locked in, {$this->firstName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.subscribe-in-progress',
            with: [
                'firstName'        => $this->firstName,
                'daysRemaining'    => $this->daysRemaining,
                'trialEndDate'     => $this->trialEndDate,
                'currentDiscount'  => $this->currentDiscount,
                'progressPercent'  => $this->progressPercent,
                'countdownUrl'     => $this->utm('https://fynla.org/subscribe', 'countdown-cta'),
                'discountUrl'      => $this->utm('https://fynla.org/subscribe', 'discount-panel-cta'),
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
