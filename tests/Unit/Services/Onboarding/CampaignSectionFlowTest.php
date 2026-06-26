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

    // spouse — household_calculation_mode is already known (dual_earner), so the
    // spouse-work question skips itself straight to household data.
    expect(SM::nextCampaignSection('income', $u))->toBe(SM::STATE_CAMPAIGN_ISA_HOLDINGS)       // savings
        ->and(SM::nextCampaignSection('savings', $u))->toBe(SM::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS) // investments
        ->and(SM::nextCampaignSection('investments', $u))->toBe(SM::STATE_CAMPAIGN_DOB)        // pensions (DOB first)
        ->and(SM::nextCampaignSection('pensions', $u))->toBe(SM::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD) // spouse (dual-earner → household)
        ->and(SM::nextCampaignSection('spouse', $u))->toBe(SM::STATE_BASE_EXPENDITURE)         // expenditure
        ->and(SM::nextCampaignSection('expenditure', $u))->toBe(SM::STATE_CAMPAIGN_SYNTHESIS); // synthesis then terminal
});

it('skips savings, investments and spouse sections for a single user with no cash/investments', function () {
    $u = campaignUser(['funnel_answers' => ['assets' => ['pension']]]);

    // income → (savings skip, investments skip) → pensions
    expect(SM::nextCampaignSection('income', $u))->toBe(SM::STATE_CAMPAIGN_DOB)
        // pensions → (spouse skip: single) → expenditure
        ->and(SM::nextCampaignSection('pensions', $u))->toBe(SM::STATE_BASE_EXPENDITURE);
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

it('states a 3-5 minute estimate when one asset is selected', function () {
    $u = campaignUser([
        'first_name' => 'Trapper',
        'employment_status' => 'full_time',
        'annual_employment_income' => null,
        'funnel_answers' => ['employment' => 'full-time', 'assets' => ['savings']],
    ]);

    expect(SM::buildWorkPrompt('', $u))->toContain('about 3-5 minutes');
});

it('adds a minute per asset beyond the first to the time estimate', function () {
    // 3 assets selected → base 3-5 + (3 - 1) = 5-7 minutes.
    $u = campaignUser([
        'first_name' => 'Trapper',
        'employment_status' => 'full_time',
        'annual_employment_income' => null,
        'funnel_answers' => ['employment' => 'full-time', 'assets' => ['savings', 'pension', 'isa']],
    ]);

    expect(SM::buildWorkPrompt('', $u))->toContain('about 5-7 minutes');
});

it('falls back to the 3-5 minute estimate when no assets are selected', function () {
    $u = campaignUser([
        'first_name' => 'Trapper',
        'employment_status' => 'full_time',
        'annual_employment_income' => null,
        'funnel_answers' => ['employment' => 'full-time', 'assets' => []],
    ]);

    expect(SM::buildWorkPrompt('', $u))->toContain('about 3-5 minutes');
});

it('drops the recap and asks the plain income question once income is captured', function () {
    $u = campaignUser([
        'employment_status' => 'full_time',
        'annual_employment_income' => 60000,
        'funnel_answers' => ['assets' => ['savings']],
    ]);

    expect(SM::buildWorkPrompt('', $u))->not->toContain('thanks for those answers');
});

it('runs verify+confirm before the section advice, then advice → next section', function () {
    $u = campaignUser(['funnel_answers' => ['assets' => ['savings', 'investments', 'pension']]]);

    // Savings' last capture (bank accounts) → navigate/confirm (no advice yet).
    // After "is this correct? yes" the savings advice fires, then advances to the
    // investments entry. (See CampaignVerifyFlowTest for the full walk.)
    expect(SM::getNextStateId(SM::STATE_CAMPAIGN_BANK_ACCOUNTS, '', $u))->toBe('campaign_verify_navigate')
        ->and(SM::getNextStateId(SM::STATE_CAMPAIGN_ADVICE_SAVINGS, '', $u))->toBe(SM::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS);

    // Pensions' last capture (history) → navigate/confirm.
    expect(SM::getNextStateId(SM::STATE_CAMPAIGN_PENSION_HISTORY, '', $u))->toBe('campaign_verify_navigate');

    // Income end (employment-more "no") → navigate/confirm; income advice → next section.
    expect(SM::nextFromEmploymentMore('No', $u))->toBe('campaign_verify_navigate')
        ->and(SM::getNextStateId(SM::STATE_CAMPAIGN_ADVICE_INCOME, '', $u))->not->toBe('campaign_verify_navigate');
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

    // Spouse advice now fires after the confirm and continues to the next section
    // (nextCampaignSection('spouse')) — never back into the verify flow, never
    // itself. The invariant this test guards is only that no advice state self-edges.
    expect(SM::getNextStateId(SM::STATE_CAMPAIGN_ADVICE_SPOUSE, '', $u))
        ->not->toBe(SM::STATE_CAMPAIGN_ADVICE_SPOUSE)
        ->and(SM::getNextStateId(SM::STATE_CAMPAIGN_ADVICE_SPOUSE, '', $u))
        ->not->toBe('campaign_verify_navigate');
});

it('section order matches the single source-of-truth array', function () {
    expect(SM::CAMPAIGN_SECTION_ORDER)->toBe([
        'income', 'savings', 'investments', 'pensions', 'spouse', 'expenditure',
    ]);
});
