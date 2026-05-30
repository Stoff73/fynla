<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;

/**
 * CoALA Phase 5 PR 1 — FR-M11/M12/M13.
 *
 * `HasAiChat::costTelemetryMetadata` assembles the per-turn cost telemetry
 * fragment that is merged into `ai_messages.metadata`: the prompt-cache
 * hit/miss token counts and the GBP cost. It normalises the two providers'
 * differing token semantics before costing:
 *   - Anthropic reports `input_tokens` EXCLUDING cache read/creation.
 *   - xAI folds cached reads INTO `prompt_tokens`, and has no separate
 *     cache-creation tier, so regular input = prompt - cached, miss = 0.
 */
function invokeCostTelemetry(
    string $model,
    bool $isXai,
    int $totalInputTokens,
    int $totalOutputTokens,
    int $cacheReadTokens,
    int $cacheCreationTokens,
): array {
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionMethod($agent, 'costTelemetryMetadata');
    $reflection->setAccessible(true);

    return $reflection->invoke(
        $agent,
        $model,
        $isXai,
        $totalInputTokens,
        $totalOutputTokens,
        $cacheReadTokens,
        $cacheCreationTokens,
    );
}

beforeEach(function () {
    config()->set('ai_pricing.models', [
        'claude-haiku-4-5-20251001' => [
            'input' => 0.001,
            'output' => 0.002,
            'cache_write' => 0.00125,
            'cache_read' => 0.0001,
        ],
        'grok-4.3' => [
            'input' => 0.001,
            'output' => 0.002,
            'cache_write' => 0.001,
            'cache_read' => 0.0001,
        ],
    ]);
    config()->set('ai_pricing.default', [
        'input' => 0.003,
        'output' => 0.006,
        'cache_write' => 0.00375,
        'cache_read' => 0.0003,
    ]);
});

describe('HasAiChat::costTelemetryMetadata (FR-M11/M12/M13)', function () {
    it('records hit/miss tokens and gbp cost for an Anthropic turn', function () {
        $meta = invokeCostTelemetry(
            model: 'claude-haiku-4-5-20251001',
            isXai: false,
            totalInputTokens: 1000,   // regular input (excludes cache for Anthropic)
            totalOutputTokens: 500,
            cacheReadTokens: 2000,
            cacheCreationTokens: 300,
        );

        // input 0.001 + output 0.001 + cache_read 0.0002 + cache_write 0.000375
        expect($meta['cache_hit_tokens'])->toBe(2000)
            ->and($meta['cache_miss_tokens'])->toBe(300)
            ->and($meta['gbp_cost'])->toBe(0.002575)
            ->and($meta['gbp_cost_priced'])->toBeTrue();
    });

    it('subtracts cached reads from prompt tokens for an xAI turn and zeroes miss', function () {
        $meta = invokeCostTelemetry(
            model: 'grok-4.3',
            isXai: true,
            totalInputTokens: 5000,   // xAI prompt_tokens INCLUDES the 2000 cached
            totalOutputTokens: 0,
            cacheReadTokens: 2000,
            cacheCreationTokens: 0,
        );

        // regular input = 5000 - 2000 = 3000 → 0.003 + cache_read 0.0002
        expect($meta['cache_hit_tokens'])->toBe(2000)
            ->and($meta['cache_miss_tokens'])->toBe(0)
            ->and($meta['gbp_cost'])->toBe(0.0032);
    });

    it('never lets xAI regular input go negative', function () {
        $meta = invokeCostTelemetry(
            model: 'grok-4.3',
            isXai: true,
            totalInputTokens: 1000,
            totalOutputTokens: 0,
            cacheReadTokens: 4000, // pathological: more cached than prompt
            cacheCreationTokens: 0,
        );

        // regular input clamped to 0 → only cache_read 4000/1000×0.0001 = 0.0004
        expect($meta['gbp_cost'])->toBe(0.0004);
    });

    it('flags an unpriced model rather than reporting a silent zero', function () {
        $meta = invokeCostTelemetry(
            model: 'mystery-model-9000',
            isXai: false,
            totalInputTokens: 1000,
            totalOutputTokens: 1000,
            cacheReadTokens: 0,
            cacheCreationTokens: 0,
        );

        expect($meta['gbp_cost_priced'])->toBeFalse()
            ->and($meta['gbp_cost'])->toBe(0.009); // default 0.003 + 0.006
    });

    it('emits gbp cost and zeroed cache fields even with no cache activity', function () {
        $meta = invokeCostTelemetry(
            model: 'claude-haiku-4-5-20251001',
            isXai: false,
            totalInputTokens: 1000,
            totalOutputTokens: 0,
            cacheReadTokens: 0,
            cacheCreationTokens: 0,
        );

        expect($meta)->toHaveKeys(['cache_hit_tokens', 'cache_miss_tokens', 'gbp_cost'])
            ->and($meta['cache_hit_tokens'])->toBe(0)
            ->and($meta['cache_miss_tokens'])->toBe(0)
            ->and($meta['gbp_cost'])->toBe(0.001);
    });
});
