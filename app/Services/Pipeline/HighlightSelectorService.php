<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use RuntimeException;

/**
 * Given a Whisper transcript for a source video, ask Claude Opus to pick a
 * couple of short (20–30s) highlight moments to clip for social. Returns
 * start/end timestamps and a one-line reason per highlight.
 *
 * Captions are NOT burned into clips — they're added later in the editing
 * stage — so the transcript here is used only for choosing the moments.
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
    /**
     * @param  list<array{start:float,end:float}>  $avoidRanges  previously-rejected ranges to steer away from
     */
    public function select(string $articleTitle, ?string $articleSummary, array $transcript, int $maxHighlights = 3, array $avoidRanges = [], ?string $feedback = null): array
    {
        $completion = $this->anthropic->complete(
            $this->systemBlocks(),
            [[
                'role' => 'user',
                'content' => $this->userMessage($articleTitle, $articleSummary, $transcript, $maxHighlights, $avoidRanges, $feedback),
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
            $end = min($totalDuration, (float) ($highlight['end'] ?? $start + 25));
            if ($end - $start < 12) {
                continue;
            }
            if ($end - $start > 30) {
                $end = $start + 30;
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
            - Each moment must be 20–30 seconds long. Aim for ~25s.
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
                  "end": 59.0,
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

    private function userMessage(string $title, ?string $summary, array $transcript, int $maxHighlights, array $avoidRanges = [], ?string $feedback = null): string
    {
        $lines = [];
        $lines[] = "Article this video supports: {$title}";
        if ($summary !== null && $summary !== '') {
            $lines[] = 'Article summary: '.$summary;
        }
        $lines[] = "Pick up to {$maxHighlights} highlights.";

        if ($avoidRanges !== []) {
            $ranges = implode(', ', array_map(
                static fn (array $r) => sprintf('%.1f–%.1fs', $r['start'] ?? 0, $r['end'] ?? 0),
                $avoidRanges,
            ));
            $lines[] = "IMPORTANT: a previous pick was rejected. Do NOT reuse these ranges: {$ranges}. Choose a genuinely different moment.";
        }
        if ($feedback !== null && trim($feedback) !== '') {
            $lines[] = 'Reviewer feedback to address: '.trim($feedback);
        }

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
