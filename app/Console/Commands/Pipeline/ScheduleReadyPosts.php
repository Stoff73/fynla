<?php

declare(strict_types=1);

namespace App\Console\Commands\Pipeline;

use App\Jobs\Pipeline\SchedulePostJob;
use App\Models\Pipeline\PipelinePost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Runs hourly. Finds approved-but-unscheduled posts and dispatches
 * SchedulePostJob per post, applying the variant-distribution rule from
 * the social-media-posts skill: A and B variants for the same clip must
 * not both land on the same platform. When both are approved, the
 * earlier-created variant wins on its original platform; the other is
 * left `approved` for the next tick (which won't happen unless the
 * reviewer manually re-routes it — safe default).
 */
class ScheduleReadyPosts extends Command
{
    protected $signature = 'pipeline:schedule-ready-posts
                            {--dry-run : Log what would be scheduled without dispatching}';

    protected $description = 'Dispatch SchedulePostJob for every approved-unscheduled post';

    public function handle(): int
    {
        if (! config('pipeline.enabled')) {
            $this->warn('Pipeline disabled (PIPELINE_ENABLED=false). Skipping.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');

        $posts = PipelinePost::approvedUnscheduled()
            ->orderBy('id')
            ->get();

        if ($posts->isEmpty()) {
            $this->info('No approved-unscheduled posts.');

            return self::SUCCESS;
        }

        $picked = [];
        $seenPlatformClip = [];
        foreach ($posts as $post) {
            $key = $post->pipeline_article_id.'|'.$post->clip_index.'|'.$post->platform;
            if (isset($seenPlatformClip[$key])) {
                continue;
            }
            $seenPlatformClip[$key] = true;
            $picked[] = $post;
        }

        $this->info(sprintf(
            'Dispatching %d post(s)%s.',
            count($picked),
            $dry ? ' (dry run)' : '',
        ));

        foreach ($picked as $post) {
            $this->line("  → post #{$post->id} ({$post->platform}, variant {$post->variant})");

            if ($dry) {
                continue;
            }

            SchedulePostJob::dispatch($post);
        }

        Log::channel('pipeline')->info('ScheduleReadyPosts run.', [
            'count' => count($picked),
            'dry_run' => $dry,
        ]);

        return self::SUCCESS;
    }
}
