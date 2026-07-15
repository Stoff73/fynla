<?php

declare(strict_types=1);

namespace App\Mail\Pipeline;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VideoAuditReminderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @param  list<array{path:string,relative:string,age_days:?int,size_mb:float}>  $rows */
    public function __construct(
        public array $rows,
        public int $olderThanDays,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Quarterly video audit — %d file(s) over %d days old', count($this->rows), $this->olderThanDays),
        );
    }

    public function content(): Content
    {
        $totalMb = array_sum(array_column($this->rows, 'size_mb'));

        return new Content(
            view: 'emails.pipeline.video-audit-reminder',
            with: [
                'rows' => $this->rows,
                'olderThanDays' => $this->olderThanDays,
                'fileCount' => count($this->rows),
                'totalMb' => number_format($totalMb, 1),
            ],
        );
    }
}
