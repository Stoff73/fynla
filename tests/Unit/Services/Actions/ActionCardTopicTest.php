<?php

declare(strict_types=1);

use App\Services\Actions\ActionCardService;

/*
 * Walked on csjones 2026-09-26: "Protection · Warning" above the income
 * protection card — the engine's internal category is not a topic. Walked on
 * csjones 2026-09-29: "Savings · Lifecycle" on every savings card, the same
 * disease (StrategyCategory::Lifecycle).
 */
it('names a real category and drops the engine-internal ones', function () {
    expect(ActionCardService::topicFor('income_band'))->toBe('Income Band')
        ->and(ActionCardService::topicFor('isa_allowance'))->toBe('ISA Allowance')
        ->and(ActionCardService::topicFor('warning'))->toBeNull()
        ->and(ActionCardService::topicFor('lifecycle'))->toBeNull()
        ->and(ActionCardService::topicFor('general'))->toBeNull()
        ->and(ActionCardService::topicFor(null))->toBeNull();
});
