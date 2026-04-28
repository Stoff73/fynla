<?php

declare(strict_types=1);

namespace App\Mail\Newsletter;

use App\Models\News\NewsSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly NewsSubscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.marketing.address'),
                config('mail.marketing.name')
            ),
            subject: 'Confirm your subscription to Fynla news',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.confirm-subscription',
            with: [
                'confirmUrl' => url('/subscribe/news/confirm/'.$this->subscriber->confirmation_token),
            ],
        );
    }
}
