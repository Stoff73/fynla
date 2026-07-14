<?php

declare(strict_types=1);

namespace App\Console\Commands\Pipeline;

use App\Jobs\Pipeline\ProcessInsightArticleJob;
use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\PipelineArticle;
use App\Models\Pipeline\PipelineRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stage 1 — Detect InsightArticles that have been published on the site but
 * have not yet entered the marketing pipeline. Creates a pipeline_articles
 * row per new article and dispatches ProcessInsightArticleJob to hand off to
 * Stage 2.
 *
 * Runs daily via the scheduler. Feature-flag gated by config('pipeline.enabled').
 */
class DetectNewArticles extends Command
{
    protected $signature = 'pipeline:detect-new-articles
                            {--dry-run : List what would be dispatched without creating rows or dispatching jobs}';

    protected $description = 'Detect InsightArticles ready for the marketing pipeline';

    public function handle(): int
    {
        if (! config('pipeline.enabled')) {
            $this->warn('Pipeline is disabled (PIPELINE_ENABLED=false). Skipping.');
            Log::channel('pipeline')->info('Detect skipped: PIPELINE_ENABLED=false.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $existingIds = PipelineArticle::query()->pluck('insight_article_id')->all();

        $newArticles = InsightArticle::published()
            ->whereNotIn('id', $existingIds)
            ->orderBy('published_at')
            ->get();

        if ($newArticles->isEmpty()) {
            $this->info('No new articles to enter the pipeline.');
            Log::channel('pipeline')->info('Detect ran — 0 new articles.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Detected %d new article%s%s.',
            $newArticles->count(),
            $newArticles->count() === 1 ? '' : 's',
            $dryRun ? ' (dry run — nothing will be dispatched)' : ''
        ));

        foreach ($newArticles as $article) {
            $this->line("  → InsightArticle #{$article->id}: {$article->title}");

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($article) {
                $pipelineArticle = PipelineArticle::create([
                    'insight_article_id' => $article->id,
                    'status' => 'detected',
                ]);

                PipelineRun::create([
                    'pipeline_article_id' => $pipelineArticle->id,
                    'stage' => 'detect',
                    'status' => 'success',
                    'started_at' => now(),
                    'finished_at' => now(),
                    'metadata' => [
                        'insight_article_id' => $article->id,
                        'insight_article_slug' => $article->slug,
                    ],
                ]);

                ProcessInsightArticleJob::dispatch($pipelineArticle);

                Log::channel('pipeline')->info('Detected new article.', [
                    'pipeline_article_id' => $pipelineArticle->id,
                    'insight_article_id' => $article->id,
                    'slug' => $article->slug,
                ]);
            });
        }

        return self::SUCCESS;
    }
}
