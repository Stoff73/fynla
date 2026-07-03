<?php

declare(strict_types=1);

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
