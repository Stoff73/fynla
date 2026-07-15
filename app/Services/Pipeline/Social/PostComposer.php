<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Social;

use App\Models\Insights\InsightArticle;
use App\Services\Pipeline\AnthropicOpusClient;
use RuntimeException;

/**
 * Generates two caption variants (A + B) per post. Rule 4 of the
 * social-media-posts skill: playful, credible, UK spelling, no
 * personalised advice, no emojis in first 40 chars, no "link in bio",
 * hashtags separate from copy (they're merged in by the caller).
 *
 * Variant A targets the default Fynla audience (informed novice, 30–55).
 * Variant B targets the audience-under-test for the week (younger,
 * older, higher-income, pre-retirement — cycled by the scheduler).
 */
class PostComposer
{
    /** @var list<string> Rotated for variant B — one hypothesis per week. */
    private const TEST_AUDIENCES = [
        'younger UK viewers (22–29) new to their first professional salary',
        'higher-income UK viewers (40–55) with over £150k annual household income',
        'pre-retirement UK viewers (55–65) within ten years of retirement',
        'first-time buyers actively saving for a deposit',
    ];

    public function __construct(
        private readonly AnthropicOpusClient $anthropic,
    ) {}

    /**
     * @return array{A: string, B: string, audience_b: string}
     */
    public function compose(InsightArticle $article, string $platform): array
    {
        $audienceB = $this->pickTestAudience();

        $completion = $this->anthropic->complete(
            $this->systemBlocks($platform, $audienceB),
            [[
                'role' => 'user',
                'content' => $this->userMessage($article),
            ]],
        );

        $data = $this->parse($completion['text']);

        return [
            'A' => (string) ($data['variant_a'] ?? ''),
            'B' => (string) ($data['variant_b'] ?? ''),
            'audience_b' => $audienceB,
        ];
    }

    private function pickTestAudience(): string
    {
        // Rotate deterministically by ISO week number so different articles in
        // the same week get the SAME hypothesis under test.
        $week = (int) now()->isoWeek;

        return self::TEST_AUDIENCES[$week % count(self::TEST_AUDIENCES)];
    }

    /**
     * @return list<array{type:'text',text:string,cache_control?:array{type:'ephemeral'}}>
     */
    private function systemBlocks(string $platform, string $audienceB): array
    {
        $charLimits = match ($platform) {
            'instagram' => '2,200 character maximum. Aim for 100–150 words.',
            'facebook' => '~500 character maximum in practice. Aim for 60–100 words.',
            'tiktok' => '300 character maximum. Aim for 40–70 words. Punchy.',
            default => '~150 words.',
        };

        return [[
            'type' => 'text',
            'text' => <<<PROMPT
            You are the post composer for Fynla's marketing pipeline. Given a UK
            financial-planning insight article, produce TWO caption variants for
            a {$platform} post.

            Voice rules (non-negotiable):
            - Light-hearted but credible. Finance is serious; the delivery isn't.
            - British English. Never say 401(k), IRS, or Social Security. Use
              ISA, HMRC, State Pension. Optimise, whilst, quid.
            - Speak to one person ("you", "your"). Never "everyone" or "you all".
            - No personalised financial advice. General education only.
            - No product-specific names unless the source article uses them first.
            - No emojis in the first 40 characters (thumbnail preview cuts them).
              One or two emojis later in the caption are fine, if they add.
            - No "link in bio" phrasing. No "swipe up".
            - Do NOT include hashtags — hashtags are appended separately by the
              pipeline.
            - Do NOT include the destination URL — the pipeline adds it.
            - {$charLimits}

            Variant strategy:
            - Variant A: aimed at the default Fynla audience — informed but not
              confident UK viewers, ages 30–55.
            - Variant B: aimed at {$audienceB}.
            - Both variants must be based on the same article and same 60-second
              clip, but written in the register that resonates with each
              audience.

            Output ONLY JSON, nothing else:

            {
              "variant_a": "…the full A caption, ready to post…",
              "variant_b": "…the full B caption, ready to post…"
            }
            PROMPT,
            'cache_control' => ['type' => 'ephemeral'],
        ]];
    }

    private function userMessage(InsightArticle $article): string
    {
        return sprintf(
            "Article title: %s\n\nArticle summary: %s",
            $article->title,
            $article->summary ?? '(no summary)',
        );
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
        if (! is_array($data) || ! isset($data['variant_a'], $data['variant_b'])) {
            throw new RuntimeException('Post composer response missing variant_a or variant_b.');
        }

        return $data;
    }
}
