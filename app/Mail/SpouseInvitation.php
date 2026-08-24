<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * W-0349 — an invitation to an address that has NO Fynla account.
 *
 * Takes plain strings, not a `User`. That is the point: on this path there is
 * no user to take, because the account is no longer created. `SpouseAccountCreated`
 * is its predecessor and takes `User $spouseUser` plus a temporary password —
 * which is exactly the behaviour CSJ removed (2026-08-23).
 */
class SpouseInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $invitedEmail,
        public string $inviterName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "{$this->inviterName} has invited you to plan together on Fynla",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.spouse-invitation',
            with: [
                'invitedEmail' => $this->invitedEmail,
                'inviterName' => $this->inviterName,
                // No prefill parameter: nothing on the register screen reads one
                // today, so a query string here would be decoration. The body
                // names the address instead.
                'registerUrl' => rtrim((string) config('app.url'), '/').'/register',
            ],
        );
    }
}
