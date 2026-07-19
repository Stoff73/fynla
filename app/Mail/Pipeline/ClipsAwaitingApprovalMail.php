<?php

declare(strict_types=1);

namespace App\Mail\Pipeline;

use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\ClipApproval;
use App\Models\Pipeline\PipelineArticle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClipsAwaitingApprovalMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<ClipApproval>  $approvals
     * @param  list<string>  $signedClipUrls
     */
    public function __construct(
        public PipelineArticle $pipelineArticle,
        public InsightArticle $article,
        public array $approvals,
        public array $signedClipUrls,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Clips awaiting approval — %s', $this->article->title),
        );
    }

    public function content(): Content
    {
        $base = rtrim((string) config('app.url', 'https://fynla.org'), '/');

        $rows = [];
        foreach ($this->approvals as $i => $approval) {
            $signedUrl = $this->signedClipUrls[$i] ?? null;
            $rows[] = [
                'clip_index' => $approval->clip_index,
                'scheduled_day' => $approval->scheduled_at->format('l'),
                'scheduled_date' => $approval->scheduled_at->format('j M Y'),
                'scheduled_time' => $approval->scheduled_at->format('H:i'),
                'preview_url' => $signedUrl,
                'approve_url' => $base.'/pipeline/clips/approve/'.$approval->approve_token,
                'reject_url' => $base.'/pipeline/clips/reject/'.$approval->reject_token,
            ];
        }

        return new Content(
            view: 'emails.pipeline.clips-awaiting-approval',
            with: [
                'articleTitle' => $this->article->title,
                'articleSlug' => $this->article->slug,
                'clipCount' => count($this->approvals),
                'clips' => $rows,
                'approveAllUrl' => $base.'/pipeline/clips/approve-all/'.$this->pipelineArticle->id.'/'.$this->approveAllToken(),
                'approvalQueueUrl' => $base.'/admin/pipeline/clips?article_id='.$this->pipelineArticle->id,
                'autoApproveMinutes' => (int) config('pipeline.clip_approval.auto_approve_minutes_before_post', 10),
            ],
        );
    }

    /**
     * Approve-all magic link uses the first clip's approve_token as its
     * article-level token — same expiry, same 48-char entropy, and it's
     * validated against the article at click time rather than being a
     * per-article secret we have to store separately.
     */
    private function approveAllToken(): string
    {
        return $this->approvals[0]->approve_token ?? '';
    }
}
