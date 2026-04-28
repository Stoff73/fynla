<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DontMissOutMail extends LifecycleMail
{
    public function __construct(
        public string $firstName,
    ) {}

    protected function utmCampaign(): string
    {
        return 'dont-miss-out';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "Don't miss out, {$this->firstName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.dont-miss-out',
            with: [
                'firstName'           => $this->firstName,
                'getStartedUrl'       => $this->utm('https://fynla.org/dashboard', 'zero-state-get-started'),
                'addInformationUrl'   => $this->utm('https://fynla.org/dashboard', 'add-my-information-cta'),
            ],
        );
    }
}
