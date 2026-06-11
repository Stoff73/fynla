<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine as SM;

/**
 * The savetax campaign question sequence is driven by a single ordered list
 * (SM::CAMPAIGN_SECTION_ORDER) walked by nextCampaignSection(). These tests
 * lock the section ordering + funnel-aware skipping so the flow can be
 * reordered safely from that one array.
 */
function campaignUser(array $attrs = []): User
{
    return User::factory()->make(array_merge([
        'onboarding_fyn_path' => 'campaign',
        'marital_status' => 'single',
        'date_of_birth' => null,
        'monthly_expenditure' => 0,
        'household_calculation_mode' => null,
        'funnel_answers' => ['assets' => []],
    ], $attrs));
}

it('walks every section in order for a fully-loaded married dual-earner', function () {
    $u = campaignUser([
        'marital_status' => 'married',
        'household_calculation_mode' => 'dual_earner',
        'funnel_answers' => ['assets' => ['savings', 'investments', 'pension']],
    ]);

    expect(SM::nextCampaignSection('income', $u))->toBe(SM::STATE_CAMPAIGN_ISA_HOLDINGS)       // savings
        ->and(SM::nextCampaignSection('savings', $u))->toBe(SM::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS) // investments
        ->and(SM::nextCampaignSection('investments', $u))->toBe(SM::STATE_CAMPAIGN_DOB)        // pensions (DOB first)
        ->and(SM::nextCampaignSection('pensions', $u))->toBe(SM::STATE_CAMPAIGN_CHARITABLE_GIVING) // giving
        // spouse — household_calculation_mode is already known (dual_earner),
        // so the spouse-work question skips itself straight to household data.
        ->and(SM::nextCampaignSection('giving', $u))->toBe(SM::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD)
        ->and(SM::nextCampaignSection('spouse', $u))->toBe(SM::STATE_BASE_EXPENDITURE)         // expenditure
        ->and(SM::nextCampaignSection('expenditure', $u))->toBe(SM::STATE_CAMPAIGN_SYNTHESIS); // synthesis then terminal
});

it('skips savings, investments and spouse sections for a single user with no cash/investments', function () {
    $u = campaignUser(['funnel_answers' => ['assets' => ['pension']]]);

    // income → (savings skip, investments skip) → pensions
    expect(SM::nextCampaignSection('income', $u))->toBe(SM::STATE_CAMPAIGN_DOB)
        // pensions → giving → (spouse skip: single) → expenditure
        ->and(SM::nextCampaignSection('pensions', $u))->toBe(SM::STATE_CAMPAIGN_CHARITABLE_GIVING)
        ->and(SM::nextCampaignSection('giving', $u))->toBe(SM::STATE_BASE_EXPENDITURE);
});

it('keeps savings when the user holds an ISA (cash-like asset)', function () {
    $u = campaignUser(['funnel_answers' => ['assets' => ['isa']]]);

    expect(SM::nextCampaignSection('income', $u))->toBe(SM::STATE_CAMPAIGN_ISA_HOLDINGS);
});

it('resolves the pensions entry past DOB once a date of birth is known', function () {
    $u = campaignUser([
        'date_of_birth' => '1985-01-12',
        'employment_status' => 'full_time',
        'funnel_answers' => ['assets' => []],
    ]);

    // pensions entry is DOB, but skipIfDobSet → transitively advances to the
    // workplace-pension capture (employed, so it isn't skipped).
    expect(SM::nextCampaignSection('investments', $u))->toBe(SM::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME);
});

it('opens the income-first entry with the funnel recap greeting', function () {
    $u = campaignUser([
        'first_name' => 'Trapper',
        'employment_status' => 'full_time',
        'annual_employment_income' => null,
        'annual_self_employment_income' => null,
        'funnel_answers' => ['employment' => 'full-time', 'income' => '100001_125140', 'assets' => ['savings']],
    ]);

    $prompt = SM::buildWorkPrompt('', $u);
    expect($prompt)->toContain('thanks for those answers')   // greeting
        ->and($prompt)->toContain('income')                  // leads into income
        ->and($prompt)->not->toContain('date of birth');     // DOB deferred
});

it('drops the recap and asks the plain income question once income is captured', function () {
    $u = campaignUser([
        'employment_status' => 'full_time',
        'annual_employment_income' => 60000,
        'funnel_answers' => ['assets' => ['savings']],
    ]);

    expect(SM::buildWorkPrompt('', $u))->not->toContain('thanks for those answers');
});

it('inserts a per-section advice turn between a section and the next', function () {
    $u = campaignUser(['funnel_answers' => ['assets' => ['savings', 'investments', 'pension']]]);

    // Savings' last capture (bank accounts) → savings advice → investments entry.
    expect(SM::getNextStateId(SM::STATE_CAMPAIGN_BANK_ACCOUNTS, '', $u))->toBe(SM::STATE_CAMPAIGN_ADVICE_SAVINGS)
        ->and(SM::getNextStateId(SM::STATE_CAMPAIGN_ADVICE_SAVINGS, '', $u))->toBe(SM::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS);

    // Pensions' last capture (history) → pensions advice → next section.
    expect(SM::getNextStateId(SM::STATE_CAMPAIGN_PENSION_HISTORY, '', $u))->toBe(SM::STATE_CAMPAIGN_ADVICE_PENSIONS);

    // Income end (employment-more "no") → income advice → savings entry.
    expect(SM::nextFromEmploymentMore('No', $u))->toBe(SM::STATE_CAMPAIGN_ADVICE_INCOME)
        ->and(SM::getNextStateId(SM::STATE_CAMPAIGN_ADVICE_INCOME, '', $u))->toBe(SM::STATE_CAMPAIGN_ISA_HOLDINGS);
});

it('marks every advice state as an auto-advancing advice turn with a section', function () {
    foreach ([
        SM::STATE_CAMPAIGN_ADVICE_INCOME, SM::STATE_CAMPAIGN_ADVICE_SAVINGS,
        SM::STATE_CAMPAIGN_ADVICE_INVESTMENTS, SM::STATE_CAMPAIGN_ADVICE_PENSIONS,
        SM::STATE_CAMPAIGN_ADVICE_SPOUSE,
    ] as $id) {
        $state = SM::getState($id);
        expect($state['turn_type'])->toBe('advice')
            ->and($state['advice_section'] ?? null)->not->toBeNull();
    }
});

it('never lets a campaign advice state auto-advance back into itself', function () {
    // Regression: STATE_CAMPAIGN_ADVICE_SPOUSE had next => itself. Advice turns
    // auto-advance with no user input, so the self-edge recursed without bound —
    // 17,509 identical "As a couple…" messages were persisted at ~41/sec before
    // the worker died. Every advice state MUST resolve to a different state.
    $u = campaignUser([
        'marital_status' => 'married',
        'household_calculation_mode' => 'dual_earner',
        'funnel_answers' => ['assets' => ['savings', 'investments', 'pension']],
    ]);

    foreach ([
        SM::STATE_CAMPAIGN_ADVICE_INCOME, SM::STATE_CAMPAIGN_ADVICE_SAVINGS,
        SM::STATE_CAMPAIGN_ADVICE_INVESTMENTS, SM::STATE_CAMPAIGN_ADVICE_PENSIONS,
        SM::STATE_CAMPAIGN_ADVICE_SPOUSE,
    ] as $id) {
        expect(SM::getNextStateId($id, '', $u))->not->toBe($id);
    }

    // Spouse advice continues to the expenditure section,
    // matching the nextCampaignSection('spouse') contract above.
    expect(SM::getNextStateId(SM::STATE_CAMPAIGN_ADVICE_SPOUSE, '', $u))
        ->toBe(SM::STATE_BASE_EXPENDITURE);
});

it('section order matches the single source-of-truth array', function () {
    expect(SM::CAMPAIGN_SECTION_ORDER)->toBe([
        'income', 'savings', 'investments', 'pensions', 'giving', 'spouse', 'expenditure',
    ]);
});
