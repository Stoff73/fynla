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

class SubscriptionRenewalReminder extends Mailable
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
            subject: 'Your Fynla subscription renews in 7 days',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-renewal-reminder',
            with: [
                'user' => $this->user,
                'planName' => ucfirst($this->subscription->plan),
                'billingCycle' => $this->subscription->billing_cycle,
                'amount' => number_format($this->subscription->amount / 100, 2),
                'renewalDate' => $this->subscription->current_period_end?->format('j F Y'),
            ],
        );
    }
}
