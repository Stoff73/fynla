<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\AI\Fyn\FynSystemPrompt;
use App\Services\Onboarding\AssetCaptureEntityExtractor;
use App\Services\Onboarding\OnboardingStateMachine;

/**
 * ISA onboarding capture fixes (CSJ): a Cash ISA is a savings product and a
 * Stocks & Shares ISA is an investment product; the current-tax-year
 * contribution (not the balance) is the ISA allowance used; and a captured
 * Stocks & Shares ISA surfaces in the investments section even for a user who
 * ticked only "ISA" (not "investments") on the funnel.
 */
it('runs the investments section when the user has a Stocks & Shares ISA but did not tick investments', function () {
    $user = User::factory()->create(['funnel_answers' => ['assets' => ['isa']]]);
    InvestmentAccount::factory()->isa()->create(['user_id' => $user->id]);

    expect(OnboardingStateMachine::skipSectionIfNoInvestments($user))->toBeFalse();
});

it('skips the investments section when the user has neither investments nor a Stocks & Shares ISA', function () {
    $user = User::factory()->create(['funnel_answers' => ['assets' => ['isa']]]);

    expect(OnboardingStateMachine::skipSectionIfNoInvestments($user))->toBeTrue();
});

it('runs the investments section when the funnel ticked investments', function () {
    $user = User::factory()->create(['funnel_answers' => ['assets' => ['investments']]]);

    expect(OnboardingStateMachine::skipSectionIfNoInvestments($user))->toBeFalse();
});

it('routes an explicit Stocks & Shares ISA to investments rather than cash savings', function () {
    $extractor = app(AssetCaptureEntityExtractor::class);

    expect($extractor->extractSavingsAccounts('a Vanguard Stocks & Shares ISA with £40,000'))->toBe([])
        ->and($extractor->extractInvestmentAccounts('a Vanguard Stocks & Shares ISA with £40,000'))
        ->toHaveCount(1)
        ->and($extractor->extractInvestmentAccounts('a Vanguard Stocks & Shares ISA with £40,000')[0]['account_type'])
        ->toBe('stocks_shares_isa');
});

it('does not turn a bare ISA into a Cash ISA', function () {
    $extractor = app(AssetCaptureEntityExtractor::class);

    expect($extractor->extractSavingsAccounts('I have an ISA with £20,000'))->toBe([])
        ->and($extractor->extractInvestmentAccounts('I have an ISA with £20,000'))->toBe([]);
});

it('tells Fyn to route a Cash ISA to savings and a Stocks & Shares ISA to investments, using the tax-year subscription', function () {
    $prompt = FynSystemPrompt::text();

    expect($prompt)->toContain('Cash ISA is a savings product')
        ->and($prompt)->toContain('Stocks & Shares ISA')
        ->and($prompt)->toContain('amount paid IN during the current tax year');
});
