<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentFailedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Subscription $subscription
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: 'Action required — payment issue with your Fynla subscription',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-failed',
            with: [
                'user' => $this->user,
                'subscription' => $this->subscription,
                'planName' => ucfirst($this->subscription->plan ?? 'Standard'),
                'amount' => number_format($this->subscription->amount / 100, 2),
                'periodEnd' => $this->subscription->current_period_end?->format('j F Y'),
            ],
        );
    }
}
