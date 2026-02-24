<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Payment $payment
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: 'Payment confirmation - Fynla',
        );
    }

    public function content(): Content
    {
        $subscription = $this->payment->subscription;

        return new Content(
            view: 'emails.payment-confirmation',
            with: [
                'user' => $this->user,
                'payment' => $this->payment,
                'planName' => ucfirst($subscription->plan ?? 'Standard'),
                'billingCycle' => $subscription->billing_cycle ?? 'monthly',
                'amount' => number_format($this->payment->amount / 100, 2),
                'paymentDate' => $this->payment->created_at?->format('j F Y'),
            ],
        );
    }
}
