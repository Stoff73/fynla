<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReferralInvitationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $referrer,
        public string $referralCode
    ) {}

    public function envelope(): Envelope
    {
        $name = trim(($this->referrer->first_name ?? '') . ' ' . ($this->referrer->surname ?? ''));

        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "{$name} thinks you'd like Fynla",
        );
    }

    public function content(): Content
    {
        $registerUrl = config('app.url') . '/register?ref=' . $this->referralCode;

        return new Content(
            view: 'emails.referral-invitation',
            with: [
                'referrerName' => trim(($this->referrer->first_name ?? '') . ' ' . ($this->referrer->surname ?? '')),
                'referralCode' => $this->referralCode,
                'registerUrl' => $registerUrl,
            ],
        );
    }
}
