<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Social;

use App\Models\Insights\InsightArticle;
use App\Services\Pipeline\AnthropicOpusClient;
use RuntimeException;

/**
 * Chooses 2–5 hashtags per social post. Rule 3 of the social-media-posts
 * skill: hashtags must be based on common relevant searches, not vanity
 * tags. The banned-list (config pipeline.social.banned_hashtags) filters
 * out generic/spammy tags like #finance, #fyp.
 *
 * MVP: prompts Claude Opus for hashtag suggestions using the article
 * title + summary + platform norms. Later this can be extended to feed
 * real search-volume data from Ahrefs/SEMrush — extend the picker,
 * don't bypass it.
 */
class HashtagPicker
{
    public function __construct(
        private readonly AnthropicOpusClient $anthropic,
    ) {}

    /**
     * @return list<string> Hashtags including the leading '#'.
     */
    public function pick(InsightArticle $article, string $platform, ?int $count = null): array
    {
        $target = $count ?? (int) config('pipeline.social.default_hashtag_count', 3);
        $min = (int) config('pipeline.social.hashtag_min', 2);
        $max = (int) config('pipeline.social.hashtag_max', 5);
        $target = max($min, min($max, $target));

        $banned = array_map('strtolower', (array) config('pipeline.social.banned_hashtags', []));

        $completion = $this->anthropic->complete(
            $this->systemBlocks($platform, $target, $banned),
            [[
                'role' => 'user',
                'content' => $this->userMessage($article),
            ]],
        );

        $tags = $this->parse($completion['text']);

        $tags = $this->normaliseAndFilter($tags, $banned, $min, $max);
        if ($tags === []) {
            throw new RuntimeException('HashtagPicker returned zero usable hashtags after filtering.');
        }

        return $tags;
    }

    /**
     * @param  list<string>  $banned
     * @return list<array{type:'text',text:string,cache_control?:array{type:'ephemeral'}}>
     */
    private function systemBlocks(string $platform, int $target, array $banned): array
    {
        $bannedLine = implode(', ', $banned);

        return [[
            'type' => 'text',
            'text' => <<<PROMPT
            You are the hashtag picker for Fynla's marketing pipeline. Given a UK
            financial planning article, produce a JSON array of {$target} hashtags
            for a {$platform} post.

            Rules:
            - Return between 2 and 5 hashtags, ideally {$target}.
            - Choose based on searches a UK financial-content viewer would run —
              specific over broad. #ISAallowance is better than #saving.
            - No generic or vanity tags: {$bannedLine}
            - No hashtags with more than 40 characters.
            - British English spelling.
            - Lowercase or camelCase are both fine; be consistent with what the
              platform's own audience actually uses.
            - No emojis inside hashtags.
            - Include the leading '#' in each string.

            Output ONLY a JSON array of strings, nothing else. Example:

            ["#ISAallowance", "#UKtax", "#personalfinance"]
            PROMPT,
            'cache_control' => ['type' => 'ephemeral'],
        ]];
    }

    private function userMessage(InsightArticle $article): string
    {
        return sprintf(
            "Article title: %s\n\nArticle summary: %s\n\nArticle category: %s",
            $article->title,
            $article->summary ?? '(no summary)',
            $article->category ?? 'financial-planning',
        );
    }

    private function parse(string $raw): array
    {
        $trimmed = trim($raw);
        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*|```\s*$/i', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }
        if (! str_starts_with($trimmed, '[')) {
            $firstBracket = strpos($trimmed, '[');
            $lastBracket = strrpos($trimmed, ']');
            if ($firstBracket !== false && $lastBracket !== false) {
                $trimmed = substr($trimmed, $firstBracket, $lastBracket - $firstBracket + 1);
            }
        }

        $data = json_decode($trimmed, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('Hashtag response was not a JSON array.');
        }

        return $data;
    }

    /**
     * @param  list<mixed>  $tags
     * @param  list<string>  $banned
     * @return list<string>
     */
    private function normaliseAndFilter(array $tags, array $banned, int $min, int $max): array
    {
        $seen = [];
        $out = [];

        foreach ($tags as $tag) {
            if (! is_string($tag)) {
                continue;
            }
            $trimmed = trim($tag);
            if ($trimmed === '') {
                continue;
            }
            if ($trimmed[0] !== '#') {
                $trimmed = '#'.$trimmed;
            }
            if (strlen($trimmed) > 40) {
                continue;
            }
            $lower = strtolower($trimmed);
            if (in_array($lower, $banned, true)) {
                continue;
            }
            if (isset($seen[$lower])) {
                continue;
            }

            $seen[$lower] = true;
            $out[] = $trimmed;

            if (count($out) >= $max) {
                break;
            }
        }

        return count($out) >= $min ? $out : [];
    }
}
