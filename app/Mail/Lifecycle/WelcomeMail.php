<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WelcomeMail extends LifecycleMail
{
    public function __construct(
        public string $firstName,
        public string $email,
        public string $username,
        public string $planLabel,
        public string $startDate,
        public string $journeySlug,
        public string $journeyHeadline,
    ) {}

    protected function utmCampaign(): string
    {
        return 'welcome';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "Welcome to Fynla, {$this->firstName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.welcome',
            with: [
                'firstName'       => $this->firstName,
                'email'           => $this->email,
                'username'        => $this->username,
                'planLabel'       => $this->planLabel,
                'startDate'       => $this->startDate,
                'journeyHeadline' => $this->journeyHeadline,
                'journeyUrl'      => $this->utm("https://fynla.org/journeys/{$this->journeySlug}", 'journey-cta'),
                'dashboardUrl'    => $this->utm('https://fynla.org/dashboard', 'dashboard-cta'),
                'homeUrl'         => 'https://fynla.org',
            ],
        );
    }
}
