<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pipeline;

use App\Http\Controllers\Controller;
use App\Models\Pipeline\ClipApproval;
use App\Models\Pipeline\PipelineArticle;
use App\Services\Pipeline\ClipApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles the one-click magic links in the ClipsAwaitingApprovalMail.
 * No login required — the 48-char token IS the secret. Every action
 * is single-use (the token stops being valid once the approval row
 * changes state) and expires after
 * ClipApprovalService::TOKEN_TTL_HOURS.
 */
class ClipApprovalActionController extends Controller
{
    public function __construct(private readonly ClipApprovalService $service) {}

    public function approve(Request $request, string $token): mixed
    {
        try {
            $approval = $this->findApproval($token, 'approve_token');
        } catch (\RuntimeException $e) {
            return response($this->page('Link expired or already used', $e->getMessage()), 410);
        }

        try {
            $this->service->approve($approval, actorId: null, viaEmail: true);
        } catch (Throwable $e) {
            Log::channel('pipeline')->error('Magic-link approve failed.', [
                'approval_id' => $approval->id,
                'error' => $e->getMessage(),
            ]);

            return response($this->page('Something went wrong', 'We could not record the approval. Please open the admin queue and try there.'), 500);
        }

        return response($this->page(
            'Clip approved',
            'Clip '.$approval->clip_index.' for <strong>'.e($approval->pipelineArticle->insightArticle?->title ?? 'this article').'</strong> is now approved. If every clip on this article is decided, composition and scheduling will start automatically.'
        ));
    }

    public function reject(Request $request, string $token): mixed
    {
        try {
            $approval = $this->findApproval($token, 'reject_token');
        } catch (\RuntimeException $e) {
            return response($this->page('Link expired or already used', $e->getMessage()), 410);
        }

        try {
            $this->service->reject($approval, reason: 'Rejected via email link.', actorId: null, viaEmail: true);
        } catch (Throwable $e) {
            return response($this->page('Something went wrong', 'We could not record the rejection. Please open the admin queue and try there.'), 500);
        }

        return response($this->page(
            'Clip rejected',
            'Clip '.$approval->clip_index.' has been rejected. Composition and scheduling are blocked for this article until the clip is regenerated.'
        ));
    }

    public function approveAll(Request $request, int $pipelineArticleId, string $token): mixed
    {
        $article = PipelineArticle::find($pipelineArticleId);
        if ($article === null) {
            return response($this->page('Article not found', 'The article referenced by this link no longer exists.'), 404);
        }

        // Validate the token — must match at least one of the article's clip approve tokens
        $anyValid = ClipApproval::where('pipeline_article_id', $article->id)
            ->where('approve_token', $token)
            ->where('token_expires_at', '>', now())
            ->exists();
        if (! $anyValid) {
            return response($this->page('Link expired or invalid', 'The approve-all link has expired or does not match this article.'), 410);
        }

        $decided = $this->service->approveAllForArticle($article, actorId: null, viaEmail: true);

        return response($this->page(
            'All clips approved',
            count($decided).' clip(s) approved. Composition and scheduling will start automatically.'
        ));
    }

    private function findApproval(string $token, string $column): ClipApproval
    {
        $approval = ClipApproval::where($column, $token)->first();
        if ($approval === null) {
            throw new \RuntimeException('The link is not valid — the token does not match any pending clip.');
        }
        if ($approval->token_expires_at !== null && $approval->token_expires_at->isPast()) {
            throw new \RuntimeException('This link expired '.$approval->token_expires_at->diffForHumans().'.');
        }
        if ($approval->status !== 'pending') {
            throw new \RuntimeException('This clip was already '.$approval->status.'.');
        }

        return $approval;
    }

    private function page(string $title, string $body): string
    {
        $escapedTitle = e($title);

        return <<<HTML
            <!doctype html>
            <html lang="en-GB">
            <head>
                <meta charset="utf-8">
                <title>{$escapedTitle} — Fynla Pipeline</title>
                <style>
                    body { font-family: 'Segoe UI', system-ui, sans-serif; max-width: 640px; margin: 5rem auto; padding: 0 1.5rem; color: #1F2A44; }
                    h1 { font-weight: 700; font-size: 1.5rem; margin-bottom: 1rem; }
                    p { line-height: 1.55; color: #4b5563; }
                    a { color: #e74c6f; text-decoration: none; font-weight: 700; }
                </style>
            </head>
            <body>
                <h1>{$escapedTitle}</h1>
                <p>{$body}</p>
                <p><a href="/admin/pipeline/clips">Open the approval queue</a></p>
            </body>
            </html>
        HTML;
    }
}
