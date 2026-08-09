<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs local `whisper` (openai-whisper Python package) as a subprocess to
 * generate a segment-level transcript from a video. Free — no API calls.
 *
 * Requires:
 *   pip install openai-whisper
 *
 * Model choice tuned for cost/quality of highlight-selection transcripts,
 * not for perfect captions: `small` runs in ~1× realtime on modern laptops
 * without a GPU and is accurate enough to pick highlight timestamps and
 * generate serviceable social-video captions.
 */
class LocalWhisperTranscriber
{
    public function __construct(
        private readonly int $timeoutSeconds = 1800,
    ) {}

    /**
     * Transcribe the given video file. Writes {video}.json (verbose_json) and
     * {video}.srt next to the source. Returns the absolute path to the JSON
     * transcript.
     */
    public function transcribe(string $videoPath): string
    {
        if (! is_file($videoPath)) {
            throw new RuntimeException("Video file not found: {$videoPath}");
        }

        $outputDir = dirname($videoPath);
        $model = (string) config('pipeline.whisper.model', 'small');
        $language = (string) config('pipeline.whisper.language', 'en');

        $process = new Process([
            'whisper',
            $videoPath,
            '--model', $model,
            '--language', $language,
            '--output_dir', $outputDir,
            '--output_format', 'all',
            '--verbose', 'False',
        ]);
        $process->setTimeout($this->timeoutSeconds);

        Log::channel('pipeline')->info('Whisper transcribe start.', [
            'video' => $videoPath,
            'model' => $model,
        ]);

        try {
            $process->mustRun();
        } catch (\Throwable $e) {
            Log::channel('pipeline')->error('Whisper transcription failed.', [
                'error' => $e->getMessage(),
                'stderr' => $process->getErrorOutput(),
                'stdout' => $process->getOutput(),
            ]);
            throw new RuntimeException('Whisper transcription failed: '.$e->getMessage(), previous: $e);
        }

        $basename = pathinfo($videoPath, PATHINFO_FILENAME);
        $jsonPath = $outputDir.DIRECTORY_SEPARATOR.$basename.'.json';

        if (! is_file($jsonPath)) {
            throw new RuntimeException("Whisper did not write expected transcript at {$jsonPath}");
        }

        Log::channel('pipeline')->info('Whisper transcribe done.', [
            'transcript' => $jsonPath,
        ]);

        return $jsonPath;
    }

    /**
     * Load a Whisper JSON transcript and return its normalised segments.
     *
     * @return array{
     *   text: string,
     *   segments: list<array{id:int,start:float,end:float,text:string}>,
     *   language: string
     * }
     */
    public function load(string $jsonPath): array
    {
        $raw = file_get_contents($jsonPath);
        if ($raw === false) {
            throw new RuntimeException("Failed to read transcript at {$jsonPath}");
        }

        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        $segments = [];
        foreach ($data['segments'] ?? [] as $index => $segment) {
            $segments[] = [
                'id' => (int) ($segment['id'] ?? $index),
                'start' => (float) ($segment['start'] ?? 0.0),
                'end' => (float) ($segment['end'] ?? 0.0),
                'text' => trim((string) ($segment['text'] ?? '')),
            ];
        }

        return [
            'text' => trim((string) ($data['text'] ?? '')),
            'segments' => $segments,
            'language' => (string) ($data['language'] ?? 'en'),
        ];
    }
}
