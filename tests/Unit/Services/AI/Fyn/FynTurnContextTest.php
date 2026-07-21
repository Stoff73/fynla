<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynTurnContext;

it('builds an advice-mode context', function (): void {
    $user = User::factory()->make(['first_name' => 'Chris']);
    $ctx = FynTurnContext::make(
        user: $user,
        message: 'How is my pension doing?',
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'RETIREMENT'],
    );

    expect($ctx->mode)->toBe('advice')
        ->and($ctx->onboardingFocus)->toBeNull()
        ->and($ctx->isOnboarding())->toBeFalse()
        ->and($ctx->classification['primary'])->toBe('RETIREMENT');
});

it('builds an onboarding-mode context', function (): void {
    $user = User::factory()->make(['first_name' => 'Chris']);
    $ctx = FynTurnContext::make(
        user: $user,
        message: 'Halifax ISA £10k',
        currentRoute: null,
        mode: 'onboarding',
        onboardingFocus: 'savings',
        isPreview: false,
        classification: null,
    );

    expect($ctx->isOnboarding())->toBeTrue()
        ->and($ctx->onboardingFocus)->toBe('savings');
});

it('rejects an invalid mode', function (): void {
    $user = User::factory()->make();
    FynTurnContext::make(
        user: $user, message: 'x', currentRoute: null,
        mode: 'banana', onboardingFocus: null, isPreview: false, classification: null,
    );
})->throws(InvalidArgumentException::class);
