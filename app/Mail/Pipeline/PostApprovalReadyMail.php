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

class PostApprovalReadyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public PipelineArticle $pipelineArticle,
        public InsightArticle $article,
        public int $postCount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Posts ready to review — '.$this->article->title,
        );
    }

    public function content(): Content
    {
        $baseUrl = rtrim((string) config('app.url', 'https://fynla.org'), '/');

        return new Content(
            view: 'emails.pipeline.posts-approval-ready',
            with: [
                'articleTitle' => $this->article->title,
                'articleSlug' => $this->article->slug,
                'postCount' => $this->postCount,
                'approvalUrl' => $baseUrl.'/admin/pipeline/posts?article_id='.$this->pipelineArticle->id,
            ],
        );
    }
}
