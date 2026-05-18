<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\ContextBucket;
use App\Services\AI\Fyn\FynContextSelector;
use App\Services\AI\Fyn\FynTurnContext;

function ctx(string $mode, ?array $classification, ?string $focus = null): FynTurnContext
{
    return FynTurnContext::make(
        user: User::factory()->make(),
        message: 'x',
        currentRoute: '/dashboard',
        mode: $mode,
        onboardingFocus: $focus,
        isPreview: false,
        classification: $classification,
    );
}

it('onboarding gets exactly IDENTITY + CAPTURE', function (): void {
    $b = (new FynContextSelector)->buckets(ctx('onboarding', null, 'savings'));
    expect($b)->toEqualCanonicalizing([ContextBucket::IDENTITY, ContextBucket::CAPTURE]);
});

it('advice factual gets IDENTITY only', function (): void {
    $b = (new FynContextSelector)->buckets(ctx('advice', ['primary' => 'billing']));
    expect($b)->toEqual([ContextBucket::IDENTITY]);
});

it('advice non-factual gets IDENTITY + POSITION + READINESS', function (): void {
    $b = (new FynContextSelector)->buckets(ctx('advice', ['primary' => 'retirement_contribution']));
    expect($b)->toEqualCanonicalizing([
        ContextBucket::IDENTITY, ContextBucket::POSITION, ContextBucket::READINESS,
    ]);
});

it('advice with null classification is treated as non-factual', function (): void {
    $b = (new FynContextSelector)->buckets(ctx('advice', null));
    expect($b)->toContain(ContextBucket::POSITION);
});
