<?php

declare(strict_types=1);

namespace App\Jobs\Pipeline;

use App\Mail\Pipeline\ClipsReadyForReviewMail;
use App\Models\Pipeline\PipelineArticle;
use App\Models\Pipeline\PipelineRun;
use App\Services\Pipeline\CaptionBuilder;
use App\Services\Pipeline\Google\GoogleDriveService;
use App\Services\Pipeline\Google\GoogleSheetsService;
use App\Services\Pipeline\HighlightSelectorService;
use App\Services\Pipeline\LocalWhisperTranscriber;
use App\Services\Pipeline\VideoCropService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Stage 3 orchestrator — turns a source video from Drive into one or more
 * 9:16 clips ready for social publishing.
 *
 * Flow (per InsightArticle):
 *   1. Download source video from Drive → storage/app/social/source/{slug}.mp4
 *   2. ffprobe → duration
 *   3. Whisper transcribe (local, free) → JSON + SRT sidecars
 *   4. If duration > MAX_WHOLE_VIDEO_SECONDS: Claude picks 1–3 highlights;
 *      else: whole video is the single "highlight".
 *   5. For each highlight: build clip-scoped SRT + FFmpeg crop+burn →
 *      storage/app/social/video/{slug}/clip-N.mp4
 *   6. Update tracker sheet row with signed download URLs.
 *   7. Email marketing@fynla.org.
 *
 * Errors abort the run and move the article to `failed`; retry_count is
 * incremented so a re-run can pick up where we left off.
 */
class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Videos ≤ this length are cropped whole rather than highlight-picked. */
    public const MAX_WHOLE_VIDEO_SECONDS = 75;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public PipelineArticle $pipelineArticle)
    {
        $this->onQueue(config('pipeline.queue', 'pipeline'));
    }

    public function handle(
        GoogleDriveService $drive,
        GoogleSheetsService $sheets,
        LocalWhisperTranscriber $whisper,
        HighlightSelectorService $highlights,
        CaptionBuilder $captions,
        VideoCropService $cropper,
    ): void {
        $article = $this->pipelineArticle->fresh();
        $insight = $article->insightArticle;

        if ($insight === null) {
            $this->fail($article, 'Insight article no longer exists.');

            return;
        }

        if (! in_array($article->status, ['rendering', 'failed'], true)) {
            Log::channel('pipeline')->info('Skipping — video already processed.', [
                'pipeline_article_id' => $article->id,
                'status' => $article->status,
            ]);

            return;
        }

        $slug = $insight->slug;
        $sourceDir = storage_path('app/social/source');
        $sourcePath = $sourceDir.DIRECTORY_SEPARATOR.$slug.'.mp4';
        $clipsDir = storage_path("app/social/video/{$slug}");

        $run = PipelineRun::create([
            'pipeline_article_id' => $article->id,
            'stage' => 'video',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            if (empty($article->source_video_drive_file_id)) {
                throw new \RuntimeException('source_video_drive_file_id is missing — was the detect step skipped?');
            }
            $drive->downloadFile($article->source_video_drive_file_id, $sourcePath);
            $duration = $cropper->probeDurationSeconds($sourcePath);

            $transcriptPath = $whisper->transcribe($sourcePath);
            $transcript = $whisper->load($transcriptPath);

            $clipRanges = $duration > self::MAX_WHOLE_VIDEO_SECONDS
                ? $highlights->select($insight, $transcript)
                : [['start' => 0.0, 'end' => $duration, 'reason' => 'Short video — kept whole.']];

            @mkdir($clipsDir, 0755, true);

            $clipPaths = [];
            foreach ($clipRanges as $index => $range) {
                $clipNumber = $index + 1;
                $srtPath = $clipsDir.DIRECTORY_SEPARATOR."clip-{$clipNumber}.srt";
                $captions->buildSrt($transcript, $srtPath, $range['start'], $range['end']);

                $clipPath = $clipsDir.DIRECTORY_SEPARATOR."clip-{$clipNumber}.mp4";
                $cropper->cropAndBurn($sourcePath, $clipPath, $range['start'], $range['end'], $srtPath);
                $clipPaths[] = $clipPath;
            }

            $signedUrls = array_map(
                fn (string $path) => $this->signedDownloadUrl($slug, basename($path)),
                $clipPaths,
            );

            $article->update([
                'source_video_path' => $sourcePath,
                'source_video_duration_s' => (int) round($duration),
                'transcript_path' => $transcriptPath,
                'clip_paths' => $clipPaths,
                'captions_burned' => true,
                'clips_generated_at' => now(),
                'status' => 'rendered',
                'last_error' => null,
            ]);

            if ($article->tracking_sheet_row_id !== null) {
                $sheetId = (string) config('pipeline.google.tracker_sheet_id');
                if ($sheetId !== '') {
                    $sheets->appendRow($sheetId, [
                        now()->toIso8601String(),
                        $slug,
                        $insight->title,
                        $article->script_drive_url,
                        'Video Ready',
                        $article->source_video_drive_url,
                        implode("\n", $signedUrls),
                        'captions: burned',
                    ]);
                }
            }

            $run->update([
                'status' => 'success',
                'finished_at' => now(),
                'metadata' => [
                    'duration_s' => (int) round($duration),
                    'clip_count' => count($clipPaths),
                    'ranges' => $clipRanges,
                ],
            ]);

            Mail::to((string) config('pipeline.notifications.script_ready_to'))
                ->queue(new ClipsReadyForReviewMail($article->fresh(), $insight, $signedUrls));

            Log::channel('pipeline')->info('Video clips generated + delivered.', [
                'pipeline_article_id' => $article->id,
                'clip_count' => count($clipPaths),
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status' => 'error',
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            $article->increment('retry_count');
            $article->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);

            Log::channel('pipeline')->error('Video processing failed.', [
                'pipeline_article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function signedDownloadUrl(string $slug, string $filename): string
    {
        $ttlDays = (int) config('pipeline.video.signed_url_ttl_days', 30);

        return URL::temporarySignedRoute(
            'pipeline.clip.download',
            now()->addDays($ttlDays),
            ['slug' => $slug, 'filename' => $filename],
        );
    }

    private function fail(PipelineArticle $article, string $reason): void
    {
        $article->update(['status' => 'failed', 'last_error' => $reason]);

        PipelineRun::create([
            'pipeline_article_id' => $article->id,
            'stage' => 'video',
            'status' => 'error',
            'error' => $reason,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        Log::channel('pipeline')->error('Video processing aborted.', [
            'pipeline_article_id' => $article->id,
            'reason' => $reason,
        ]);
    }
}
