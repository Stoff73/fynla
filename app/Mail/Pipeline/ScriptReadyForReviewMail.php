<?php

declare(strict_types=1);

namespace App\Mail\Pipeline;

use App\Models\Pipeline\PipelineArticle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notification (not an approval) sent when a video script has been generated
 * and uploaded to Drive. Links to the script AND to the article's admin page,
 * where the human reviews the formatting and pushes it to dev/live.
 *
 * Source-agnostic: the pipeline article may be sourced from a native
 * InsightArticle or from the DocumentArticle CMS.
 */
class ScriptReadyForReviewMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public PipelineArticle $pipelineArticle) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Script ready — '.($this->pipelineArticle->sourceTitle() ?? 'article'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pipeline.script-ready',
            with: [
                'articleTitle' => $this->pipelineArticle->sourceTitle() ?? 'Untitled article',
                'articleSlug' => $this->pipelineArticle->sourceSlug() ?? '',
                'scriptUrl' => (string) $this->pipelineArticle->script_drive_url,
                'adminUrl' => $this->adminUrl(),
                'costGbp' => number_format((float) $this->pipelineArticle->script_cost_gbp, 4),
                'model' => (string) $this->pipelineArticle->script_model,
            ],
        );
    }

    /**
     * The admin page where the article is reviewed and pushed to dev/live.
     * Insight-sourced articles use the pipeline article manager (which has the
     * local→dev→live push controls); document-sourced articles use the CMS
     * document editor.
     */
    private function adminUrl(): string
    {
        $base = rtrim((string) config('app.url'), '/');

        if ($this->pipelineArticle->insight_article_id !== null) {
            return $base.'/admin/pipeline/articles/'.$this->pipelineArticle->insight_article_id;
        }

        if ($this->pipelineArticle->document_article_id !== null) {
            return $base.'/admin/documents/'.$this->pipelineArticle->document_article_id.'/edit';
        }

        return $base.'/admin';
    }
}
