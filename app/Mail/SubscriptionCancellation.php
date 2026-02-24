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

class SubscriptionCancellation extends Mailable
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
            subject: 'Subscription cancelled - Fynla',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-cancellation',
            with: [
                'user' => $this->user,
                'planName' => ucfirst($this->subscription->plan ?? 'Standard'),
                'billingCycle' => $this->subscription->billing_cycle ?? 'monthly',
                'accessUntil' => $this->subscription->current_period_end?->format('j F Y'),
            ],
        );
    }
}
