<?php

declare(strict_types=1);

use App\Services\Onboarding\OnboardingStateMachine;

it('maps every navigable campaign section to a route and capture-entry state', function (): void {
    $config = OnboardingStateMachine::campaignVerifyConfig();

    // Charity verifies inline (no route); the rest navigate.
    expect($config['income']['route'])->toBe('/income')
        ->and($config['income']['entry'])->toBe(OnboardingStateMachine::STATE_BASE_EMPLOYMENT)
        ->and($config['savings']['route'])->toBe('/savings')
        ->and($config['investments']['route'])->toBe('/investment')
        ->and($config['pensions']['route'])->toBe('/retirement')
        ->and($config['spouse']['route'])->toBe('/income')
        ->and($config['expenditure']['route'])->toBe('/expenditure')
        ->and($config['giving']['route'])->toBeNull();
});
