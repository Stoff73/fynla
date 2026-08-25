<?php

declare(strict_types=1);

namespace App\Jobs\Pipeline;

use App\Mail\Pipeline\ClipsAwaitingApprovalMail;
use App\Models\Pipeline\ClipApproval;
use App\Models\Pipeline\PipelineArticle;
use App\Models\Pipeline\PipelineRun;
use App\Services\Pipeline\ClipApprovalService;
use App\Services\Pipeline\Google\GoogleDriveService;
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
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Reject-with-feedback → regenerate (Q1).
 *
 * When marketing rejects a SHORT highlight clip, ClipApprovalService::reject
 * dispatches this job. It reuses the source video + transcript already on disk,
 * asks the highlight selector for a DIFFERENT 20–30s moment (steering away from
 * the rejected range and addressing the reviewer's feedback), re-crops it (no
 * burned captions), swaps it into the article's clip_paths, replaces the
 * rejected ClipApproval with a fresh pending one, and re-sends the approval
 * email for just that clip.
 *
 * If the selector can't find a better moment, the clip stays rejected — the
 * reject count caps runaway regeneration (config max_regenerations).
 */
class RegenerateClipJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public int $pipelineArticleId,
        public int $clipIndex,
        public string $feedback,
    ) {
        $this->onQueue(config('pipeline.queue', 'pipeline'));
    }

    public function handle(
        GoogleDriveService $drive,
        LocalWhisperTranscriber $whisper,
        HighlightSelectorService $highlights,
        VideoCropService $cropper,
        ClipApprovalService $approvals,
    ): void {
        $article = PipelineArticle::find($this->pipelineArticleId);
        if ($article === null || ! $article->hasSource()) {
            Log::channel('pipeline')->warning('RegenerateClip skipped — article/source missing.', [
                'pipeline_article_id' => $this->pipelineArticleId,
            ]);

            return;
        }

        $rejected = ClipApproval::where('pipeline_article_id', $article->id)
            ->where('clip_index', $this->clipIndex)
            ->where('status', 'rejected')
            ->latest('id')
            ->first();

        if ($rejected === null || $rejected->clip_kind !== 'short') {
            Log::channel('pipeline')->info('RegenerateClip skipped — no rejected short clip to regenerate.', [
                'pipeline_article_id' => $article->id,
                'clip_index' => $this->clipIndex,
            ]);

            return;
        }

        $run = PipelineRun::create([
            'pipeline_article_id' => $article->id,
            'stage' => 'clip_regenerate',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $slug = $article->sourceSlug();
            $sourcePath = $this->ensureSource($drive, $article, $slug);
            $transcript = $this->ensureTranscript($whisper, $article, $sourcePath);

            $newRange = $highlights->select(
                $article->sourceTitle() ?? '',
                $article->sourceSummary(),
                $transcript,
                1,
                $this->avoidRanges($article),
                $this->feedback,
            )[0];

            $clipsDir = storage_path("app/social/video/{$slug}");
            @mkdir($clipsDir, 0755, true);
            $newRegen = (int) $rejected->regen_count + 1;
            $clipPath = $clipsDir.DIRECTORY_SEPARATOR."clip-{$this->clipIndex}-r{$newRegen}.mp4";
            $cropper->cropAndBurn($sourcePath, $clipPath, $newRange['start'], $newRange['end']);

            // Swap the new clip into the article's clip_paths at this index.
            $clipPaths = is_array($article->clip_paths) ? $article->clip_paths : [];
            $clipPaths[$this->clipIndex - 1] = $clipPath;
            $article->update(['clip_paths' => array_values($clipPaths), 'status' => 'rendered', 'last_error' => null]);

            // Replace the rejected approval with a fresh pending one for the
            // same slot, carrying the incremented regen_count.
            $rejected->delete();
            $fresh = $approvals->createForArticle($article->fresh());
            $newApproval = collect($fresh)->firstWhere('clip_index', $this->clipIndex);
            if ($newApproval !== null) {
                $newApproval->update(['regen_count' => $newRegen, 'clip_kind' => 'short']);
            }

            $signedUrl = URL::temporarySignedRoute(
                'pipeline.clip.download',
                now()->addDays((int) config('pipeline.video.signed_url_ttl_days', 30)),
                ['slug' => $slug, 'filename' => basename($clipPath)],
            );

            if ($newApproval !== null) {
                Mail::to((string) config('pipeline.notifications.script_ready_to'))
                    ->queue(new ClipsAwaitingApprovalMail($article->fresh(), [$newApproval->fresh()], [$signedUrl]));
            }

            $run->update([
                'status' => 'success',
                'finished_at' => now(),
                'metadata' => [
                    'clip_index' => $this->clipIndex,
                    'regen_count' => $newRegen,
                    'range' => $newRange,
                ],
            ]);

            Log::channel('pipeline')->info('Clip regenerated from feedback.', [
                'pipeline_article_id' => $article->id,
                'clip_index' => $this->clipIndex,
                'regen_count' => $newRegen,
            ]);
        } catch (Throwable $e) {
            $run->update(['status' => 'error', 'error' => $e->getMessage(), 'finished_at' => now()]);

            // Regeneration failed (e.g. no better moment found). Terminally
            // exclude this clip (cap out its regen_count) so it stops counting as
            // "in progress", then re-evaluate — the article should still proceed
            // with whatever clips are approved. Other clips are unaffected.
            $stillRejected = ClipApproval::find($rejected->id);
            if ($stillRejected !== null && $stillRejected->status === 'rejected') {
                $stillRejected->update([
                    'regen_count' => (int) config('pipeline.clip_approval.max_regenerations', 3),
                ]);
                $approvals->evaluateDownstream($article->fresh());
            }

            Log::channel('pipeline')->warning('Clip regeneration failed — clip excluded from the run.', [
                'pipeline_article_id' => $article->id,
                'clip_index' => $this->clipIndex,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function ensureSource(GoogleDriveService $drive, PipelineArticle $article, string $slug): string
    {
        $path = $article->source_video_path
            ?: storage_path('app/social/source'.DIRECTORY_SEPARATOR.$slug.'.mp4');

        if (! is_file($path)) {
            if (empty($article->source_video_drive_file_id)) {
                throw new \RuntimeException('Source video missing and no Drive file id to re-download.');
            }
            $drive->downloadFile($article->source_video_drive_file_id, $path);
        }

        return $path;
    }

    /**
     * @return array{segments: list<array{start:float,end:float,text:string}>,text:string}
     */
    private function ensureTranscript(LocalWhisperTranscriber $whisper, PipelineArticle $article, string $sourcePath): array
    {
        $transcriptPath = $article->transcript_path;
        if (! is_string($transcriptPath) || ! is_file($transcriptPath)) {
            $transcriptPath = $whisper->transcribe($sourcePath);
        }

        return $whisper->load($transcriptPath);
    }

    /**
     * The range originally cut for this clip (from the video-stage run
     * metadata), so the selector steers away from it.
     *
     * @return list<array{start:float,end:float}>
     */
    private function avoidRanges(PipelineArticle $article): array
    {
        $run = PipelineRun::where('pipeline_article_id', $article->id)
            ->where('stage', 'video')
            ->latest('id')
            ->first();

        $ranges = $run?->metadata['ranges'] ?? [];
        $original = $ranges[$this->clipIndex - 1] ?? null;

        return $original !== null
            ? [['start' => (float) ($original['start'] ?? 0), 'end' => (float) ($original['end'] ?? 0)]]
            : [];
    }
}
