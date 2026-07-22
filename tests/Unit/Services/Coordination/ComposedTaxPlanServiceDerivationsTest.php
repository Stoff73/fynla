<?php

declare(strict_types=1);

use App\Services\AI\Pointers\FetchResult;
use App\Services\Coordination\ComposedTaxPlanService;

/**
 * The shared home for strategy-id + digest derivation (§6e harmonisation).
 * RecommendationHandler::fetch (skill path) and
 * CoordinatingAgent::handleRecommendations (tool path) both consume these
 * statics — the cross-path live pin is RecommendationHandlerParityTest.
 */
it('extractStrategyIds returns surfaced item types and locked strategy types', function (): void {
    $plan = [
        'items' => [
            ['type' => 'isa_topup_vs_psa', 'title' => 'Wrap excess cash'],
            ['no_type_key' => true],
            ['type' => ''],
            ['type' => 'bed_and_isa'],
        ],
        'combined_annual_saving' => 0.0,
        'locked' => [
            ['strategy_type' => 'salary_sacrifice_ni', 'missing' => ['pension']],
            ['strategy_type' => 'lifetime_isa', 'missing' => ['date_of_birth']],
        ],
    ];

    expect(ComposedTaxPlanService::extractStrategyIds($plan))->toBe([
        'surfaced' => ['isa_topup_vs_psa', 'bed_and_isa'],
        'locked' => ['salary_sacrifice_ni', 'lifetime_isa'],
    ]);
});

it('extractStrategyIds handles an empty plan', function (): void {
    expect(ComposedTaxPlanService::extractStrategyIds([
        'items' => [], 'combined_annual_saving' => 0.0, 'locked' => [],
    ]))->toBe(['surfaced' => [], 'locked' => []]);
});

it('planDigest equals the digest FetchResult::make derives from the handler payload encoding', function (): void {
    $plan = [
        'items' => [['type' => 'isa_topup_vs_psa', 'estimated_annual_saving' => 312.5]],
        'combined_annual_saving' => 312.5,
        'locked' => [['strategy_type' => 'salary_sacrifice_ni', 'missing' => ['pension']]],
    ];

    // The exact value encoding RecommendationHandler::fetch passes to
    // FetchResult::make — planDigest must never drift from it.
    $value = (string) json_encode(['composed_tax_plan' => $plan], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    expect(ComposedTaxPlanService::planDigest($plan))
        ->toBe(FetchResult::make($value, 'recommendation engine', '2026-06-12')->digest)
        ->and(strlen(ComposedTaxPlanService::planDigest($plan)))->toBe(16);
});
