<?php

declare(strict_types=1);

namespace App\Jobs\Pipeline;

use App\Models\Pipeline\PipelinePost;
use App\Models\Pipeline\PipelineRun;
use App\Services\Pipeline\Social\BufferClient;
use App\Services\Pipeline\Social\PostScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Pushes a single approved post to Buffer at the next optimum slot for
 * its platform. On success: status → scheduled, scheduled_at stamped,
 * buffer_update_id stored for later metrics fetch.
 *
 * Variant distribution rule (from the skill): A and B for the same clip
 * should not both land on the same platform. The
 * `ScheduleReadyPosts` command picks which variant/platform pairs to
 * dispatch — this job is naive, it just schedules whatever it's handed.
 */
class SchedulePostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public PipelinePost $post)
    {
        $this->onQueue(config('pipeline.queue', 'pipeline'));
    }

    public function handle(BufferClient $buffer, PostScheduler $scheduler): void
    {
        $post = $this->post->fresh();

        if ($post->status !== 'approved') {
            Log::channel('pipeline')->info('SchedulePost skipped — not approved.', [
                'post_id' => $post->id,
                'status' => $post->status,
            ]);

            return;
        }

        $clipPath = $this->resolveClipPath($post);
        if ($clipPath === null || ! is_file($clipPath)) {
            $this->fail($post, "Clip file not found for post {$post->id}.");

            return;
        }

        // Buffer's GraphQL API downloads the video from a public URL — pass
        // our signed clip route (30-day TTL), not the raw file path.
        $videoUrl = $this->buildSignedClipUrl($post, $clipPath);

        $run = PipelineRun::create([
            'pipeline_article_id' => $post->pipeline_article_id,
            'stage' => 'schedule',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $slot = $scheduler->nextSlot($post->platform, CarbonImmutable::now('UTC'));

            $body = trim($post->caption)
                .' '
                .($post->destination_url_final ?: $post->destination_url_default)
                ."\n\n"
                .implode(' ', (array) $post->hashtags);

            $response = $buffer->schedule($post->platform, $body, $videoUrl, $slot->toDateTime());

            $updateId = null;
            foreach ($response['updates'] ?? [] as $u) {
                if (! empty($u['id'])) {
                    $updateId = $u['id'];
                    break;
                }
            }

            $post->update([
                'status' => 'scheduled',
                'scheduled_at' => $slot->toDateTime(),
                'buffer_update_id' => $updateId,
                'last_error' => null,
            ]);

            $run->update([
                'status' => 'success',
                'finished_at' => now(),
                'metadata' => [
                    'scheduled_at' => $slot->toIso8601String(),
                    'buffer_update_id' => $updateId,
                ],
            ]);

            Log::channel('pipeline')->info('Post scheduled via Buffer.', [
                'post_id' => $post->id,
                'platform' => $post->platform,
                'scheduled_at' => $slot->toIso8601String(),
                'buffer_update_id' => $updateId,
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status' => 'error',
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            $post->increment('retry_count');
            $post->update(['status' => 'failed', 'last_error' => $e->getMessage()]);

            Log::channel('pipeline')->error('Post schedule failed.', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function resolveClipPath(PipelinePost $post): ?string
    {
        $article = $post->pipelineArticle;
        $paths = is_array($article?->clip_paths) ? $article->clip_paths : [];
        $index = $post->clip_index - 1;

        return $paths[$index] ?? null;
    }

    /**
     * Build the public signed URL Buffer will download the clip from.
     * TTL matches the tracker sheet's ({PIPELINE_SIGNED_URL_TTL_DAYS},
     * default 30 days) so a clip stays fetchable for the full window
     * between compose and publish.
     */
    private function buildSignedClipUrl(PipelinePost $post, string $clipPath): string
    {
        $ttlDays = (int) config('pipeline.video.signed_url_ttl_days', 30);
        $slug = $post->pipelineArticle?->insightArticle?->slug ?? $post->pipelineArticle?->slug;

        if (! is_string($slug) || $slug === '') {
            throw new \RuntimeException("Cannot build signed URL: article slug missing for post {$post->id}.");
        }

        return URL::temporarySignedRoute(
            'pipeline.clip.download',
            now()->addDays($ttlDays),
            ['slug' => $slug, 'filename' => basename($clipPath)],
        );
    }

    private function fail(PipelinePost $post, string $reason): void
    {
        PipelineRun::create([
            'pipeline_article_id' => $post->pipeline_article_id,
            'stage' => 'schedule',
            'status' => 'error',
            'error' => $reason,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        $post->update(['status' => 'failed', 'last_error' => $reason]);

        Log::channel('pipeline')->error('SchedulePost aborted.', [
            'post_id' => $post->id,
            'reason' => $reason,
        ]);
    }
}
