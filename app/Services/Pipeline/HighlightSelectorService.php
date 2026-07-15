<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Models\Insights\InsightArticle;
use RuntimeException;

/**
 * Given a Whisper transcript for a source video that's too long to post
 * as-is, ask Claude Opus to pick 1–3 highlight moments to clip. Returns
 * start/end timestamps and a one-line reason per highlight.
 *
 * Not called for videos ≤ 75s — those are cropped whole.
 */
class HighlightSelectorService
{
    public function __construct(
        private readonly AnthropicOpusClient $anthropic,
    ) {}

    /**
     * @param  array{segments: list<array{start:float,end:float,text:string}>,text:string}  $transcript
     * @return list<array{start:float,end:float,reason:string}>
     */
    public function select(InsightArticle $article, array $transcript, int $maxHighlights = 3): array
    {
        $completion = $this->anthropic->complete(
            $this->systemBlocks(),
            [[
                'role' => 'user',
                'content' => $this->userMessage($article, $transcript, $maxHighlights),
            ]],
        );

        try {
            $data = $this->parse($completion['text']);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Highlight selector produced invalid output: '.$e->getMessage(),
                previous: $e,
            );
        }

        $totalDuration = $this->totalDuration($transcript);
        $picked = [];
        foreach ($data['highlights'] ?? [] as $highlight) {
            $start = max(0.0, (float) ($highlight['start'] ?? 0));
            $end = min($totalDuration, (float) ($highlight['end'] ?? $start + 30));
            if ($end - $start < 5) {
                continue;
            }
            if ($end - $start > 75) {
                $end = $start + 75;
            }

            $picked[] = [
                'start' => $start,
                'end' => $end,
                'reason' => (string) ($highlight['reason'] ?? ''),
            ];

            if (count($picked) >= $maxHighlights) {
                break;
            }
        }

        if ($picked === []) {
            throw new RuntimeException('Highlight selector returned zero usable highlights.');
        }

        return $picked;
    }

    /**
     * @return list<array{type:'text',text:string,cache_control?:array{type:'ephemeral'}}>
     */
    private function systemBlocks(): array
    {
        return [[
            'type' => 'text',
            'text' => <<<'PROMPT'
            You are the highlight selector for Fynla's marketing pipeline. Given the
            transcript of a recorded video, pick up to N moments that will make the
            best short vertical clips for Instagram Reels, Facebook Reels and TikTok.

            Selection criteria:
            - Each moment must be 20–75 seconds long. Aim for ~60s where possible.
            - Prefer moments that stand alone — a viewer with no context should get
              the point.
            - Prefer moments with a clear hook in the first two sentences.
            - Prefer moments where a concrete number, analogy, or story lands.
            - Do not pick throat-clearing intros, sign-offs ("thanks for watching"),
              or tool/software mentions unless directly relevant.
            - Return moments in the order they appear in the video.

            Output ONLY JSON (no code fence, no preamble):

            {
              "highlights": [
                {
                  "start": 34.0,
                  "end": 92.5,
                  "reason": "One-sentence justification for picking this moment."
                }
              ]
            }

            `start` and `end` are seconds (float). `reason` is one sentence for
            reviewer sanity-checking; it never appears in the clip.
            PROMPT,
            'cache_control' => ['type' => 'ephemeral'],
        ]];
    }

    private function userMessage(InsightArticle $article, array $transcript, int $maxHighlights): string
    {
        $lines = [];
        $lines[] = "Article this video supports: {$article->title}";
        if ($article->summary) {
            $lines[] = 'Article summary: '.$article->summary;
        }
        $lines[] = "Pick up to {$maxHighlights} highlights.";
        $lines[] = '';
        $lines[] = '--- Transcript (start–end seconds, text) ---';

        foreach ($transcript['segments'] as $segment) {
            $lines[] = sprintf(
                '[%.1f–%.1f] %s',
                $segment['start'],
                $segment['end'],
                $segment['text'],
            );
        }

        return implode("\n", $lines);
    }

    private function parse(string $raw): array
    {
        $trimmed = trim($raw);
        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*|```\s*$/i', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }
        if (! str_starts_with($trimmed, '{')) {
            $firstBrace = strpos($trimmed, '{');
            $lastBrace = strrpos($trimmed, '}');
            if ($firstBrace !== false && $lastBrace !== false) {
                $trimmed = substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1);
            }
        }

        $data = json_decode($trimmed, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('Highlight response was not a JSON object.');
        }

        return $data;
    }

    private function totalDuration(array $transcript): float
    {
        $last = end($transcript['segments']);

        return $last === false ? 0.0 : (float) $last['end'];
    }
}
