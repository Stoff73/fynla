<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine as SM;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Create an AiConversation for a given user (needed by income-carry tests
 * that require a real conversation ID to drive onboarding_parked_facts).
 */
function pensioncheckConversation(User $user): AiConversation
{
    return AiConversation::factory()->create(['user_id' => $user->id]);
}

function pensioncheckUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
        'marital_status' => 'single',
        'employment_status' => 'full_time',
        'date_of_birth' => '1980-06-15',
    ], $attrs));
}

// ── 1. State declarations ───────────────────────────────────────────────���─────

it('all eight campaign2 states are present in the transition table', function (): void {
    $states = SM::states();

    foreach ([
        SM::STATE_CAMPAIGN2_EXISTING_RECAP,
        SM::STATE_CAMPAIGN2_PENSION_POTS,
        SM::STATE_CAMPAIGN2_PENSION_DB,
        SM::STATE_CAMPAIGN2_FLEXIBLE_ACCESS,
        SM::STATE_CAMPAIGN2_STATE_PENSION,
        SM::STATE_CAMPAIGN2_RETIREMENT_GOALS,
        SM::STATE_CAMPAIGN2_SPOUSE_PENSIONS,
        SM::STATE_CAMPAIGN2_TERMINAL,
    ] as $stateId) {
        expect(array_key_exists($stateId, $states))
            ->toBeTrue("missing state: {$stateId}");
    }
});

it('campaign2 states have correct turn types', function (): void {
    $expected = [
        SM::STATE_CAMPAIGN2_EXISTING_RECAP => 'bubbles',
        SM::STATE_CAMPAIGN2_PENSION_POTS => 'delegated',
        SM::STATE_CAMPAIGN2_PENSION_DB => 'delegated',
        SM::STATE_CAMPAIGN2_FLEXIBLE_ACCESS => 'delegated',
        SM::STATE_CAMPAIGN2_STATE_PENSION => 'grouped_extract',
        SM::STATE_CAMPAIGN2_RETIREMENT_GOALS => 'grouped_extract',
        SM::STATE_CAMPAIGN2_SPOUSE_PENSIONS => 'delegated',
        SM::STATE_CAMPAIGN2_TERMINAL => 'terminal',
    ];

    $states = SM::states();
    foreach ($expected as $id => $expectedType) {
        expect($states[$id]['turn_type'] ?? null)->toBe($expectedType, "wrong turn_type for {$id}");
    }
});

it('campaign2_terminal has navigate_to /retirement matching the brief', function (): void {
    $state = SM::getState(SM::STATE_CAMPAIGN2_TERMINAL);
    expect($state['navigate_to'])->toBe('/retirement');
});

it('campaign2_existing_recap has the correct bubble labels', function (): void {
    $state = SM::getState(SM::STATE_CAMPAIGN2_EXISTING_RECAP);
    $ids = array_column($state['bubbles'], 'id');
    expect($ids)->toContain('yes')->toContain('changed');
});

// ── 2. Transitions ────────────────────────────────────────────────────────────

// campaign_occupational_scheme.next: savetax → pension_contribs; pensioncheck → pension_pots

it('nextFromCampaignOccupationalScheme routes savetax to campaign_pension_contribs', function (): void {
    $user = User::factory()->create(['onboarding_fyn_selection' => 'savetax']);
    expect(SM::nextFromCampaignOccupationalScheme('3% matched', $user))
        ->toBe(SM::STATE_CAMPAIGN_PENSION_CONTRIBS);
});

it('nextFromCampaignOccupationalScheme routes pensioncheck to campaign2_pension_pots', function (): void {
    $user = pensioncheckUser();
    expect(SM::nextFromCampaignOccupationalScheme('3% matched', $user))
        ->toBe(SM::STATE_CAMPAIGN2_PENSION_POTS);
});

// campaign_pension_contribs.next: savetax → verify; pensioncheck → pension_db

it('nextFromCampaignPensionContribs routes pensioncheck to campaign2_pension_db', function (): void {
    $user = pensioncheckUser();
    // Verify transition helper returns the correct next state id.
    expect(SM::nextFromCampaignPensionContribs('no extra contributions', $user))
        ->toBe(SM::STATE_CAMPAIGN2_PENSION_DB);
});

it('nextFromCampaignPensionContribs routes savetax to the verify sub-flow (not pension_db)', function (): void {
    $user = User::factory()->create(['onboarding_fyn_selection' => 'savetax']);
    $next = SM::nextFromCampaignPensionContribs('no extra contributions', $user);
    // savetax pensions section ends at the verify announce (not pension_db).
    expect($next)->toBe('campaign_verify_announce')
        ->and($next)->not->toBe(SM::STATE_CAMPAIGN2_PENSION_DB);
});

// campaign2_pension_db.next = campaign2_flexible_access (static)

it('campaign2_pension_db advances to campaign2_flexible_access', function (): void {
    $user = pensioncheckUser(['date_of_birth' => '1960-01-01']); // age 65 → flexible access shown
    $next = SM::getNextStateId(SM::STATE_CAMPAIGN2_PENSION_DB, 'no DB pension', $user);
    expect($next)->toBe(SM::STATE_CAMPAIGN2_FLEXIBLE_ACCESS);
});

// campaign2_flexible_access skip: age < 55

it('flexible access state is skipped when user is under 55', function (): void {
    $user = pensioncheckUser(['date_of_birth' => '2000-01-01']); // age 25
    expect(SM::skipIfFlexibleAccessNotApplicable($user))->toBeTrue();
});

it('flexible access state is not skipped when user is 55 or over with no access flagged', function (): void {
    $user = pensioncheckUser(['date_of_birth' => '1960-01-01']); // age 65
    expect(SM::skipIfFlexibleAccessNotApplicable($user))->toBeFalse();
});

it('flexible access state is skipped when has_flexibly_accessed is already flagged', function (): void {
    $user = pensioncheckUser(['date_of_birth' => '1960-01-01']); // age 65
    DCPension::factory()->create([
        'user_id' => $user->id,
        'has_flexibly_accessed' => true,
    ]);
    expect(SM::skipIfFlexibleAccessNotApplicable($user))->toBeTrue();
});

// campaign2_existing_recap routing

it('nextFromExistingRecap routes "changed" to campaign_verify_edit', function (): void {
    $user = pensioncheckUser();
    expect(SM::nextFromExistingRecap("Something's changed", $user))
        ->toBe('campaign_verify_edit');
});

it('nextFromExistingRecap routes "yes" to first non-skipped pensioncheck section', function (): void {
    $user = pensioncheckUser([
        'annual_employment_income' => null, // income section not skipped
        'marital_status' => 'single',
    ]);
    $next = SM::nextFromExistingRecap("Yes, that's right", $user);
    // First section is income (entry: base_employment). Employment status is
    // full_time so skipIfEmploymentSet returns true → transitively advances
    // past base_employment to base_work or beyond. Verify it's not 'done'/synthesis.
    expect($next)->not->toBe(SM::STATE_CAMPAIGN_SYNTHESIS)
        ->and($next)->not->toBe(SM::STATE_CAMPAIGN2_TERMINAL);
});

// ── 3. Occupational-scheme extended skip for pensioncheck ─────��───────────────

it('skipIfOccupationalScheme returns true when not employed (base condition — savetax path unchanged)', function (): void {
    foreach (['self_employed', 'retired', 'unemployed'] as $status) {
        $user = User::factory()->create([
            'employment_status' => $status,
            'onboarding_fyn_selection' => 'savetax',
        ]);
        expect(SM::skipIfOccupationalScheme($user))
            ->toBeTrue("should skip for employment status '{$status}'");
    }
});

it('skipIfOccupationalScheme returns false for employed savetax user even with workplace pension', function (): void {
    $user = User::factory()->create([
        'employment_status' => 'full_time',
        'onboarding_fyn_selection' => 'savetax',
    ]);
    DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_type' => 'workplace',
        'current_fund_value' => 45000,
    ]);
    // savetax never skips based on existing pot — only employment status gates it.
    expect(SM::skipIfOccupationalScheme($user))->toBeFalse();
});

it('skipIfOccupationalScheme returns true for pensioncheck user with funded workplace DC pension', function (): void {
    $user = pensioncheckUser(['employment_status' => 'full_time']);
    DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_type' => 'workplace',
        'current_fund_value' => 45000,
    ]);
    expect(SM::skipIfOccupationalScheme($user))->toBeTrue();
});

it('skipIfOccupationalScheme returns false for pensioncheck user with workplace DC pension but no fund value', function (): void {
    $user = pensioncheckUser(['employment_status' => 'full_time']);
    // current_fund_value is NOT NULL DEFAULT 0 — 0 represents "not yet entered".
    DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_type' => 'workplace',
        'current_fund_value' => 0,
    ]);
    // No value yet — occupational scheme question still needed.
    expect(SM::skipIfOccupationalScheme($user))->toBeFalse();
});

// ── 4. Pension-pots loop ──────────────────────────────────────────────────────

it('nextFromPensionPots loops back to itself when a DC pension lacks a fund value', function (): void {
    $user = pensioncheckUser();
    // current_fund_value is NOT NULL DEFAULT 0 — 0 represents "not yet entered".
    DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 0,
    ]);
    expect(SM::nextFromPensionPots('£45,000', $user))
        ->toBe(SM::STATE_CAMPAIGN2_PENSION_POTS);
});

it('nextFromPensionPots advances to campaign_pension_contribs when all DC pensions have values', function (): void {
    $user = pensioncheckUser();
    DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 45000,
    ]);
    expect(SM::nextFromPensionPots('done', $user))
        ->toBe(SM::STATE_CAMPAIGN_PENSION_CONTRIBS);
});

it('buildPensionPotsPrompt names the scheme missing a fund value', function (): void {
    $user = pensioncheckUser();
    // 0 represents "no fund value entered yet" (current_fund_value is NOT NULL DEFAULT 0).
    DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => 'Aviva Workplace Pension',
        'current_fund_value' => 0,
    ]);
    $prompt = SM::buildPensionPotsPrompt('', $user);
    expect($prompt)->toContain('Aviva Workplace Pension');
});

// ── 4b. Pension-pots entry skip (D1 Fix 2) ────────────────────────────────────
// campaign2_pension_pots had no skip_if, so with zero DC pensions (e.g. the
// occupational_scheme capture failed) it ran anyway, hit buildPensionPotsPrompt's
// null fallback ("I've noted the current values…") and the model ad-libbed. The
// state must skip when no DC pension is missing a value.

it('skipIfNoPensionPotToFill returns true when the user has no DC pensions', function (): void {
    $user = pensioncheckUser();
    expect(SM::skipIfNoPensionPotToFill($user))->toBeTrue();
});

it('skipIfNoPensionPotToFill returns false when a DC pension is missing its value', function (): void {
    $user = pensioncheckUser();
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 0]);
    expect(SM::skipIfNoPensionPotToFill($user))->toBeFalse();
});

it('skipIfNoPensionPotToFill returns true when every DC pension already has a value', function (): void {
    $user = pensioncheckUser();
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 45000]);
    expect(SM::skipIfNoPensionPotToFill($user))->toBeTrue();
});

it('campaign2_pension_pots is wired with the skip_if predicate', function (): void {
    $state = SM::getState(SM::STATE_CAMPAIGN2_PENSION_POTS);
    expect($state['skip_if'] ?? null)->not->toBeNull();
});

it('applySkipRules skips campaign2_pension_pots to pension_contribs when nothing is missing', function (): void {
    $user = pensioncheckUser();
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 45000]);
    expect(SM::applySkipRules(SM::STATE_CAMPAIGN2_PENSION_POTS, $user))
        ->toBe(SM::STATE_CAMPAIGN_PENSION_CONTRIBS);
});

it('applySkipRules keeps campaign2_pension_pots when a pot value is missing', function (): void {
    $user = pensioncheckUser();
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 0]);
    expect(SM::applySkipRules(SM::STATE_CAMPAIGN2_PENSION_POTS, $user))
        ->toBe(SM::STATE_CAMPAIGN2_PENSION_POTS);
});

// ── 4c. Pension-pots update targeting (D1 Fix 2b) ─────────────────────────────
// The pot-value turn updates an EXISTING DC pension by id, but the onboarding
// CAPTURE context only surfaces record counts (dc_pensions_count), not ids —
// so update_record had no entity_id to target. The state declares
// record_context = 'pensions', and handleAssetCaptureTurn appends a reference
// block listing the section's records with their ids (verify-edit pattern) to
// the system-prompt override.

it('campaign2_pension_pots declares the pensions record_context', function (): void {
    $state = SM::getState(SM::STATE_CAMPAIGN2_PENSION_POTS);
    expect($state['record_context'] ?? null)->toBe('pensions');
});

it('capture record-context appendix lists the DC pension with its entity_id', function (): void {
    $user = pensioncheckUser();
    $pension = DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => 'Aviva Workplace Pension',
        'current_fund_value' => 0,
    ]);

    $director = app(OnboardingChatDirector::class);
    $state = SM::getState(SM::STATE_CAMPAIGN2_PENSION_POTS);
    $appendix = (new ReflectionMethod($director, 'captureRecordContextAppendix'))
        ->invoke($director, $user, $state);

    expect($appendix)->toContain('entity_id: '.$pension->id)
        ->and($appendix)->toContain('dc_pension')
        ->and($appendix)->toContain('Aviva Workplace Pension')
        ->and($appendix)->toContain('update_record');
});

it('capture record-context appendix is empty for a state without record_context', function (): void {
    $user = pensioncheckUser();
    $director = app(OnboardingChatDirector::class);
    $state = SM::getState(SM::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME);
    $appendix = (new ReflectionMethod($director, 'captureRecordContextAppendix'))
        ->invoke($director, $user, $state);

    expect($appendix)->toBe('');
});

// ── 4d. Campaign-aware terminal CTA (D1 Fix 4) ────────────────────────────────
// emitTerminalNavigationTurn hardcoded the bubble id 'view_strategy' + label
// "Take me to my tax strategy" for every campaign, so the pensioncheck terminal
// (navigate_to /retirement) showed the savetax text. The bubble id/label must
// follow the terminal state's navigate_to route.

it('terminal navigation bubble follows the pensioncheck retirement route', function (): void {
    $director = app(OnboardingChatDirector::class);
    $bubble = (new ReflectionMethod($director, 'terminalNavigationBubble'))
        ->invoke($director, '/retirement');

    expect($bubble['route'])->toBe('/retirement')
        ->and($bubble['label'])->toContain('retirement')
        ->and($bubble['label'])->not->toContain('tax strategy')
        ->and($bubble['id'])->not->toBe('view_strategy');
});

it('terminal navigation bubble keeps the savetax tax-strategy label', function (): void {
    $director = app(OnboardingChatDirector::class);
    $bubble = (new ReflectionMethod($director, 'terminalNavigationBubble'))
        ->invoke($director, '/tax-strategy');

    expect($bubble['route'])->toBe('/tax-strategy')
        ->and($bubble['label'])->toBe('Take me to my tax strategy')
        ->and($bubble['id'])->toBe('view_strategy');
});

// ── 5. Campaign-section terminal routing ──────────────────────────────────────

it('nextCampaignSection for pensioncheck routes to campaign_synthesis when all sections exhausted', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_selection' => 'pensioncheck',
        'marital_status' => 'single',
        'monthly_expenditure' => 2500, // expenditure section skipped
    ]);
    // After expenditure (last section) → campaign_synthesis for pensioncheck (C3 fix:
    // pensioncheck now routes through the shared synthesis recap before its terminal).
    expect(SM::nextCampaignSection('expenditure', $user))
        ->toBe(SM::STATE_CAMPAIGN_SYNTHESIS);
});

it('nextCampaignSection for savetax still routes to campaign_synthesis when all sections exhausted', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_selection' => 'savetax',
        'marital_status' => 'single',
        'monthly_expenditure' => 2500,
    ]);
    expect(SM::nextCampaignSection('expenditure', $user))
        ->toBe(SM::STATE_CAMPAIGN_SYNTHESIS);
});

// ── 6. nextFromExpenditureReview routes pensioncheck to recap gate ────────────

it('nextFromExpenditureReview routes pensioncheck campaign users to campaign2_existing_recap', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
    ]);
    expect(SM::nextFromExpenditureReview('looks correct', $user))
        ->toBe(SM::STATE_CAMPAIGN2_EXISTING_RECAP);
});

it('nextFromExpenditureReview still routes savetax campaign users to campaign_intro (regression)', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'savetax',
    ]);
    expect(SM::nextFromExpenditureReview('looks correct', $user))
        ->toBe(SM::STATE_CAMPAIGN_INTRO);
});

// ── 7. Spouse pensions skip ───────────────────────────────────────────────────

it('campaign2_spouse_pensions is skipped for a single pensioncheck user', function (): void {
    $user = pensioncheckUser(['marital_status' => 'single']);
    $state = SM::getState(SM::STATE_CAMPAIGN2_SPOUSE_PENSIONS);
    $skipFn = $state['skip_if'];
    expect($skipFn($user))->toBeTrue();
});

it('campaign2_spouse_pensions is not skipped for a married pensioncheck user', function (): void {
    $user = pensioncheckUser(['marital_status' => 'married']);
    $state = SM::getState(SM::STATE_CAMPAIGN2_SPOUSE_PENSIONS);
    $skipFn = $state['skip_if'];
    expect($skipFn($user))->toBeFalse();
});

// ── 8. Two-turn retirement-goals sequence ─────────────────────────────────────
//
// The capture_retirement_goals handler returns details.missing=['target_retirement_age']
// when only income is provided. The director emits a partial retry and stays on
// campaign2_retirement_goals. On the second turn the LLM supplies both fields via
// the transcript context. This test pins the partial-retry protocol: income-only
// returns the missing signal; age+income creates the profile.

it('capture_retirement_goals partial-retry protocol: income-only returns missing target_retirement_age', function (): void {
    $user = pensioncheckUser(['date_of_birth' => '1975-01-01']);

    // Simulate the handler receiving income only (no age).
    $coordinator = app(CoordinatingAgent::class);
    $method = new ReflectionMethod($coordinator, 'handleCaptureRetirementGoals');
    $method->setAccessible(true);

    $result = $method->invoke($coordinator, ['target_retirement_income' => 28000], $user, false);

    expect($result['onboarding_capture'])->toBeTrue()
        ->and($result['details']['missing'])->toContain('target_retirement_age');

    // No profile written on income-only.
    expect(RetirementProfile::where('user_id', $user->id)->exists())->toBeFalse();
});

it('capture_retirement_goals second turn with both fields persists age and income', function (): void {
    $user = pensioncheckUser(['date_of_birth' => '1975-01-01']);

    $coordinator = app(CoordinatingAgent::class);
    $method = new ReflectionMethod($coordinator, 'handleCaptureRetirementGoals');
    $method->setAccessible(true);

    // Second turn: LLM extracted both fields from transcript context.
    $result = $method->invoke($coordinator, [
        'target_retirement_age' => 67,
        'target_retirement_income' => 28000,
    ], $user, false);

    expect($result['onboarding_capture'])->toBeTrue();

    $profile = RetirementProfile::where('user_id', $user->id)->first();
    expect($profile)->not->toBeNull()
        ->and((int) $profile->target_retirement_age)->toBe(67)
        ->and((float) $profile->target_retirement_income)->toBe(28000.0);
});

// ── 9. campaign2_state_pension coverage (zero in prior agents) ────────────────

it('campaign2_state_pension has advance_on_answered_question set', function (): void {
    $state = SM::getState(SM::STATE_CAMPAIGN2_STATE_PENSION);
    expect($state['advance_on_answered_question'] ?? false)->toBeTrue();
});

it('campaign2_state_pension transitions to campaign_verify_announce on answer', function (): void {
    $user = pensioncheckUser();
    // enterCampaignVerify stamps verify_section + returns campaign_verify_announce.
    $next = SM::getNextStateId(SM::STATE_CAMPAIGN2_STATE_PENSION, 'not sure', $user);
    expect($next)->toBe('campaign_verify_announce');
});

it('campaign2_state_pension uses the capture_state_pension extraction tool', function (): void {
    $state = SM::getState(SM::STATE_CAMPAIGN2_STATE_PENSION);
    expect($state['extraction_tool'] ?? null)->toBe('capture_state_pension');
});

it('campaign2_state_pension has a retry_text with example figures', function (): void {
    $state = SM::getState(SM::STATE_CAMPAIGN2_STATE_PENSION);
    expect($state['retry_text'] ?? '')->toContain('qualifying years');
});

// ── 9b. capture_state_pension garbage-row guard (D1 Fix 3) ─────────────────────
// When the user said "not sure", the live model called capture_state_pension
// with forecast_annual=0, ni_years_completed=0, state_pension_age=0. Those
// explicit zeros passed the old `!== null` filter and wrote a garbage
// state_pensions row (forecast 0 / ni 0 / age 0), which kills the no-forecast
// advice trigger and the row-existence skip. Reject all-zero calls.

it('capture_state_pension rejects an all-zero call without writing a row', function (): void {
    $user = pensioncheckUser();
    $coordinator = app(CoordinatingAgent::class);
    $method = new ReflectionMethod($coordinator, 'handleCaptureStatePension');
    $method->setAccessible(true);

    $result = $method->invoke($coordinator, [
        'forecast_annual' => 0,
        'ni_years_completed' => 0,
        'state_pension_age' => 0,
    ], $user, false);

    // No row written.
    expect(StatePension::where('user_id', $user->id)->exists())->toBeFalse();
    // Not framed as a successful capture.
    expect($result['onboarding_capture'] ?? false)->toBeFalse();
});

it('capture_state_pension writes a row for a genuine forecast', function (): void {
    $user = pensioncheckUser();
    $coordinator = app(CoordinatingAgent::class);
    $method = new ReflectionMethod($coordinator, 'handleCaptureStatePension');
    $method->setAccessible(true);

    $result = $method->invoke($coordinator, [
        'forecast_annual' => 11500,
        'ni_years_completed' => 25,
    ], $user, false);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    $row = StatePension::where('user_id', $user->id)->first();
    expect($row)->not->toBeNull()
        ->and((float) $row->state_pension_forecast_annual)->toBe(11500.0)
        ->and((int) $row->ni_years_completed)->toBe(25);
});

it('capture_state_pension writes a row when only the age is positive', function (): void {
    $user = pensioncheckUser();
    $coordinator = app(CoordinatingAgent::class);
    $method = new ReflectionMethod($coordinator, 'handleCaptureStatePension');
    $method->setAccessible(true);

    $result = $method->invoke($coordinator, [
        'forecast_annual' => 0,
        'state_pension_age' => 67,
    ], $user, false);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect((int) StatePension::where('user_id', $user->id)->first()?->state_pension_age)->toBe(67);
});

// ── 10. Income carry via onboarding_parked_facts ──────────────────────────────
//
// Realistic two-turn scenario: first call receives income only — handler parks
// the income and returns the partial-retry missing signal; second call receives
// age only — handler reads parked income, creates the profile with BOTH fields,
// then clears the parked bucket.

it('income-only first turn parks the income in onboarding_parked_facts', function (): void {
    $user = pensioncheckUser(['date_of_birth' => '1975-01-01']);
    $conversation = pensioncheckConversation($user);

    $coordinator = app(CoordinatingAgent::class);
    $method = new ReflectionMethod($coordinator, 'handleCaptureRetirementGoals');
    $method->setAccessible(true);

    $result = $method->invoke($coordinator, ['target_retirement_income' => 28000], $user, false, $conversation->id);

    expect($result['onboarding_capture'])->toBeTrue()
        ->and($result['details']['missing'])->toContain('target_retirement_age');

    // Income should be parked in the conversation.
    $conversation->refresh();
    $parked = $conversation->onboarding_parked_facts ?? [];
    // JSON roundtrips integers as int; cast to float for comparison.
    expect((float) ($parked['retirement_goals']['target_retirement_income'] ?? 0))->toBe(28000.0);

    // No profile written yet.
    expect(RetirementProfile::where('user_id', $user->id)->exists())->toBeFalse();
});

it('age-only second turn merges parked income and writes both to retirement_profiles', function (): void {
    $user = pensioncheckUser(['date_of_birth' => '1975-01-01']);
    $conversation = pensioncheckConversation($user);

    $coordinator = app(CoordinatingAgent::class);
    $method = new ReflectionMethod($coordinator, 'handleCaptureRetirementGoals');
    $method->setAccessible(true);

    // First turn: income only — parks income.
    $method->invoke($coordinator, ['target_retirement_income' => 28000], $user, false, $conversation->id);

    // Second turn: age only — handler merges the parked income.
    $result = $method->invoke($coordinator, ['target_retirement_age' => 67], $user, false, $conversation->id);

    expect($result['onboarding_capture'])->toBeTrue();

    $profile = RetirementProfile::where('user_id', $user->id)->first();
    expect($profile)->not->toBeNull()
        ->and((int) $profile->target_retirement_age)->toBe(67)
        ->and((float) $profile->target_retirement_income)->toBe(28000.0);

    // Parked bucket cleared after successful write.
    $conversation->refresh();
    $parked = $conversation->onboarding_parked_facts ?? [];
    expect(array_key_exists('retirement_goals', $parked))->toBeFalse();
});

// ── 11. campaign_pension_history — higher-rate income gate ────────────────────
//
// The pension-contribution-history question is only surfaced for higher-rate
// taxpayers; basic-rate users have no carry-forward optimisation to surface.
// Gross income = annual_employment_income + annual_self_employment_income.
// Gate uses TaxConfigService 'income_tax.higher_rate_threshold' (never a literal).

it('campaign_pension_history is present in the transition table', function (): void {
    expect(array_key_exists(SM::STATE_CAMPAIGN_PENSION_HISTORY, SM::states()))->toBeTrue();
});

it('skipIfPensionHistoryNotApplicable returns true for a basic-rate user', function (): void {
    // Income below the higher-rate threshold (default £50,270).
    $user = pensioncheckUser(['annual_employment_income' => 35000]);
    expect(SM::skipIfPensionHistoryNotApplicable($user))->toBeTrue();
});

it('skipIfPensionHistoryNotApplicable returns false for a higher-rate user', function (): void {
    // Income above the higher-rate threshold.
    $user = pensioncheckUser(['annual_employment_income' => 65000]);
    expect(SM::skipIfPensionHistoryNotApplicable($user))->toBeFalse();
});

it('skipIfPensionHistoryNotApplicable sums employment and self-employment income', function (): void {
    // Combined income crosses the higher-rate threshold even though each field alone does not.
    $user = pensioncheckUser([
        'annual_employment_income' => 30000,
        'annual_self_employment_income' => 25000, // combined = £55,000
    ]);
    expect(SM::skipIfPensionHistoryNotApplicable($user))->toBeFalse();
});

it('campaign_pension_history state is skipped when savetax walk exhausts the pensions section', function (): void {
    // Savetax does not include campaign_pension_history in its section map;
    // the state machine must not visit it for a savetax user.
    $user = User::factory()->create([
        'onboarding_fyn_selection' => 'savetax',
        'marital_status' => 'single',
        'monthly_expenditure' => 2500,
    ]);
    // Walk all sections — none should return campaign_pension_history.
    $sections = SM::sectionOrderFor('savetax');
    foreach ($sections as $section) {
        $next = SM::nextCampaignSection($section, $user);
        expect($next)->not->toBe(SM::STATE_CAMPAIGN_PENSION_HISTORY,
            "savetax walk must never land on campaign_pension_history (after section '{$section}')");
    }
});

// ── Task C6: fresh-user pensioncheck intro (G5) ───────────────────────────────

it('buildWorkPrompt for a fresh pensioncheck user returns pension-flavoured intro, not savetax tax plan copy', function (): void {
    $user = pensioncheckUser([
        'first_name' => 'Lara',
        'annual_employment_income' => null,
        'annual_self_employment_income' => null,
        'funnel_answers' => [
            'campaign' => 'pensioncheck',
            'employment' => 'full-time',
            'income' => 'upto_50270',
        ],
    ]);
    $conversation = pensioncheckConversation($user);

    $text = SM::buildWorkPrompt('', $user, $conversation);

    // Pension-flavoured phrase must appear.
    expect($text)->toContain('pension position');

    // Savetax-only phrase must NOT appear.
    expect($text)->not->toContain('tax plan');

    // Must include a time estimate (F12).
    expect($text)->toMatch('/\d+ minutes?/');

    // Must contain a bold income question to open the income section.
    expect($text)->toContain('**Let\'s start with your income.**');
});

it('buildWorkPrompt for a fresh savetax user is byte-identical to its existing output (savetax regression guard)', function (): void {
    $user = User::factory()->create([
        'first_name' => 'Dave',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'savetax',
        'annual_employment_income' => null,
        'annual_self_employment_income' => null,
        'marital_status' => 'single',
        'funnel_answers' => [
            'campaign' => 'savetax',
            'employment' => 'full-time',
            'income' => 'upto_50270',
        ],
    ]);
    $conversation = pensioncheckConversation($user);

    $text = SM::buildWorkPrompt('', $user, $conversation);

    // Savetax-specific phrase must appear.
    expect($text)->toContain('tax plan');

    // Pension-flavoured phrase must NOT appear in the savetax path.
    expect($text)->not->toContain('pension position');
});
