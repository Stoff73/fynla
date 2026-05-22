<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\PipelineRun;
use App\Services\ArticleScraperService;
use App\Services\ClaudeService;
use App\Services\FFmpegService;
use App\Services\HeyGenService;
use App\Services\ImageRendererService;
use Illuminate\Console\Command;

/**
 * Orchestrates Stages 1.5 → 5 for a single article.
 * Stages 6+ (schedule, approve, publish, monitor, boost) come in chunks 3-5.
 *
 * Usage:
 *   php artisan pipeline:process --url=https://fynla.org/insights/isa-allowance-2025-26
 *   php artisan pipeline:process --article-id=42
 */
class PipelineProcess extends Command
{
    protected $signature = 'pipeline:process
                            {--url= : Article URL to process (creates record if new)}
                            {--article-id= : Existing article ID to re-process}
                            {--skip-video : Skip HeyGen + FFmpeg (faster testing of script + images)}';

    protected $description = 'Run stages 1.5–5 (scrape, Claude, images, video, clips) for one article';

    public function handle(
        ArticleScraperService $scraper,
        ClaudeService $claude,
        ImageRendererService $renderer,
        HeyGenService $heygen,
        FFmpegService $ffmpeg,
    ): int {
        $article = $this->resolveArticle();
        if (!$article) {
            return self::FAILURE;
        }

        $this->info("Processing article #{$article->id}: {$article->source_url}");

        try {
            // Stage 1.5 — Scrape
            $this->runStage('scrape', $article, fn () => $scraper->scrape($article));

            // Stage 2 — Claude script + structured output
            $claudeOutput = $this->runStage('claude', $article, fn () => $claude->generate($article));
            $this->info("→ topic: {$article->fresh()->inferred_topic} (confidence: {$article->fresh()->topic_confidence})");
            $this->info("→ landing: {$article->fresh()->chosen_landing_page_key}");

            // Stage 3 — Images
            $this->runStage('images', $article, fn () => $renderer->renderAll($article->fresh(), $claudeOutput));

            if (!$this->option('skip-video')) {
                // Stage 4 — HeyGen video
                $videoAsset = $this->runStage('heygen', $article, fn () => $heygen->generateVideo($article->fresh(), $claudeOutput['video_script']));

                // Stage 5 — FFmpeg clips
                $this->runStage('ffmpeg', $article, fn () => $ffmpeg->cutClips($article->fresh(), $videoAsset, $claudeOutput['clip_timestamps'] ?? []));
            } else {
                $this->warn('Skipping HeyGen + FFmpeg (--skip-video).');
            }

            $article->fresh()->setStatus('rendered');
            $this->info("✓ Pipeline stages 1.5–5 complete for article #{$article->id}");
            $this->info("  Output: storage/app/output/article-{$article->id}/");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Pipeline failed: " . $e->getMessage());
            $article->fresh()->setStatus('failed');
            return self::FAILURE;
        }
    }

    private function resolveArticle(): ?Article
    {
        if ($id = $this->option('article-id')) {
            $article = Article::find($id);
            if (!$article) {
                $this->error("No article with ID {$id}");
                return null;
            }
            return $article;
        }

        if ($url = $this->option('url')) {
            return Article::firstOrCreate(
                ['source_url' => $url],
                ['slug' => basename($url), 'status' => 'discovered']
            );
        }

        $this->error('Provide either --url or --article-id');
        return null;
    }

    private function runStage(string $name, Article $article, \Closure $fn): mixed
    {
        $run = PipelineRun::create([
            'article_id' => $article->id,
            'stage' => $name,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $result = $fn();
            $run->update(['status' => 'success', 'finished_at' => now()]);
            return $result;
        } catch (\Throwable $e) {
            $run->update(['status' => 'error', 'error' => $e->getMessage(), 'finished_at' => now()]);
            throw $e;
        }
    }
}
