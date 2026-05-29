<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Stage 5 — Cuts the HeyGen MP4 into 3-5 short clips at Claude-identified
 * timestamps, each rendered in 9:16, 1:1, and 16:9 aspect ratios.
 */
class FFmpegService
{
    public function cutClips(Article $article, PipelineAsset $videoAsset, array $timestamps): array
    {
        $assets = [];
        $sourceVideo = $videoAsset->local_path;
        $outDir = dirname($sourceVideo).'/clips';
        @mkdir($outDir, 0755, true);

        foreach ($timestamps as $i => $ts) {
            $start = $this->toSeconds($ts['start']);
            $end = $this->toSeconds($ts['end']);
            $duration = $end - $start;
            if ($duration <= 0) {
                continue;
            }

            foreach ([
                ['ratio' => '9:16', 'filter' => 'crop=ih*9/16:ih,scale=1080:1920', 'tag' => '9x16'],
                ['ratio' => '1:1',  'filter' => 'crop=ih:ih,scale=1080:1080',     'tag' => '1x1'],
                ['ratio' => '16:9', 'filter' => 'scale=1920:1080',                'tag' => '16x9'],
            ] as $aspect) {
                $clipPath = "{$outDir}/clip_{$i}_{$aspect['tag']}.mp4";

                $process = new Process([
                    'ffmpeg', '-y',
                    '-ss', (string) $start,
                    '-i', $sourceVideo,
                    '-t', (string) $duration,
                    '-vf', $aspect['filter'],
                    '-c:v', 'libx264', '-preset', 'fast', '-crf', '23',
                    '-c:a', 'aac', '-b:a', '128k',
                    $clipPath,
                ]);
                $process->setTimeout(120);
                $process->run();

                if (! $process->isSuccessful()) {
                    Log::channel('pipeline')->error("FFmpeg failed for clip $i $aspect[ratio]: ".$process->getErrorOutput());

                    continue;
                }

                $assets[] = PipelineAsset::create([
                    'article_id' => $article->id,
                    'kind' => 'clip',
                    'template_type' => 'video_clip',
                    'variant' => "clip_{$i}_{$aspect['tag']}",
                    'aspect_ratio' => $aspect['ratio'],
                    'duration_seconds' => $duration,
                    'local_path' => $clipPath,
                ]);
            }
        }

        Log::channel('pipeline')->info('FFmpeg produced '.count($assets)." clips for article {$article->id}");

        return $assets;
    }

    private function toSeconds(string $ts): int
    {
        $parts = explode(':', $ts);
        if (count($parts) === 2) {
            return ((int) $parts[0]) * 60 + (int) $parts[1];
        }
        if (count($parts) === 3) {
            return ((int) $parts[0]) * 3600 + ((int) $parts[1]) * 60 + (int) $parts[2];
        }

        return (int) $ts;
    }
}
