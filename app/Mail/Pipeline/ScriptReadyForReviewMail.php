<?php

declare(strict_types=1);

namespace App\Mail\Pipeline;

use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\PipelineArticle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScriptReadyForReviewMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public PipelineArticle $pipelineArticle,
        public InsightArticle $article,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Script ready — '.$this->article->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pipeline.script-ready',
            with: [
                'articleTitle' => $this->article->title,
                'articleSlug' => $this->article->slug,
                'scriptUrl' => $this->pipelineArticle->script_drive_url,
                'trackerUrl' => 'https://docs.google.com/spreadsheets/d/'.
                    (string) config('pipeline.google.tracker_sheet_id').'/edit',
                'costGbp' => number_format((float) $this->pipelineArticle->script_cost_gbp, 4),
                'model' => $this->pipelineArticle->script_model,
                'promptVersion' => $this->pipelineArticle->script_prompt_version,
            ],
        );
    }
}
