<?php

declare(strict_types=1);

use App\Services\Estate\LifePolicyStrategyService;

/*
 * CSJ 2026-10-06: "we do not make up monthly premiums". The whole of life page
 * states the cover the Inheritance Tax liability calls for and leaves the price
 * to insurers' quotes; nothing here may carry a premium figure (Rule 23).
 */
it('states the cover and never prices it', function (): void {
    $strategy = app(LifePolicyStrategyService::class)->calculateStrategy(400000.0, 25, 60, 'female', 62, 'male');
    $flat = json_encode($strategy);

    expect($strategy['whole_of_life_policy']['cover_amount'])->toBe(400000.0)
        ->and($strategy['whole_of_life_policy']['policy_type'])->toBe('Joint Life Second Death')
        ->and($flat)->not->toMatch('/premium"|premiums_paid|cost_benefit|self_insurance|monthly_investment/');
});
