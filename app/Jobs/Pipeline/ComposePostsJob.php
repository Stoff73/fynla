<?php

declare(strict_types=1);

namespace App\Jobs\Pipeline;

use App\Mail\Pipeline\PostApprovalReadyMail;
use App\Models\Pipeline\PipelineArticle;
use App\Models\Pipeline\PipelinePost;
use App\Models\Pipeline\PipelineRun;
use App\Services\Pipeline\Social\HashtagPicker;
use App\Services\Pipeline\Social\PostComposer;
use App\Services\Pipeline\Social\UtmLinkBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Stage 4 composer. Fires after Stage 3 has produced clips. For each
 * clip, generates two caption variants × three platforms (Instagram,
 * Facebook, TikTok) and stores them as PipelinePost rows in
 * `awaiting_approval` state, then emails marketing@fynla.org with a
 * direct link to the approval queue.
 *
 * Governed by the `social-media-posts` skill — all composition goes
 * through UtmLinkBuilder / HashtagPicker / PostComposer.
 */
class ComposePostsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const PLATFORMS = ['instagram', 'facebook', 'tiktok'];

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public PipelineArticle $pipelineArticle)
    {
        $this->onQueue(config('pipeline.queue', 'pipeline'));
    }

    public function handle(
        PostComposer $composer,
        HashtagPicker $hashtags,
        UtmLinkBuilder $links,
    ): void {
        $article = $this->pipelineArticle->fresh();
        $insight = $article->insightArticle;

        if ($insight === null) {
            $this->fail($article, 'Insight article no longer exists.');

            return;
        }

        if ($article->status !== 'rendered') {
            Log::channel('pipeline')->info('Compose skipped — article not in rendered state.', [
                'pipeline_article_id' => $article->id,
                'status' => $article->status,
            ]);

            return;
        }

        $clipCount = is_array($article->clip_paths) ? count($article->clip_paths) : 0;
        if ($clipCount === 0) {
            $this->fail($article, 'No clip paths on article — cannot compose posts.');

            return;
        }

        $run = PipelineRun::create([
            'pipeline_article_id' => $article->id,
            'stage' => 'compose',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $created = 0;
            foreach (range(1, $clipCount) as $clipIndex) {
                foreach (self::PLATFORMS as $platform) {
                    $captions = $composer->compose($insight, $platform);
                    $hashList = $hashtags->pick($insight, $platform);

                    foreach (['A', 'B'] as $variant) {
                        $existing = PipelinePost::where('pipeline_article_id', $article->id)
                            ->where('clip_index', $clipIndex)
                            ->where('platform', $platform)
                            ->where('variant', $variant)
                            ->first();
                        if ($existing !== null) {
                            continue;
                        }

                        PipelinePost::create([
                            'pipeline_article_id' => $article->id,
                            'clip_index' => $clipIndex,
                            'variant' => $variant,
                            'platform' => $platform,
                            'caption' => $captions[$variant],
                            'hashtags' => $hashList,
                            'destination_url_default' => $links->forArticle($insight, $platform, $clipIndex),
                            'destination_type' => 'article',
                            'status' => 'awaiting_approval',
                        ]);
                        $created++;
                    }
                }
            }

            $run->update([
                'status' => 'success',
                'finished_at' => now(),
                'metadata' => [
                    'posts_created' => $created,
                    'clip_count' => $clipCount,
                ],
            ]);

            Mail::to((string) config('pipeline.notifications.script_ready_to'))
                ->queue(new PostApprovalReadyMail($article->fresh(), $insight, $created));

            Log::channel('pipeline')->info('Posts composed + approval email sent.', [
                'pipeline_article_id' => $article->id,
                'posts_created' => $created,
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status' => 'error',
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            $article->increment('retry_count');
            $article->update(['last_error' => $e->getMessage()]);

            Log::channel('pipeline')->error('Post composition failed.', [
                'pipeline_article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function fail(PipelineArticle $article, string $reason): void
    {
        PipelineRun::create([
            'pipeline_article_id' => $article->id,
            'stage' => 'compose',
            'status' => 'error',
            'error' => $reason,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        $article->update(['last_error' => $reason]);

        Log::channel('pipeline')->error('Post composition aborted.', [
            'pipeline_article_id' => $article->id,
            'reason' => $reason,
        ]);
    }
}
