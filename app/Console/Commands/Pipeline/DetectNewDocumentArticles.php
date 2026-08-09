<?php

declare(strict_types=1);

namespace App\Console\Commands\Pipeline;

use App\Jobs\Pipeline\ProcessInsightArticleJob;
use App\Models\DocumentArticle;
use App\Models\Pipeline\PipelineArticle;
use App\Models\Pipeline\PipelineRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stage 1 (CMS) — Detect DocumentArticles (dev's browser-upload CMS) that have
 * been published but not yet entered the marketing pipeline. Creates a
 * pipeline_articles row per new article and dispatches ProcessInsightArticleJob
 * to generate the video script into Drive (Stage 2).
 *
 * Mirrors DetectNewArticles (which handles native InsightArticles). Runs daily
 * via the scheduler, gated by config('pipeline.enabled').
 */
class DetectNewDocumentArticles extends Command
{
    protected $signature = 'pipeline:detect-new-document-articles
                            {--dry-run : List what would be dispatched without creating rows or dispatching jobs}';

    protected $description = 'Detect published CMS DocumentArticles ready for the marketing pipeline';

    public function handle(): int
    {
        if (! config('pipeline.enabled')) {
            $this->warn('Pipeline is disabled (PIPELINE_ENABLED=false). Skipping.');
            Log::channel('pipeline')->info('Detect (documents) skipped: PIPELINE_ENABLED=false.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $existingIds = PipelineArticle::query()
            ->whereNotNull('document_article_id')
            ->pluck('document_article_id')
            ->all();

        $newArticles = DocumentArticle::published()
            ->whereNotIn('id', $existingIds)
            ->orderBy('published_at')
            ->get();

        if ($newArticles->isEmpty()) {
            $this->info('No new document articles to enter the pipeline.');
            Log::channel('pipeline')->info('Detect (documents) ran — 0 new articles.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Detected %d new document article%s%s.',
            $newArticles->count(),
            $newArticles->count() === 1 ? '' : 's',
            $dryRun ? ' (dry run — nothing will be dispatched)' : ''
        ));

        foreach ($newArticles as $article) {
            $this->line("  → DocumentArticle #{$article->id}: {$article->title}");

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($article) {
                $pipelineArticle = PipelineArticle::create([
                    'document_article_id' => $article->id,
                    'status' => 'detected',
                ]);

                PipelineRun::create([
                    'pipeline_article_id' => $pipelineArticle->id,
                    'stage' => 'detect',
                    'status' => 'success',
                    'started_at' => now(),
                    'finished_at' => now(),
                    'metadata' => [
                        'source_type' => 'document',
                        'document_article_id' => $article->id,
                        'source_slug' => $article->slug,
                    ],
                ]);

                ProcessInsightArticleJob::dispatch($pipelineArticle);

                Log::channel('pipeline')->info('Detected new document article.', [
                    'pipeline_article_id' => $pipelineArticle->id,
                    'document_article_id' => $article->id,
                    'slug' => $article->slug,
                ]);
            });
        }

        return self::SUCCESS;
    }
}
