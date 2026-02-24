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

class DataRetentionWarning extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public int $daysRemaining
    ) {}

    public function envelope(): Envelope
    {
        if ($this->daysRemaining === 1) {
            $subject = '1 day left - FINAL WARNING: your Fynla data will be permanently deleted';
        } elseif ($this->daysRemaining <= 10) {
            $subject = "{$this->daysRemaining} days left - your Fynla data will be permanently deleted";
        } elseif ($this->daysRemaining === 15) {
            $subject = '15 days until your Fynla data is permanently deleted';
        } else {
            $subject = 'Your Fynla access has ended - your data will be deleted in 30 days';
        }

        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.data-retention-warning',
            with: [
                'user' => $this->user,
                'daysRemaining' => $this->daysRemaining,
                'isFinalWarning' => $this->daysRemaining === 1,
                'isUrgent' => $this->daysRemaining <= 10,
            ],
        );
    }
}
