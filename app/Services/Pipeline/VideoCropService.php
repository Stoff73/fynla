<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * FFmpeg wrapper for the Stage 3 video work.
 *
 * Duties:
 *   - Probe a video's duration via ffprobe.
 *   - Cut a segment out of a source and centre-crop it landscape → 9:16
 *     (1080×1920).
 *   - Burn hard-coded captions from an SRT sidecar using the standard
 *     social-reel styling (white text, black outline, bottom third).
 *
 * All operations are synchronous — the caller (ProcessVideoJob) queues the
 * work off the main request thread.
 */
class VideoCropService
{
    private const OUTPUT_WIDTH = 1080;

    private const OUTPUT_HEIGHT = 1920;

    private const CAPTION_STYLE = "FontName=Arial,FontSize=18,PrimaryColour=&Hffffff,OutlineColour=&H000000,Outline=2,Shadow=1,Bold=1,Alignment=2,MarginV=140";

    public function __construct(
        private readonly int $timeoutSeconds = 900,
    ) {}

    public function probeDurationSeconds(string $videoPath): float
    {
        $process = new Process([
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $videoPath,
        ]);
        $process->setTimeout(60);
        $process->mustRun();

        return (float) trim($process->getOutput());
    }

    /**
     * Cut [start, end] out of $sourcePath, centre-crop landscape → 9:16
     * (1080×1920), optionally burn captions from $srtPath, and write to
     * $outputPath. Returns the output path on success.
     */
    public function cropAndBurn(
        string $sourcePath,
        string $outputPath,
        float $start = 0.0,
        ?float $end = null,
        ?string $srtPath = null,
    ): string {
        $dir = dirname($outputPath);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Failed to create output directory: {$dir}");
        }

        $videoFilter = $this->buildVideoFilter($srtPath);

        $args = [
            'ffmpeg',
            '-y',
            '-ss', number_format($start, 3, '.', ''),
        ];
        if ($end !== null) {
            $args[] = '-to';
            $args[] = number_format($end, 3, '.', '');
        }
        array_push($args,
            '-i', $sourcePath,
            '-vf', $videoFilter,
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '20',
            '-c:a', 'aac',
            '-b:a', '128k',
            '-movflags', '+faststart',
            $outputPath,
        );

        $process = new Process($args);
        $process->setTimeout($this->timeoutSeconds);

        Log::channel('pipeline')->info('FFmpeg crop start.', [
            'source' => $sourcePath,
            'output' => $outputPath,
            'start' => $start,
            'end' => $end,
            'captions' => $srtPath !== null,
        ]);

        try {
            $process->mustRun();
        } catch (\Throwable $e) {
            @unlink($outputPath);
            Log::channel('pipeline')->error('FFmpeg crop failed.', [
                'stderr' => $process->getErrorOutput(),
                'output' => $outputPath,
            ]);
            throw new RuntimeException('FFmpeg crop failed: '.$e->getMessage(), previous: $e);
        }

        if (! is_file($outputPath) || filesize($outputPath) === 0) {
            throw new RuntimeException("FFmpeg produced no output at {$outputPath}");
        }

        Log::channel('pipeline')->info('FFmpeg crop done.', [
            'output' => $outputPath,
            'size_kb' => (int) (filesize($outputPath) / 1024),
        ]);

        return $outputPath;
    }

    /**
     * scale to fill the 1080-wide target (preserving aspect), then centre-crop
     * to 1080×1920. Captions burned via the subtitles filter if provided.
     */
    private function buildVideoFilter(?string $srtPath): string
    {
        $chain = sprintf(
            'scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d',
            self::OUTPUT_WIDTH,
            self::OUTPUT_HEIGHT,
            self::OUTPUT_WIDTH,
            self::OUTPUT_HEIGHT,
        );

        if ($srtPath !== null && is_file($srtPath)) {
            $escaped = $this->escapeSrtPathForFilter($srtPath);
            $chain .= sprintf(",subtitles='%s':force_style='%s'", $escaped, self::CAPTION_STYLE);
        }

        return $chain;
    }

    /**
     * FFmpeg's filtergraph parser needs Windows paths escaped: backslashes
     * doubled and drive-letter colons escaped.
     */
    private function escapeSrtPathForFilter(string $path): string
    {
        $forwardSlashed = str_replace('\\', '/', $path);

        return str_replace(':', '\\:', $forwardSlashed);
    }
}
