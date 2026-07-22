<?php

declare(strict_types=1);

use App\Services\AI\Cost\AiCostCalculator;

/**
 * CoALA Phase 5 PR 1 — FR-M13.
 *
 * The calculator turns a normalised per-turn token breakdown into a GBP
 * cost using the config-driven `config/ai_pricing.php` rate table. Rates
 * are £ per 1,000 tokens; cost = tokens × rate / 1000 (PRD FR-M13).
 *
 * It must never silently produce a misleading £0 for an unpriced model —
 * the `priced` flag surfaces the gap so cost data stays trustworthy for
 * the FCA/board reporting the telemetry feeds.
 */
beforeEach(function () {
    config()->set('ai_pricing.models', [
        'test-model' => [
            'input' => 0.001,        // £ per 1k regular input tokens
            'output' => 0.002,       // £ per 1k output tokens
            'cache_write' => 0.00125, // £ per 1k cache-creation tokens
            'cache_read' => 0.0001,  // £ per 1k cache-read (hit) tokens
        ],
        'claude-haiku-4-5-20251001' => [
            'input' => 0.00079,
            'output' => 0.00395,
            'cache_write' => 0.0009875,
            'cache_read' => 0.000079,
        ],
    ]);
    config()->set('ai_pricing.default', [
        'input' => 0.003,
        'output' => 0.006,
        'cache_write' => 0.00375,
        'cache_read' => 0.0003,
    ]);
});

describe('AiCostCalculator (FR-M13)', function () {
    it('computes gbp cost from a full token breakdown', function () {
        $calc = new AiCostCalculator;

        $result = $calc->compute(
            model: 'test-model',
            inputTokens: 1000,
            outputTokens: 1000,
            cacheReadTokens: 1000,
            cacheCreationTokens: 1000,
        );

        // 1000/1000 × (0.001 + 0.002 + 0.0001 + 0.00125) = 0.00435
        expect($result['gbp_cost'])->toBe(0.00435)
            ->and($result['priced'])->toBeTrue()
            ->and($result['model'])->toBe('test-model');
    });

    it('exposes a per-component breakdown', function () {
        $calc = new AiCostCalculator;

        $result = $calc->compute(
            model: 'test-model',
            inputTokens: 2000,
            outputTokens: 500,
            cacheReadTokens: 4000,
            cacheCreationTokens: 0,
        );

        expect($result['breakdown'])->toMatchArray([
            'input' => 0.002,       // 2000/1000 × 0.001
            'output' => 0.001,      // 500/1000 × 0.002
            'cache_read' => 0.0004, // 4000/1000 × 0.0001
            'cache_write' => 0.0,   // 0 tokens
        ]);
    });

    it('returns zero cost for zero tokens but stays priced', function () {
        $calc = new AiCostCalculator;

        $result = $calc->compute('test-model', 0, 0, 0, 0);

        expect($result['gbp_cost'])->toBe(0.0)
            ->and($result['priced'])->toBeTrue();
    });

    it('falls back to default pricing for an unknown model and flags it unpriced', function () {
        $calc = new AiCostCalculator;

        $result = $calc->compute('some-unlisted-model', 1000, 1000, 0, 0);

        // default: 1000/1000 × (0.003 + 0.006) = 0.009
        expect($result['gbp_cost'])->toBe(0.009)
            ->and($result['priced'])->toBeFalse()
            ->and($result['model'])->toBe('some-unlisted-model');
    });

    it('matches a configured model by prefix when the exact key is absent', function () {
        $calc = new AiCostCalculator;

        // Provider may report a versioned/suffixed id; prefix match finds the rate.
        $result = $calc->compute('claude-haiku-4-5-20251001-preview', 1000, 0, 0, 0);

        expect($result['gbp_cost'])->toBe(0.00079)
            ->and($result['priced'])->toBeTrue();
    });

    it('defaults cache token args to zero', function () {
        $calc = new AiCostCalculator;

        $result = $calc->compute('test-model', 1000, 1000);

        // 0.001 + 0.002, no cache components
        expect($result['gbp_cost'])->toBe(0.003);
    });

    it('rounds the gbp cost to 6 decimal places', function () {
        $calc = new AiCostCalculator;

        // 333/1000 × 0.001 = 0.000333
        $result = $calc->compute('test-model', 333, 0, 0, 0);

        expect($result['gbp_cost'])->toBe(0.000333);
    });
});
