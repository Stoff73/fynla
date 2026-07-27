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
    public function createForArticle(PipelineArticle $article, ?int $fullClipIndex = null): array
    {
        $clipPaths = is_array($article->clip_paths) ? $article->clip_paths : [];
        if ($clipPaths === []) {
            throw new RuntimeException("Article #{$article->id} has no clip_paths — nothing to approve.");
        }

        $existing = ClipApproval::where('pipeline_article_id', $article->id)->get()->keyBy('clip_index');
        $now = CarbonImmutable::now('UTC');

        // Snippets from one video must go out at DIFFERENT times, not stacked on
        // the same slot. Walk a cursor forward: each clip takes the first slot
        // strictly after the previous clip's slot.
        $cursor = $now;

        $rows = [];
        foreach ($clipPaths as $index => $path) {
            $clipIndex = $index + 1;
            if ($existing->has($clipIndex)) {
                $row = $existing[$clipIndex];
                $rows[] = $row;
                if ($row->scheduled_at !== null) {
                    $existingSlot = CarbonImmutable::parse($row->scheduled_at);
                    if ($existingSlot->gt($cursor)) {
                        $cursor = $existingSlot;
                    }
                }

                continue;
            }

            $scheduledAt = $this->earliestScheduledSlot($cursor->addMinute());
            $cursor = $scheduledAt;

            $rows[] = ClipApproval::create([
                'pipeline_article_id' => $article->id,
                'clip_index' => $clipIndex,
                'clip_path' => $path,
                'clip_kind' => $clipIndex === $fullClipIndex ? 'full' : 'short',
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
     * Manual reject. The rejected clip is EXCLUDED from the run, not a blocker —
     * every approved clip still moves forward. For a SHORT (highlight) clip
     * under the regen cap we dispatch RegenerateClipJob, which uses the
     * rejection reason as feedback to pick a DIFFERENT moment, re-crops, and
     * re-notifies. The FULL clip (or a short at the cap) can't be re-picked, so
     * it's terminally excluded and we re-evaluate the gate immediately.
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

        $maxRegen = (int) config('pipeline.clip_approval.max_regenerations', 3);
        if ($approval->clip_kind === 'short' && (int) $approval->regen_count < $maxRegen) {
            \App\Jobs\Pipeline\RegenerateClipJob::dispatch(
                $approval->pipeline_article_id,
                $approval->clip_index,
                $reason,
            );

            Log::channel('pipeline')->info('Clip rejected — regeneration queued.', [
                'clip_approval_id' => $approval->id,
                'pipeline_article_id' => $approval->pipeline_article_id,
                'clip_index' => $approval->clip_index,
                'regen_count' => $approval->regen_count,
            ]);
        } else {
            // Terminal exclusion (the full clip, or a short at the regen cap):
            // this may be the final decision for the article, so re-evaluate the
            // gate — the remaining approved clips should proceed without it.
            $article = $approval->pipelineArticle()->first();
            if ($article !== null) {
                $this->maybeFireDownstream($article);
            }
        }

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
     * Public re-evaluation hook. RegenerateClipJob calls this after a failed
     * regeneration (the clip is now terminally excluded) so the article can
     * still proceed with whatever clips are approved.
     */
    public function evaluateDownstream(PipelineArticle $article): void
    {
        $this->maybeFireDownstream($article);
    }

    /**
     * Fire ComposePostsJob once every clip has reached a SETTLED state and at
     * least one is approved. Rejected clips are EXCLUDED from composition (not
     * blocking): any combination of approved clips still moves forward, so one
     * bad clip never freezes the whole article.
     *
     * A clip is NOT yet settled if it's pending, or if it's a rejected SHORT
     * clip still under the regen cap (a regenerated replacement is inbound).
     */
    private function maybeFireDownstream(PipelineArticle $article): void
    {
        $rows = ClipApproval::where('pipeline_article_id', $article->id)->get();
        if ($rows->isEmpty()) {
            return;
        }

        $maxRegen = (int) config('pipeline.clip_approval.max_regenerations', 3);

        $inProgress = $rows->contains(function ($r) use ($maxRegen) {
            if ($r->status === 'pending') {
                return true;
            }

            // A rejected short under the cap is mid-regeneration — wait for it.
            return $r->status === 'rejected'
                && $r->clip_kind === 'short'
                && (int) $r->regen_count < $maxRegen;
        });
        if ($inProgress) {
            return;
        }

        $anyApproved = $rows->contains(fn ($r) => in_array($r->status, ['approved', 'auto_approved'], true));
        if (! $anyApproved) {
            Log::channel('pipeline')->info('All clips rejected — nothing to compose.', [
                'pipeline_article_id' => $article->id,
            ]);

            return;
        }

        // Prevent double-dispatch (idempotent: composed once means
        // downstream will simply skip in-flight variants).
        if ($article->status === 'scripted' || $article->status === 'rendered') {
            // afterResponse: composition makes a dozen LLM calls, so it must never
            // run inside the approval request. On a sync queue (local/dev) an
            // inline dispatch blew PHP's max_execution_time and 500'd the
            // magic-link approval page *after* the approval had been recorded.
            ComposePostsJob::dispatch($article->fresh())->afterResponse();
            Log::channel('pipeline')->info('Clips settled — ComposePostsJob dispatched (rejected clips excluded).', [
                'pipeline_article_id' => $article->id,
                'approved' => $rows->whereIn('status', ['approved', 'auto_approved'])->count(),
                'excluded' => $rows->where('status', 'rejected')->count(),
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
