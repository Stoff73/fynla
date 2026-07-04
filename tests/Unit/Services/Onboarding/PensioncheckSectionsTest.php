<?php

declare(strict_types=1);

use App\Models\RetirementProfile;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine as SM;

/**
 * Task C1 — per-campaign section machinery (G3).
 *
 * Two groups:
 *   1. Characterisation: sectionOrderFor/campaignSections/campaignVerifyConfig
 *      with selection='savetax' return byte-identical arrays to today's
 *      constants + methods (savetax regression guard).
 *   2. PensionCheck section walk: correct order, entry states, verify routes.
 *      The C3 states (campaign2_state_pension, campaign2_retirement_goals) are
 *      not yet in inCodeStates(); only the constant strings are asserted here.
 */

// ── 1. Characterisation — savetax byte-identity ───────────────────────────────

it('sectionOrderFor savetax is byte-identical to the original CAMPAIGN_SECTION_ORDER', function (): void {
    expect(SM::sectionOrderFor('savetax'))->toBe([
        'income', 'savings', 'investments', 'pensions', 'spouse', 'expenditure',
    ]);
});

it('sectionOrderFor savetax matches the deprecated CAMPAIGN_SECTION_ORDER alias', function (): void {
    expect(SM::sectionOrderFor('savetax'))->toBe(SM::CAMPAIGN_SECTION_ORDER);
});

it('campaignSections savetax entries are byte-identical to today\'s section map', function (): void {
    $sections = SM::campaignSections('savetax');

    expect(array_keys($sections))->toBe(['income', 'savings', 'investments', 'pensions', 'spouse', 'expenditure'])
        ->and($sections['income']['entry'])->toBe(SM::STATE_BASE_EMPLOYMENT)
        ->and($sections['income']['skip'])->toBeNull()
        ->and($sections['savings']['entry'])->toBe(SM::STATE_CAMPAIGN_ISA_HOLDINGS)
        ->and($sections['investments']['entry'])->toBe(SM::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS)
        ->and($sections['pensions']['entry'])->toBe(SM::STATE_CAMPAIGN_DOB)
        ->and($sections['pensions']['skip'])->toBeNull()
        ->and($sections['spouse']['entry'])->toBe(SM::STATE_CAMPAIGN_SPOUSE_WORK)
        ->and($sections['expenditure']['entry'])->toBe(SM::STATE_BASE_EXPENDITURE)
        ->and($sections['expenditure']['skip'])->toBeNull();
});

it('campaignSections() with no argument returns the savetax map (default backward-compat)', function (): void {
    expect(SM::campaignSections())->toBe(SM::campaignSections('savetax'));
});

it('campaignVerifyConfig savetax routes are byte-identical to today\'s verify config', function (): void {
    $config = SM::campaignVerifyConfig('savetax');

    expect($config['income']['route'])->toBe('/income')
        ->and($config['income']['entry'])->toBe(SM::STATE_BASE_EMPLOYMENT)
        ->and($config['savings']['route'])->toBe('/savings')
        ->and($config['savings']['entry'])->toBe(SM::STATE_CAMPAIGN_ISA_HOLDINGS)
        ->and($config['investments']['route'])->toBe('/investment')
        ->and($config['investments']['entry'])->toBe(SM::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS)
        ->and($config['pensions']['route'])->toBe('/retirement')
        ->and($config['pensions']['entry'])->toBe(SM::STATE_CAMPAIGN_DOB)
        ->and($config['spouse']['route'])->toBe('/income')
        ->and($config['spouse']['entry'])->toBe(SM::STATE_CAMPAIGN_SPOUSE_WORK)
        ->and($config['expenditure']['route'])->toBe('/expenditure')
        ->and($config['expenditure']['entry'])->toBe(SM::STATE_BASE_EXPENDITURE);
});

it('campaignVerifyConfig() with no argument returns the savetax config (default backward-compat)', function (): void {
    expect(SM::campaignVerifyConfig())->toBe(SM::campaignVerifyConfig('savetax'));
});

it('all savetax section entry states exist in the transition table', function (): void {
    $states = SM::states();
    $sections = SM::campaignSections('savetax');

    foreach ($sections as $sectionId => $section) {
        $entryState = $section['entry'];
        expect(array_key_exists($entryState, $states))
            ->toBeTrue("savetax section '{$sectionId}' entry state '{$entryState}' is missing from states()");
    }
});

// ── 2. PensionCheck section walk ──────────────────────────────────────────────

it('sectionOrderFor pensioncheck returns the pensioncheck walk', function (): void {
    expect(SM::sectionOrderFor('pensioncheck'))->toBe([
        'income', 'pensions', 'state_pension', 'retirement_goals', 'spouse', 'expenditure',
    ]);
});

it('campaignSections pensioncheck has the expected section keys in order', function (): void {
    $sections = SM::campaignSections('pensioncheck');

    expect(array_keys($sections))->toBe([
        'income', 'pensions', 'state_pension', 'retirement_goals', 'spouse', 'expenditure',
    ]);
});

it('campaignSections pensioncheck entry states are correct', function (): void {
    $sections = SM::campaignSections('pensioncheck');

    expect($sections['income']['entry'])->toBe(SM::STATE_BASE_EMPLOYMENT)
        ->and($sections['pensions']['entry'])->toBe(SM::STATE_CAMPAIGN_DOB)
        // state_pension and retirement_goals entries are defined in Task C3;
        // the constants are available now:
        ->and($sections['state_pension']['entry'])->toBe(SM::STATE_CAMPAIGN2_STATE_PENSION)
        ->and($sections['retirement_goals']['entry'])->toBe(SM::STATE_CAMPAIGN2_RETIREMENT_GOALS)
        ->and($sections['spouse']['entry'])->toBe(SM::STATE_CAMPAIGN_SPOUSE_WORK)
        ->and($sections['expenditure']['entry'])->toBe(SM::STATE_BASE_EXPENDITURE);
});

it('campaignVerifyConfig pensioncheck routes to the correct screens', function (): void {
    $config = SM::campaignVerifyConfig('pensioncheck');

    expect($config['income']['route'])->toBe('/income')
        ->and($config['income']['entry'])->toBe(SM::STATE_BASE_EMPLOYMENT)
        ->and($config['pensions']['route'])->toBe('/retirement')
        ->and($config['pensions']['entry'])->toBe(SM::STATE_CAMPAIGN_DOB)
        ->and($config['state_pension']['route'])->toBe('/retirement')
        ->and($config['state_pension']['entry'])->toBe(SM::STATE_CAMPAIGN2_STATE_PENSION)
        ->and($config['retirement_goals']['route'])->toBe('/retirement')
        ->and($config['retirement_goals']['entry'])->toBe(SM::STATE_CAMPAIGN2_RETIREMENT_GOALS)
        ->and($config['spouse']['route'])->toBe('/income')
        ->and($config['spouse']['entry'])->toBe(SM::STATE_CAMPAIGN_SPOUSE_WORK)
        ->and($config['expenditure']['route'])->toBe('/expenditure')
        ->and($config['expenditure']['entry'])->toBe(SM::STATE_BASE_EXPENDITURE);
});

it('pensioncheck C3 state constants resolve to non-empty strings (state-table entries added in Task C3)', function (): void {
    // These constants are defined in C1 so the section arrays can reference them.
    // inCodeStates() entries (turn types, prompts) land in C3 — do NOT assert
    // states() membership here; only verify the string values are present.
    expect(SM::STATE_CAMPAIGN2_STATE_PENSION)
        ->toBeString()
        ->not->toBeEmpty()
        ->and(SM::STATE_CAMPAIGN2_RETIREMENT_GOALS)
        ->toBeString()
        ->not->toBeEmpty();
});

it('sectionOrderFor falls back to savetax for an unrecognised selection', function (): void {
    expect(SM::sectionOrderFor('unknown_campaign'))->toBe(SM::sectionOrderFor('savetax'));
});

// ── 3. Task C2 — data-presence skip predicates ────────────────────────────────
//
// Each test creates a user with or without the relevant data and calls the
// predicate directly to verify the skip decision.  The composite "existing
// user" test calls campaignSections() to assert the wiring is live.

// ─ skipSectionIfIncomeKnown ──────────────────────────────────────────────────
//
// Critical fix (C2 review): FunnelAnswersMapper sets employment_status at
// registration for every funnel user, so keying on employment_status would
// skip the income section for every NEW pensioncheck registrant — before their
// income is captured.  The predicate must key on actual captured income.

it('skipSectionIfIncomeKnown returns true when annual_employment_income is captured', function (): void {
    $user = User::factory()->create([
        'employment_status' => 'employed',
        'annual_employment_income' => 45000,
    ]);
    expect(SM::skipSectionIfIncomeKnown($user))->toBeTrue();
});

it('skipSectionIfIncomeKnown returns true when annual_self_employment_income is captured', function (): void {
    $user = User::factory()->create([
        'employment_status' => 'self_employed',
        'annual_self_employment_income' => 62000,
    ]);
    expect(SM::skipSectionIfIncomeKnown($user))->toBeTrue();
});

// Decisive fixture: FunnelAnswersMapper sets employment_status at registration,
// so a fresh pensioncheck registrant has employment_status set but no income yet.
// The income section must NOT be skipped for this user.
it('skipSectionIfIncomeKnown returns false when employment_status is set but income is null (fresh funnel registrant)', function (): void {
    $user = User::factory()->create([
        'employment_status' => 'employed',
        'annual_employment_income' => null,
        'annual_self_employment_income' => null,
    ]);
    expect(SM::skipSectionIfIncomeKnown($user))->toBeFalse();
});

it('skipSectionIfIncomeKnown returns false when no income fields are populated', function (): void {
    $user = User::factory()->create([
        'employment_status' => null,
        'annual_employment_income' => null,
        'annual_self_employment_income' => null,
    ]);
    expect(SM::skipSectionIfIncomeKnown($user))->toBeFalse();
});

// ─ skipSectionIfStatePensionKnown ────────────────────────────────────────────

it('skipSectionIfStatePensionKnown returns true when a state_pensions row exists', function (): void {
    $user = User::factory()->create();
    StatePension::factory()->create(['user_id' => $user->id]);
    expect(SM::skipSectionIfStatePensionKnown($user))->toBeTrue();
});

it('skipSectionIfStatePensionKnown returns false when no state_pensions row exists', function (): void {
    $user = User::factory()->create();
    expect(SM::skipSectionIfStatePensionKnown($user))->toBeFalse();
});

// ─ skipSectionIfGoalsKnown ───────────────────────────────────────────────────

it('skipSectionIfGoalsKnown returns true when retirement_profiles row has both target fields set', function (): void {
    $user = User::factory()->create();
    RetirementProfile::factory()->create([
        'user_id' => $user->id,
        'target_retirement_age' => 65,
        'target_retirement_income' => 30000,
    ]);
    expect(SM::skipSectionIfGoalsKnown($user))->toBeTrue();
});

it('skipSectionIfGoalsKnown returns false when retirement_profiles row has target_retirement_income null', function (): void {
    $user = User::factory()->create();
    RetirementProfile::factory()->create([
        'user_id' => $user->id,
        'target_retirement_age' => 65,
        'target_retirement_income' => null,
    ]);
    expect(SM::skipSectionIfGoalsKnown($user))->toBeFalse();
});

it('skipSectionIfGoalsKnown returns false when no retirement_profiles row exists', function (): void {
    $user = User::factory()->create();
    expect(SM::skipSectionIfGoalsKnown($user))->toBeFalse();
});

// ─ skipSectionIfExpenditureKnown ─────────────────────────────────────────────

it('skipSectionIfExpenditureKnown returns true when monthly_expenditure is greater than zero', function (): void {
    $user = User::factory()->create(['monthly_expenditure' => 2500]);
    expect(SM::skipSectionIfExpenditureKnown($user))->toBeTrue();
});

it('skipSectionIfExpenditureKnown returns false when monthly_expenditure is null', function (): void {
    $user = User::factory()->create(['monthly_expenditure' => null]);
    expect(SM::skipSectionIfExpenditureKnown($user))->toBeFalse();
});

it('skipSectionIfExpenditureKnown returns false when monthly_expenditure is zero (mirrors skipIfExpenditureSet)', function (): void {
    $user = User::factory()->create(['monthly_expenditure' => 0]);
    expect(SM::skipSectionIfExpenditureKnown($user))->toBeFalse();
});

// ─ Composite — existing SaveTax user fixture ─────────────────────────────────
//
// A realistic returning SaveTax user: income and expenditure are already captured;
// no state_pensions or retirement_profiles row exists (those are new to pensioncheck).
// The walk is asserted by calling nextCampaignSection at each step so the test
// exercises the resolver rather than duplicating its skip logic.

it('existing user with income and expenditure known skips those sections in the pensioncheck walk', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_selection' => 'pensioncheck',
        'employment_status' => 'employed',
        'annual_employment_income' => 45000, // income captured → income section skipped
        'monthly_expenditure' => 2500,       // expenditure captured → expenditure section skipped
        'marital_status' => 'married',
    ]);
    // No StatePension or RetirementProfile rows — those sections must be visited.

    // income is skipped (confirmed by the dedicated unit test; verified here too):
    expect(SM::skipSectionIfIncomeKnown($user))->toBeTrue();

    // Walk the chain via nextCampaignSection; expenditure is skipped so the walk
    // ends at campaign_synthesis after spouse — both campaigns now route through
    // the shared synthesis recap (C3 fix); synthesis then routes to campaign2_terminal
    // (/retirement) for pensioncheck via nextFromCampaignSynthesis.
    expect(SM::nextCampaignSection('income', $user))->toBe(SM::STATE_CAMPAIGN_DOB)
        ->and(SM::nextCampaignSection('pensions', $user))->toBe(SM::STATE_CAMPAIGN2_STATE_PENSION)
        ->and(SM::nextCampaignSection('state_pension', $user))->toBe(SM::STATE_CAMPAIGN2_RETIREMENT_GOALS)
        ->and(SM::nextCampaignSection('retirement_goals', $user))->toBe(SM::STATE_CAMPAIGN_SPOUSE_WORK)
        ->and(SM::nextCampaignSection('spouse', $user))->toBe(SM::STATE_CAMPAIGN_SYNTHESIS);
});
