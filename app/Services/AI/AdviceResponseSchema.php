<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * S1.6 — Schema for the `advice_response` SSE event payload.
 *
 * The canonical structured contract for any recommendation-mode turn:
 * the model's free-text reply continues to stream as `content` events
 * (signposting + brief commentary), but the substantive recommendation
 * data — headline, key figures, breakdowns, ranked recommendations,
 * next steps — is delivered as ONE `advice_response` SSE event with
 * deterministic fields sourced from `orchestrateAnalysis` engine output.
 *
 * The contract:
 *   {
 *     type: 'advice_response',
 *     headline: string,                  REQUIRED — single-sentence summary
 *     key_figures: KeyFigure[],          REQUIRED (may be empty array)
 *     breakdowns: Breakdown[],           REQUIRED (may be empty array)
 *     recommendations: Recommendation[], REQUIRED (may be empty array)
 *     next_steps: NextStep[],            REQUIRED (may be empty array)
 *     signposting: string,               REQUIRED — the FCA signposting suffix
 *   }
 *
 *   KeyFigure       = { label: string, value: string }
 *   Breakdown       = { label: string, value: string, detail?: string }
 *   Recommendation  = { title: string, priority: 'high'|'medium'|'low'|'info',
 *                       reason: string, action?: string, route_path?: string }
 *   NextStep        = { label: string, route_path: string }
 *
 * The validator is strict — any non-conforming payload throws. Per CSJ
 * direction (2026-04-27 architectural blocker note in the Sprint 1 plan):
 * we BLOCK rather than log-and-flag, because the previous post-hoc
 * scrubber was the lie that motivated this whole contract.
 */
final class AdviceResponseSchema
{
    public const TYPE = 'advice_response';

    public const REQUIRED_KEYS = [
        'type',
        'headline',
        'key_figures',
        'breakdowns',
        'recommendations',
        'next_steps',
        'signposting',
    ];

    public const VALID_PRIORITIES = ['high', 'medium', 'low', 'info'];

    public const FCA_SIGNPOSTING = 'For regulated advice personal to your circumstances, speak to a qualified financial adviser.';

    /**
     * Validate a payload. Throws AdviceResponseSchemaException on any
     * structural violation. Returns void on success.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws AdviceResponseSchemaException
     */
    public static function validate(array $payload): void
    {
        foreach (self::REQUIRED_KEYS as $key) {
            if (! array_key_exists($key, $payload)) {
                throw new AdviceResponseSchemaException("Missing required key: {$key}");
            }
        }

        if ($payload['type'] !== self::TYPE) {
            throw new AdviceResponseSchemaException("Field 'type' must be '".self::TYPE."', got: ".var_export($payload['type'], true));
        }

        if (! is_string($payload['headline']) || trim($payload['headline']) === '') {
            throw new AdviceResponseSchemaException("Field 'headline' must be a non-empty string");
        }

        if (! is_string($payload['signposting']) || trim($payload['signposting']) === '') {
            throw new AdviceResponseSchemaException("Field 'signposting' must be a non-empty string");
        }

        self::validateArrayOfShape($payload['key_figures'], 'key_figures', ['label', 'value'], optional: []);
        self::validateArrayOfShape($payload['breakdowns'], 'breakdowns', ['label', 'value'], optional: ['detail']);
        self::validateArrayOfShape($payload['next_steps'], 'next_steps', ['label', 'route_path'], optional: []);

        if (! is_array($payload['recommendations'])) {
            throw new AdviceResponseSchemaException("Field 'recommendations' must be an array");
        }

        foreach ($payload['recommendations'] as $i => $rec) {
            if (! is_array($rec)) {
                throw new AdviceResponseSchemaException("recommendations[{$i}] must be an object");
            }
            foreach (['title', 'priority', 'reason'] as $required) {
                if (! array_key_exists($required, $rec)) {
                    throw new AdviceResponseSchemaException("recommendations[{$i}] missing required field: {$required}");
                }
            }
            if (! in_array($rec['priority'], self::VALID_PRIORITIES, true)) {
                throw new AdviceResponseSchemaException("recommendations[{$i}].priority must be one of ".implode(',', self::VALID_PRIORITIES).", got: ".var_export($rec['priority'], true));
            }
        }
    }

    /**
     * @param  list<string>  $required
     * @param  list<string>  $optional
     */
    private static function validateArrayOfShape(mixed $value, string $field, array $required, array $optional): void
    {
        if (! is_array($value)) {
            throw new AdviceResponseSchemaException("Field '{$field}' must be an array");
        }

        $allowed = array_merge($required, $optional);

        foreach ($value as $i => $item) {
            if (! is_array($item)) {
                throw new AdviceResponseSchemaException("{$field}[{$i}] must be an object");
            }
            foreach ($required as $key) {
                if (! array_key_exists($key, $item)) {
                    throw new AdviceResponseSchemaException("{$field}[{$i}] missing required field: {$key}");
                }
            }
            foreach (array_keys($item) as $key) {
                if (! in_array($key, $allowed, true)) {
                    throw new AdviceResponseSchemaException("{$field}[{$i}] has unexpected field: {$key}");
                }
            }
        }
    }
}
