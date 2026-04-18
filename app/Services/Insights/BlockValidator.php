<?php

declare(strict_types=1);

namespace App\Services\Insights;

class BlockValidator
{
    private const CALLOUT_VARIANTS = ['info', 'tip', 'warning', 'success'];

    private const HEADING_LEVELS = [2, 3, 4];

    private const IMAGE_ALIGNMENTS = ['full', 'left', 'right'];

    private const CTA_STYLES = ['primary', 'secondary'];

    public function validate(mixed $blocks): array
    {
        if (! is_array($blocks)) {
            return ['body_blocks must be an array'];
        }

        $errors = [];
        foreach ($blocks as $index => $block) {
            $prefix = "block {$index}: ";
            if (! is_array($block) || ! isset($block['type'])) {
                $errors[] = $prefix.'missing type';

                continue;
            }

            foreach ($this->validateBlock($block) as $e) {
                $errors[] = $prefix.$e;
            }
        }

        return $errors;
    }

    private function validateBlock(array $block): array
    {
        return match ($block['type']) {
            'heading' => $this->validateHeading($block),
            'paragraph' => $this->validateParagraph($block),
            'list' => $this->validateList($block),
            'image' => $this->validateImage($block),
            'pull_quote' => $this->validatePullQuote($block),
            'callout' => $this->validateCallout($block),
            'divider' => [],
            'cta_button' => $this->validateCtaButton($block),
            'tax_year_stat' => $this->validateTaxYearStat($block),
            'related_articles' => $this->validateRelatedArticles($block),
            'key_takeaways' => $this->validateKeyTakeaways($block),
            default => ["Unknown block type: {$block['type']}"],
        };
    }

    private function validateHeading(array $b): array
    {
        $errors = [];
        if (! isset($b['level']) || ! in_array($b['level'], self::HEADING_LEVELS, true)) {
            $errors[] = 'heading level must be 2, 3, or 4';
        }
        if (! isset($b['text']) || ! is_string($b['text']) || $b['text'] === '') {
            $errors[] = 'heading text is required';
        }

        return $errors;
    }

    private function validateParagraph(array $b): array
    {
        if (! isset($b['html']) || ! is_string($b['html'])) {
            return ['paragraph html is required'];
        }

        return [];
    }

    private function validateList(array $b): array
    {
        $errors = [];
        if (! isset($b['ordered']) || ! is_bool($b['ordered'])) {
            $errors[] = 'list ordered must be boolean';
        }
        if (! isset($b['items']) || ! is_array($b['items']) || $b['items'] === []) {
            $errors[] = 'list items must be a non-empty array';
        }

        return $errors;
    }

    private function validateImage(array $b): array
    {
        $errors = [];
        foreach (['path', 'alt'] as $req) {
            if (! isset($b[$req]) || ! is_string($b[$req]) || trim($b[$req]) === '') {
                $errors[] = "image {$req} is required";
            }
        }
        if (isset($b['alignment']) && ! in_array($b['alignment'], self::IMAGE_ALIGNMENTS, true)) {
            $errors[] = 'image alignment must be full, left, or right';
        }

        return $errors;
    }

    private function validatePullQuote(array $b): array
    {
        if (! isset($b['text']) || ! is_string($b['text']) || $b['text'] === '') {
            return ['pull_quote text is required'];
        }

        return [];
    }

    private function validateCallout(array $b): array
    {
        $errors = [];
        if (! isset($b['variant']) || ! in_array($b['variant'], self::CALLOUT_VARIANTS, true)) {
            $errors[] = 'callout variant must be info, tip, warning, or success';
        }
        if (! isset($b['html']) || ! is_string($b['html'])) {
            $errors[] = 'callout html is required';
        }

        return $errors;
    }

    private function validateCtaButton(array $b): array
    {
        $errors = [];
        foreach (['label', 'href'] as $req) {
            if (! isset($b[$req]) || ! is_string($b[$req])) {
                $errors[] = "cta_button {$req} is required";
            }
        }
        if (isset($b['style']) && ! in_array($b['style'], self::CTA_STYLES, true)) {
            $errors[] = 'cta_button style must be primary or secondary';
        }

        return $errors;
    }

    private function validateTaxYearStat(array $b): array
    {
        $errors = [];
        foreach (['stat_key', 'label'] as $req) {
            if (! isset($b[$req]) || ! is_string($b[$req]) || $b[$req] === '') {
                $errors[] = "tax_year_stat {$req} is required";
            }
        }

        return $errors;
    }

    private function validateRelatedArticles(array $b): array
    {
        if (! isset($b['article_ids']) || ! is_array($b['article_ids']) || $b['article_ids'] === []) {
            return ['related_articles article_ids must be a non-empty array'];
        }
        if (count($b['article_ids']) > 4) {
            return ['related_articles allows at most 4 articles'];
        }

        return [];
    }

    private function validateKeyTakeaways(array $b): array
    {
        if (! isset($b['bullets']) || ! is_array($b['bullets']) || $b['bullets'] === []) {
            return ['key_takeaways bullets must be a non-empty array'];
        }

        return [];
    }
}
