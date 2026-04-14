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
 * Stub — replaced in Phase 8 Task 8.3 (the most complex template).
 */
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
        return new Envelope(subject: 'Stub — replaced in Phase 8');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>stub</p>');
    }
}
