<?php

declare(strict_types=1);

namespace App\Mail\Pipeline;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyPerformanceReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public array $summary) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Fynla weekly social report — %s to %s',
                $this->summary['week_start'],
                $this->summary['week_end'],
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pipeline.weekly-report',
            with: $this->summary,
        );
    }
}
