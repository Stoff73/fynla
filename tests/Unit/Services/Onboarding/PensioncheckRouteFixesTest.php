<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine as SM;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * RED-FIRST tests for the final-review fix wave:
 *   C1 — nextFromCampaignDob ignores funnel['pensions'] for pensioncheck
 *   I4 — nextFromSpouseWork ignores savetax household states for pensioncheck
 *   I5 — nextFromPensionPots advances on "don't know" instead of looping
 */

// ── Helpers ───────────────────────────────────────────────────────────────────

function routeFixUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
        'marital_status' => 'single',
        'employment_status' => 'full_time',
        'date_of_birth' => '1980-06-15',
    ], $attrs));
}

// ── C1: nextFromCampaignDob — pensioncheck always enters the pensions chain ──

it('C1: nextFromCampaignDob routes pensioncheck to occupational scheme even when funnel assets is empty', function (): void {
    // A pensioncheck user has no funnel_answers['assets'] key at all — the
    // pensioncheck funnel never produces that key. Before the fix,
    // funnelHasAnyAsset would return false → the function would skip straight
    // to the next section instead of entering the pensions chain.
    $user = routeFixUser(['funnel_answers' => ['campaign' => 'pensioncheck', 'employment' => 'full-time']]);

    expect(SM::nextFromCampaignDob('15 June 1980', $user))
        ->toBe(SM::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME);
});

it('C1: nextFromCampaignDob for pensioncheck ignores funnel pensions key (savetax path unchanged)', function (): void {
    // savetax user with pension in assets → still routes to occupational scheme
    $savetaxUser = User::factory()->create([
        'onboarding_fyn_selection' => 'savetax',
        'marital_status' => 'single',
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['pension']],
    ]);

    expect(SM::nextFromCampaignDob('15 June 1980', $savetaxUser))
        ->toBe(SM::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME);
});

it('C1: nextFromCampaignDob for savetax without pension in funnel skips pensions (regression guard)', function (): void {
    // savetax user who did NOT tick pension → skip the pensions chain
    $user = User::factory()->create([
        'onboarding_fyn_selection' => 'savetax',
        'marital_status' => 'single',
        'employment_status' => 'full_time',
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['savings']],
        'monthly_expenditure' => 0,
        'household_calculation_mode' => null,
    ]);

    $next = SM::nextFromCampaignDob('15 June 1980', $user);
    // savetax without pension jumps to the next section, never occupational scheme
    expect($next)->not->toBe(SM::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME);
});

// ── I4: nextFromSpouseWork — pensioncheck routes to spouse pensions, not household ─

it('I4: nextFromSpouseWork routes married pensioncheck dual_earner to campaign2_spouse_pensions', function (): void {
    $user = routeFixUser([
        'marital_status' => 'married',
        'household_calculation_mode' => 'dual_earner',
    ]);

    expect(SM::nextFromSpouseWork('Yes, they work', $user))
        ->toBe(SM::STATE_CAMPAIGN2_SPOUSE_PENSIONS);
});

it('I4: nextFromSpouseWork routes married pensioncheck single_earner_couple to campaign2_spouse_pensions', function (): void {
    $user = routeFixUser([
        'marital_status' => 'married',
        'household_calculation_mode' => 'single_earner_couple',
    ]);

    expect(SM::nextFromSpouseWork("No, they don't currently work", $user))
        ->toBe(SM::STATE_CAMPAIGN2_SPOUSE_PENSIONS);
});

it('I4: nextFromSpouseWork savetax dual_earner still routes to campaign_spouse_household (regression)', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_selection' => 'savetax',
        'marital_status' => 'married',
        'household_calculation_mode' => 'dual_earner',
    ]);

    expect(SM::nextFromSpouseWork('Yes, they work', $user))
        ->toBe(SM::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD);
});

it('I4: nextFromSpouseWork savetax single_earner still routes to campaign_spouse_non_working_assets (regression)', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_selection' => 'savetax',
        'marital_status' => 'married',
        'household_calculation_mode' => 'single_earner_couple',
    ]);

    expect(SM::nextFromSpouseWork("No, they don't work", $user))
        ->toBe(SM::STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS);
});

// ── I5: nextFromPensionPots — "don't know" answers advance past the loop ──────

it('I5: nextFromPensionPots advances to pension_contribs when user says they don\'t know', function (): void {
    $user = routeFixUser();
    // A DC pension exists with no fund value — would normally loop
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 0]);

    expect(SM::nextFromPensionPots("I don't know", $user))
        ->toBe(SM::STATE_CAMPAIGN_PENSION_CONTRIBS);
});

it('I5: nextFromPensionPots advances on "not sure" answer', function (): void {
    $user = routeFixUser();
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 0]);

    expect(SM::nextFromPensionPots('not sure', $user))
        ->toBe(SM::STATE_CAMPAIGN_PENSION_CONTRIBS);
});

it('I5: nextFromPensionPots still loops on a substantive numeric answer when value missing', function (): void {
    // A numeric answer means the model TRIED to capture; the tool may have
    // failed to write. Loop back so the director re-asks.
    $user = routeFixUser();
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 0]);

    expect(SM::nextFromPensionPots('about £45,000', $user))
        ->toBe(SM::STATE_CAMPAIGN2_PENSION_POTS);
});

it('I5: nextFromPensionPots advances to pension_contribs when all DC pensions have values (unchanged)', function (): void {
    $user = routeFixUser();
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 45000]);

    expect(SM::nextFromPensionPots('£45,000', $user))
        ->toBe(SM::STATE_CAMPAIGN_PENSION_CONTRIBS);
});
