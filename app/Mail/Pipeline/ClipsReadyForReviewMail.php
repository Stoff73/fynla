<?php

declare(strict_types=1);

namespace App\Mail\Pipeline;

use App\Models\Pipeline\PipelineArticle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClipsReadyForReviewMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @param  list<string>  $signedClipUrls */
    public function __construct(
        public PipelineArticle $pipelineArticle,
        public array $signedClipUrls,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Clips ready — '.$this->pipelineArticle->sourceTitle(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pipeline.clips-ready',
            with: [
                'articleTitle' => $this->pipelineArticle->sourceTitle(),
                'articleSlug' => $this->pipelineArticle->sourceSlug(),
                'clipCount' => count($this->signedClipUrls),
                'clipUrls' => $this->signedClipUrls,
                'trackerUrl' => 'https://docs.google.com/spreadsheets/d/'.
                    (string) config('pipeline.google.tracker_sheet_id').'/edit',
                'durationSeconds' => $this->pipelineArticle->source_video_duration_s,
            ],
        );
    }
}
