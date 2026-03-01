<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BugReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  array<string, mixed>  $bugReport
     */
    public function __construct(
        public array $bugReport
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $userId = $this->bugReport['user_id'] ?? 'Guest';
        $isPreview = $this->bugReport['is_preview_user'] ?? false;
        $previewBadge = $isPreview ? ' [PREVIEW]' : '';

        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla Bug Reports'),
            subject: "Bug Report - User {$userId}{$previewBadge}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.bug-report',
            with: [
                'bugReport' => $this->bugReport,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
