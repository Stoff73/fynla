<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Models\Insights\InsightArticle;
use App\Services\Pipeline\Prompts\BrandVoicePrompt;
use App\Services\Pipeline\Prompts\ScriptOutputSchema;
use RuntimeException;

/**
 * Generates a structured 60-second video script from a published
 * InsightArticle by prompting Anthropic Opus and validating the JSON output
 * against ScriptOutputSchema.
 *
 * Returns both the parsed data and the metadata needed to record the run
 * (cost, tokens, model, prompt version) on the PipelineArticle row.
 */
class VideoScriptGeneratorService
{
    public function __construct(
        private readonly AnthropicOpusClient $anthropic,
        private readonly BrandVoicePrompt $prompt,
    ) {}

    /**
     * @return array{
     *     data: array<string,mixed>,
     *     model: string,
     *     prompt_version: string,
     *     cost_gbp: float,
     *     usage: array<string,int>,
     * }
     */
    public function generate(InsightArticle $article): array
    {
        $userMessage = $this->buildUserMessage($article);

        $completion = $this->anthropic->complete(
            $this->prompt->systemBlocks(),
            [
                ['role' => 'user', 'content' => $userMessage],
            ]
        );

        try {
            $data = ScriptOutputSchema::parse($completion['text']);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Video-script generator produced invalid output: '.$e->getMessage(),
                previous: $e,
            );
        }

        return [
            'data' => $data,
            'model' => (string) config('pipeline.anthropic.model', 'claude-opus-4-7'),
            'prompt_version' => BrandVoicePrompt::VERSION,
            'cost_gbp' => $completion['gbp'],
            'usage' => $completion['usage'],
        ];
    }

    private function buildUserMessage(InsightArticle $article): string
    {
        $bodyText = $this->flattenBody($article);

        return sprintf(
            "Source article title: %s\n\nSource article published on: %s\n\nSource article body (plain text):\n\n%s\n\nProduce the JSON described in your system prompt.",
            $article->title,
            optional($article->published_at)->toFormattedDateString() ?? 'unknown date',
            $bodyText
        );
    }

    /**
     * Reduce the article's block-structured body_blocks to plain prose.
     * Handles the canonical Fynla block types (see App\Services\Insights\BlockValidator):
     *   heading      → "## text"
     *   paragraph    → HTML stripped, on its own line
     *   list         → "- item" per bullet
     *   pull_quote   → "> quote"
     *   callout      → HTML stripped, on its own line
     *   key_takeaways → "- takeaway" per item
     * Other block types (image, divider, cta_button, tax_year_stat,
     * related_articles) contribute no meaningful text and are skipped.
     *
     * Falls back to summary + title if body_blocks is empty.
     */
    private function flattenBody(InsightArticle $article): string
    {
        $blocks = $article->body_blocks;

        if (! is_array($blocks) || $blocks === []) {
            return trim(($article->summary ?? '').' '.$article->title);
        }

        $lines = [];
        foreach ($blocks as $block) {
            if (! is_array($block) || ! isset($block['type'])) {
                continue;
            }

            $piece = match ($block['type']) {
                'heading' => isset($block['text']) ? "\n## ".trim((string) $block['text']) : null,
                'paragraph' => isset($block['html']) ? $this->htmlToText((string) $block['html']) : null,
                'callout' => isset($block['html']) ? $this->htmlToText((string) $block['html']) : null,
                'pull_quote' => isset($block['text']) ? '> '.trim((string) $block['text']) : null,
                'list' => $this->renderList($block),
                'key_takeaways' => $this->renderList($block, itemsKey: 'takeaways'),
                default => null,
            };

            if (is_string($piece) && trim($piece) !== '') {
                $lines[] = $piece;
            }
        }

        return trim(implode("\n\n", $lines));
    }

    private function htmlToText(string $html): string
    {
        $decoded = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $decoded) ?? $decoded);
    }

    private function renderList(array $block, string $itemsKey = 'items'): ?string
    {
        $items = $block[$itemsKey] ?? [];
        if (! is_array($items) || $items === []) {
            return null;
        }

        $rendered = [];
        foreach ($items as $item) {
            if (is_string($item) && trim($item) !== '') {
                $rendered[] = '- '.trim($item);
            } elseif (is_array($item)) {
                $text = $item['text'] ?? $item['html'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    $rendered[] = '- '.$this->htmlToText($text);
                }
            }
        }

        return $rendered === [] ? null : implode("\n", $rendered);
    }
}
