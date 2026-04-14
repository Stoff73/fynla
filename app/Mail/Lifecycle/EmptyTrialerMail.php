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
 * Stub — the full template and envelope are built in Phase 8 (Task 8.2).
 * This exists only so the Phase 7 eligibility tests and LifecycleEngine's
 * dispatchEmail path can resolve the class without crashing.
 */
class EmptyTrialerMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public User $user)
    {
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
