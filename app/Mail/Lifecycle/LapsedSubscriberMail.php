<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Stub — replaced in Phase 8 Task 8.5.
 */
class LapsedSubscriberMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public ?string $updatePaymentUrl = null,
        public array $feedbackUrls = [],
        public ?string $gracePeriodEnd = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Stub — replaced in Phase 8');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>stub</p>');
    }
}
