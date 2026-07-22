<?php

declare(strict_types=1);

namespace App\Console\Commands\Pipeline;

use App\Jobs\Pipeline\ProcessVideoJob;
use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\PipelineArticle;
use App\Models\Pipeline\PipelineRun;
use App\Services\Pipeline\Google\GoogleDriveService;
use App\Services\Pipeline\Google\VideosFolderLocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Stage 3 detector. Polls the "Videos" subfolder of Marketing Automation for
 * video files named `{article-slug}.mp4` or `{article-slug}.mov`. When a matching PipelineArticle
 * exists in state `scripted` and the video hasn't been picked up yet, the
 * source video's Drive URL is written to the pipeline row and a
 * ProcessVideoJob is dispatched.
 *
 * Runs daily at 07:30 (30 min after the article-detect command so any newly
 * scripted articles have had a chance to land).
 */
class DetectNewVideos extends Command
{
    protected $signature = 'pipeline:detect-new-videos
                            {--dry-run : List what would be dispatched without touching state or dispatching jobs}';

    protected $description = 'Detect new source videos in the Marketing Automation Videos folder';

    public function handle(GoogleDriveService $drive, VideosFolderLocator $folderLocator): int
    {
        if (! config('pipeline.enabled')) {
            $this->warn('Pipeline is disabled (PIPELINE_ENABLED=false). Skipping.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $videosFolderId = $folderLocator->resolve();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // List everything in the folder (no mimeType filter) so both .mp4
        // (video/mp4) and .mov (video/quicktime) are picked up; the filename
        // extension check below is the real gate.
        $files = $drive->listFiles($videosFolderId);

        if ($files === []) {
            $this->info('No videos found in the Marketing Automation Videos folder.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d video(s) in Drive.', count($files)));

        $dispatched = 0;
        foreach ($files as $file) {
            $slug = $this->slugFromFilename($file['name'] ?? '');
            if ($slug === null) {
                $this->line("  · <fg=gray>skip</> {$file['name']} (not a video — expected .mp4 or .mov)");

                continue;
            }

            // Resolve the pipeline article by slug from either CMS: native
            // InsightArticles or the DocumentArticle (browser-upload) CMS.
            $pipelineArticle = null;
            $insight = InsightArticle::where('slug', $slug)->first();
            if ($insight !== null) {
                $pipelineArticle = PipelineArticle::where('insight_article_id', $insight->id)->first();
            } else {
                $doc = \App\Models\DocumentArticle::where('slug', $slug)->first();
                if ($doc !== null) {
                    $pipelineArticle = PipelineArticle::where('document_article_id', $doc->id)->first();
                }
            }

            if ($pipelineArticle === null) {
                $this->line("  · <fg=gray>skip</> {$slug}.mp4 (no pipeline article for slug '{$slug}' — published & script generated?)");

                continue;
            }

            if ($pipelineArticle->source_video_drive_file_id === $file['id']) {
                continue;
            }

            if (! in_array($pipelineArticle->status, ['scripted', 'failed'], true)) {
                $this->line("  · <fg=gray>skip</> {$slug}.mp4 (article state '{$pipelineArticle->status}' — already processed?)");

                continue;
            }

            $this->line("  → <fg=cyan>queue</> {$slug}.mp4 → pipeline_article #{$pipelineArticle->id}");

            if ($dryRun) {
                continue;
            }

            $pipelineArticle->update([
                'source_video_drive_file_id' => $file['id'],
                'source_video_drive_url' => $file['webViewLink'] ?? "https://drive.google.com/file/d/{$file['id']}/view",
                'status' => 'rendering',
                'last_error' => null,
            ]);

            PipelineRun::create([
                'pipeline_article_id' => $pipelineArticle->id,
                'stage' => 'video_detect',
                'status' => 'success',
                'started_at' => now(),
                'finished_at' => now(),
                'metadata' => [
                    'drive_file_id' => $file['id'],
                    'drive_file_name' => $file['name'],
                    'insight_article_slug' => $slug,
                ],
            ]);

            ProcessVideoJob::dispatch($pipelineArticle);

            Log::channel('pipeline')->info('Detected new source video.', [
                'pipeline_article_id' => $pipelineArticle->id,
                'insight_article_slug' => $slug,
                'drive_file_id' => $file['id'],
            ]);

            $dispatched++;
        }

        $this->newLine();
        $this->info(sprintf('Dispatched %d job(s)%s.', $dispatched, $dryRun ? ' (dry run — nothing actually dispatched)' : ''));

        return self::SUCCESS;
    }

    private function slugFromFilename(string $filename): ?string
    {
        // Drive filenames sometimes carry stray leading/trailing whitespace.
        $filename = trim($filename);
        $lower = strtolower($filename);
        foreach (['.mp4', '.mov'] as $ext) {
            if (str_ends_with($lower, $ext)) {
                return substr($filename, 0, -strlen($ext));
            }
        }

        return null;
    }
}
