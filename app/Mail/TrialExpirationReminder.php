<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialExpirationReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public int $daysRemaining
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->daysRemaining === 1
            ? 'Your Fynla trial ends tomorrow'
            : "Your Fynla trial ends in {$this->daysRemaining} days";

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address('noreply@fynla.org', 'Fynla'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trial-expiration-reminder',
            with: [
                'user' => $this->user,
                'daysRemaining' => $this->daysRemaining,
                'planName' => ucfirst($this->user->plan ?? 'Standard'),
            ],
        );
    }
}
