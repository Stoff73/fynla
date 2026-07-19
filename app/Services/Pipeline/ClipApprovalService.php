<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Jobs\Pipeline\ComposePostsJob;
use App\Models\Pipeline\ClipApproval;
use App\Models\Pipeline\PipelineArticle;
use App\Services\Pipeline\Social\PostScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Owns the clip approval gate between Stage 3 and Stage 4.
 *
 * ProcessVideoJob calls createForArticle() after clips render — one
 * ClipApproval row per clip. Marketing reviews via
 * /admin/pipeline/clips or via the magic links in the approval email.
 * When every clip for an article reaches approved/auto_approved,
 * onArticleFullyApproved() fires ComposePostsJob.
 *
 * scheduled_at is precomputed at row creation using PostScheduler —
 * the email shows the ACTUAL day/time the post will land, and the
 * auto-approve deadline is derived from it.
 */
class ClipApprovalService
{
    public const TOKEN_TTL_HOURS = 48;

    public function __construct(private readonly PostScheduler $scheduler) {}

    /**
     * Create one pending ClipApproval per clip on the article. Idempotent
     * — if approvals already exist for this article's current clips, they
     * are returned unchanged (safe re-run after Stage 3 retries).
     *
     * @return list<ClipApproval>
     */
    public function createForArticle(PipelineArticle $article): array
    {
        $clipPaths = is_array($article->clip_paths) ? $article->clip_paths : [];
        if ($clipPaths === []) {
            throw new RuntimeException("Article #{$article->id} has no clip_paths — nothing to approve.");
        }

        $existing = ClipApproval::where('pipeline_article_id', $article->id)->get()->keyBy('clip_index');
        $now = CarbonImmutable::now('UTC');

        $rows = [];
        foreach ($clipPaths as $index => $path) {
            $clipIndex = $index + 1;
            if ($existing->has($clipIndex)) {
                $rows[] = $existing[$clipIndex];

                continue;
            }

            $scheduledAt = $this->earliestScheduledSlot($now->addMinutes(15 * $clipIndex));

            $rows[] = ClipApproval::create([
                'pipeline_article_id' => $article->id,
                'clip_index' => $clipIndex,
                'clip_path' => $path,
                'status' => 'pending',
                'approve_token' => Str::random(48),
                'reject_token' => Str::random(48),
                'token_expires_at' => now()->addHours(self::TOKEN_TTL_HOURS),
                'scheduled_at' => $scheduledAt,
            ]);
        }

        Log::channel('pipeline')->info('Clip approvals created.', [
            'pipeline_article_id' => $article->id,
            'clip_count' => count($rows),
        ]);

        return $rows;
    }

    /**
     * Manual approve — from admin UI or magic link. Idempotent on
     * already-approved rows (returns silently).
     */
    public function approve(ClipApproval $approval, ?int $actorId = null, bool $viaEmail = false): ClipApproval
    {
        if (in_array($approval->status, ['approved', 'auto_approved'], true)) {
            return $approval;
        }
        if ($approval->status === 'rejected') {
            throw new RuntimeException('Cannot approve a rejected clip.');
        }

        $approval->update([
            'status' => 'approved',
            'approved_by' => $actorId,
            'approved_at' => now(),
            'approved_via_email' => $viaEmail,
        ]);

        $article = $approval->pipelineArticle()->first();
        if ($article !== null) {
            $this->maybeFireDownstream($article);
        }

        return $approval->fresh();
    }

    /**
     * Manual reject. Blocks the article from downstream composition
     * until the clip is regenerated (Stage 3 rerun).
     */
    public function reject(ClipApproval $approval, string $reason, ?int $actorId = null, bool $viaEmail = false): ClipApproval
    {
        if ($approval->status === 'approved' || $approval->status === 'auto_approved') {
            throw new RuntimeException('Cannot reject an already-approved clip.');
        }

        $approval->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => $actorId,
            'approved_at' => now(),
            'approved_via_email' => $viaEmail,
        ]);

        return $approval->fresh();
    }

    /**
     * Approve every pending clip for this article. Used by the "Approve
     * all" button + magic link. Ignores already-decided rows.
     *
     * @return list<ClipApproval>
     */
    public function approveAllForArticle(PipelineArticle $article, ?int $actorId = null, bool $viaEmail = false): array
    {
        $pending = ClipApproval::where('pipeline_article_id', $article->id)->pending()->get();
        $decided = [];
        foreach ($pending as $approval) {
            $decided[] = $this->approve($approval, $actorId, $viaEmail);
        }

        return $decided;
    }

    /**
     * Called by the auto-approve cron (pipeline:auto-approve-clips).
     * Silently promotes pending → auto_approved when we're within N
     * minutes of the scheduled post time.
     *
     * @return list<ClipApproval>
     */
    public function autoApproveDue(int $minutesBefore): array
    {
        $now = now();
        $due = ClipApproval::pending()
            ->with('pipelineArticle.insightArticle')
            ->where('scheduled_at', '<=', $now->clone()->addMinutes($minutesBefore))
            ->get();

        $fired = [];
        foreach ($due as $approval) {
            $approval->update([
                'status' => 'auto_approved',
                'approved_by' => null,
                'approved_at' => now(),
                'approved_via_email' => false,
            ]);
            $fired[] = $approval->fresh();
            $this->maybeFireDownstream($approval->pipelineArticle);

            Log::channel('pipeline')->info('Clip auto-approved.', [
                'clip_approval_id' => $approval->id,
                'pipeline_article_id' => $approval->pipeline_article_id,
                'scheduled_at' => $approval->scheduled_at->toIso8601String(),
            ]);
        }

        return $fired;
    }

    /**
     * If every clip for an article is decided (approved/auto/rejected)
     * AND at least one is approved AND none are rejected, dispatch
     * ComposePostsJob. Rejected clips block downstream until Stage 3
     * regenerates them.
     */
    private function maybeFireDownstream(PipelineArticle $article): void
    {
        $rows = ClipApproval::where('pipeline_article_id', $article->id)->get();
        if ($rows->isEmpty()) {
            return;
        }

        $anyPending = $rows->contains(fn ($r) => $r->status === 'pending');
        if ($anyPending) {
            return;
        }

        $anyRejected = $rows->contains(fn ($r) => $r->status === 'rejected');
        if ($anyRejected) {
            Log::channel('pipeline')->info('Clip approvals decided but a rejection blocks compose.', [
                'pipeline_article_id' => $article->id,
                'rejected_count' => $rows->where('status', 'rejected')->count(),
            ]);

            return;
        }

        // Prevent double-dispatch (idempotent: composed once means
        // downstream will simply skip in-flight variants).
        if ($article->status === 'scripted' || $article->status === 'rendered') {
            ComposePostsJob::dispatch($article->fresh());
            Log::channel('pipeline')->info('All clips approved — ComposePostsJob dispatched.', [
                'pipeline_article_id' => $article->id,
            ]);
        }
    }

    /**
     * Compute the earliest platform slot at or after $notBefore, so the
     * email can show marketing WHEN the post will actually land.
     */
    private function earliestScheduledSlot(CarbonImmutable $notBefore): CarbonImmutable
    {
        $platforms = ['instagram', 'facebook', 'tiktok'];
        $slots = [];
        foreach ($platforms as $platform) {
            $slots[] = $this->scheduler->nextSlot($platform, $notBefore);
        }
        sort($slots);

        return $slots[0];
    }
}
