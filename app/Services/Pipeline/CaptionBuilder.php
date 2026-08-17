<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use RuntimeException;

/**
 * Converts Whisper segments into an SRT subtitle file that FFmpeg can burn
 * into a clip. Handles offset-adjustment when the clip starts partway
 * through the source video (highlight case) — output timestamps are
 * relative to the clip, not the source.
 */
class CaptionBuilder
{
    /**
     * Build an SRT file covering [clipStart, clipEnd] of the source video's
     * transcript. Returns the absolute path to the .srt file.
     *
     * @param  array{segments: list<array{start:float,end:float,text:string}>}  $transcript
     */
    public function buildSrt(array $transcript, string $outputPath, float $clipStart = 0.0, ?float $clipEnd = null): string
    {
        $dir = dirname($outputPath);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Failed to create directory for SRT: {$dir}");
        }

        $index = 1;
        $lines = [];

        foreach ($transcript['segments'] as $segment) {
            $segStart = (float) $segment['start'];
            $segEnd = (float) $segment['end'];
            $text = trim((string) $segment['text']);

            if ($text === '') {
                continue;
            }

            if ($segEnd <= $clipStart) {
                continue;
            }
            if ($clipEnd !== null && $segStart >= $clipEnd) {
                break;
            }

            $adjustedStart = max(0.0, $segStart - $clipStart);
            $adjustedEnd = $clipEnd !== null
                ? min($clipEnd - $clipStart, $segEnd - $clipStart)
                : $segEnd - $clipStart;

            if ($adjustedEnd - $adjustedStart < 0.15) {
                continue;
            }

            $lines[] = (string) $index;
            $lines[] = $this->formatTimecode($adjustedStart).' --> '.$this->formatTimecode($adjustedEnd);
            $lines[] = $text;
            $lines[] = '';

            $index++;
        }

        file_put_contents($outputPath, implode("\n", $lines));

        return $outputPath;
    }

    private function formatTimecode(float $seconds): string
    {
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds - $hours * 3600) / 60);
        $secs = (int) floor($seconds - $hours * 3600 - $minutes * 60);
        $millis = (int) round(($seconds - floor($seconds)) * 1000);

        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secs, $millis);
    }
}
