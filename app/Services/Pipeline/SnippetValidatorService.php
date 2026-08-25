<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Second-pass AI check on the snippets the HighlightSelectorService proposed.
 *
 * The selector picks moments; this service audits them against the transcript
 * and asks: is each snippet actually a valid social clip — does it start on a
 * clean sentence, land a complete thought, hook in the first words, and make
 * sense with no other context? Where a snippet is nearly right, the model
 * returns adjusted in/out points and we re-cut to those. Where it can't be
 * salvaged, it's dropped rather than published half-formed.
 *
 * Non-fatal by design: if validation errors or the model returns nothing
 * usable, the original selector ranges are used unchanged.
 */
class SnippetValidatorService
{
    public function __construct(
        private readonly PipelineAiClient $ai,
    ) {}

    /**
     * @param  list<array{start:float,end:float,reason?:string}>  $snippets
     * @param  array{segments: list<array{start:float,end:float,text:string}>,text:string}  $transcript
     * @return list<array{start:float,end:float,reason:string}>
     */
    public function validate(array $snippets, array $transcript, float $sourceDuration): array
    {
        if ($snippets === [] || ! config('pipeline.video.snippet_validation_enabled', true)) {
            return $snippets;
        }

        try {
            $completion = $this->ai->complete(
                $this->systemBlocks(),
                [['role' => 'user', 'content' => $this->userMessage($snippets, $transcript)]],
            );
            $data = $this->parse($completion['text']);
        } catch (\Throwable $e) {
            Log::channel('pipeline')->warning('Snippet validation failed — using selector ranges unchanged.', [
                'error' => $e->getMessage(),
            ]);

            return $snippets;
        }

        $min = (float) config('pipeline.video.snippet_min_seconds', 12);
        $max = (float) config('pipeline.video.snippet_max_seconds', 18);

        $validated = [];
        foreach ($data['snippets'] ?? [] as $i => $checked) {
            if (($checked['valid'] ?? false) !== true) {
                Log::channel('pipeline')->info('Snippet rejected by validator.', [
                    'index' => $i,
                    'reason' => (string) ($checked['reason'] ?? 'no reason given'),
                ]);

                continue;
            }

            $original = $snippets[$i] ?? null;
            $start = max(0.0, (float) ($checked['start'] ?? $original['start'] ?? 0));
            $end = min($sourceDuration, (float) ($checked['end'] ?? $original['end'] ?? 0));

            // Keep the adjusted cut inside the allowed snippet length.
            if ($end - $start < $min || $end - $start > $max) {
                $end = min($sourceDuration, $start + (float) config('pipeline.video.snippet_target_seconds', 15));
            }
            if ($end - $start < $min) {
                continue;
            }

            $validated[] = [
                'start' => $start,
                'end' => $end,
                'reason' => (string) ($checked['reason'] ?? $original['reason'] ?? ''),
            ];
        }

        if ($validated === []) {
            Log::channel('pipeline')->warning('Validator rejected every snippet — using selector ranges unchanged.');

            return $snippets;
        }

        return $validated;
    }

    /**
     * @return list<array{type:'text',text:string,cache_control?:array{type:'ephemeral'}}>
     */
    private function systemBlocks(): array
    {
        return [[
            'type' => 'text',
            'text' => <<<'PROMPT'
            You are the snippet QA check for Fynla's marketing pipeline. You are
            given a transcript and a set of proposed short social clips (start/end
            in seconds). Judge each one and, where it is nearly right, correct the
            in/out points.

            A snippet is VALID only if all of these hold:
            - It starts at a clean sentence boundary — not mid-sentence, not
              mid-word, not on a filler word ("so", "um", "and then").
            - It ends on a completed thought.
            - It makes sense entirely on its own, with no reference to something
              explained earlier in the video.
            - It hooks in the first spoken words — a question, a number, a claim.
              A slow wind-up is NOT valid for a scroll feed.
            - It carries ONE clear idea a viewer can repeat afterwards.

            If a snippet is close but clipped awkwardly, set valid=true and return
            CORRECTED start/end that snap to the nearest clean sentence boundaries
            in the transcript. Keep the corrected length between 12 and 18 seconds.

            If a snippet cannot be saved (rambling, no hook, needs prior context,
            or is off-topic), set valid=false and say why in one short sentence.

            Return the snippets in the SAME ORDER you received them.

            Output ONLY JSON (no code fence, no preamble):

            {
              "snippets": [
                {
                  "valid": true,
                  "start": 34.0,
                  "end": 49.0,
                  "reason": "Opens on a direct question and lands the £252 figure."
                }
              ]
            }
            PROMPT,
            'cache_control' => ['type' => 'ephemeral'],
        ]];
    }

    private function userMessage(array $snippets, array $transcript): string
    {
        $lines = ['--- Proposed snippets ---'];
        foreach ($snippets as $i => $s) {
            $lines[] = sprintf(
                '[%d] %.1f–%.1fs — %s',
                $i,
                $s['start'] ?? 0,
                $s['end'] ?? 0,
                $s['reason'] ?? '(no reason)',
            );
        }

        $lines[] = '';
        $lines[] = '--- Transcript (start–end seconds, text) ---';
        foreach ($transcript['segments'] ?? [] as $segment) {
            $lines[] = sprintf('[%.1f–%.1f] %s', $segment['start'], $segment['end'], $segment['text']);
        }

        return implode("\n", $lines);
    }

    private function parse(string $raw): array
    {
        $trimmed = trim($raw);
        if (str_starts_with($trimmed, '```')) {
            $trimmed = trim(preg_replace('/^```(?:json)?\s*|```\s*$/i', '', $trimmed) ?? $trimmed);
        }
        if (! str_starts_with($trimmed, '{')) {
            $first = strpos($trimmed, '{');
            $last = strrpos($trimmed, '}');
            if ($first !== false && $last !== false) {
                $trimmed = substr($trimmed, $first, $last - $first + 1);
            }
        }

        $data = json_decode($trimmed, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('Snippet validator response was not a JSON object.');
        }

        return $data;
    }
}
