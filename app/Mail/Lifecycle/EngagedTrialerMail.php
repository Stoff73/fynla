<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EngagedTrialerMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public array $context = [],
        public ?string $magicUrl = null,
        public ?string $discountCode = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $firstName = $this->user->first_name;
        $subject = $firstName
            ? "Your Fynla picture so far, {$firstName} — and 25-45% off to finish it"
            : 'Your Fynla picture so far — and 25-45% off to finish it';

        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle.engaged-trialer',
            with: [
                'user' => $this->user,
                'firstName' => $this->user->first_name ?: 'there',
                'completionPct' => $this->context['completion_pct'] ?? 0,
                'modulesWithData' => $this->context['modules_with_data'] ?? [],
                'modulesRemaining' => $this->context['modules_remaining'] ?? [],
                'magicUrl' => $this->magicUrl,
                'discountCode' => $this->discountCode,
            ],
        );
    }
}
