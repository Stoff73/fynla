<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class GreatJobMail extends LifecycleMail
{
    public function __construct(
        public string $firstName,
        public int $progressPercent = 75,
        public string $discountEarned = '15%',
    ) {}

    protected function utmCampaign(): string
    {
        return 'great-job';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "Great job {$this->firstName} — you're closer to financial freedom",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.great-job',
            with: [
                'firstName'        => $this->firstName,
                'progressPercent'  => $this->progressPercent,
                'discountEarned'   => $this->discountEarned,
                'finishPlanUrl1'   => $this->utm('https://fynla.org/dashboard', 'value-block-cta'),
                'finishPlanUrl2'   => $this->utm('https://fynla.org/dashboard', 'next-steps-cta'),
                'finishPlanUrl3'   => $this->utm('https://fynla.org/dashboard', 'why-this-matters-cta'),
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
