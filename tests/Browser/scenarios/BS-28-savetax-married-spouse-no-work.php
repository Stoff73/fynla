<?php

declare(strict_types=1);

/**
 * BS-28 — SaveTax campaign, married, spouse non-working (Path C / single_earner_couple)
 *
 * Sprint: SaveTax sections 4-6
 * Spec: April/April29Updates/savetax-campaign-post-expenses-plan.md §Verification
 * Pest siblings:
 *   - tests/Unit/Services/Onboarding/CampaignStateMachineBranchTest.php
 *   - tests/Feature/AI/DirectWrite/CaptureSpouseNonWorkingAssetsTest.php
 *   - tests/Feature/AI/DirectWrite/CaptureSpouseWorkStatusTest.php
 *   - tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php (Path C tests)
 *
 * Goal: drive the single_earner_couple path end-to-end. SPOUSE_NON_WORKING_ASSETS
 * state IS visited (NOT SPOUSE_HOUSEHOLD). Terminal page renders HouseholdView
 * in single_earner_couple mode with a prominent AssetShiftingPanel showing
 * concrete asset-transfer suggestions and Marriage Allowance.
 *
 * Seed: none.
 *
 * Script:
 *   1. Navigate /savetax.
 *   2. Register as a married persona; complete MFA. Set user income to
 *      £50,000 (basic-rate) so Marriage Allowance is eligible AND saves tax.
 *   3. Onboarding chat through DOB + marital='married' + spouse details +
 *      dependants + employment + expenditure → "Looks correct".
 *   4. Walk through OCCUPATIONAL_SCHEME → ISA → BANK → INVESTMENT → PENSION.
 *      In BANK, capture a meaningful balance (e.g. £200,000 @ 3.5%) so the
 *      asset-shifting calculator has at-risk holdings.
 *   5. STATE_CAMPAIGN_SPOUSE_WORK bubble: click "No, they don't currently work".
 *      → capture_spouse_work_status fires:
 *           household_calculation_mode = 'single_earner_couple'
 *           marriage_allowance_eligible = true
 *      → router goes to STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS (NOT
 *         SPOUSE_HOUSEHOLD).
 *   6. STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS: capture spouse's existing
 *      standalone assets — test mix: spouse_existing_isa_balance=£5,000,
 *      others 0 ("they have a small Cash ISA from before we married, nothing
 *      else").
 *      → capture_spouse_non_working_assets fires; tax_strategy_household_inputs
 *         row created with the non-working-spouse fields populated.
 *   7. STATE_CAMPAIGN_TERMINAL → "We've created your personal tax strategy" +
 *      tap "Take me to my tax strategy" button → /tax-strategy.
 *   8. browser_snapshot → docs/savetax-verification/BS-28/01-asset-shifting.png
 *      Assertions on rendered page:
 *        - HouseholdView renders TWO AllowanceGrid (user + spouse).
 *        - Spouse grid is mostly red/empty (low utilisation_pct on PA,
 *          PSA, Starting Rate, ISA, CGT, Dividend, AA).
 *        - AssetShiftingPanel visible with at least:
 *            • marriage_allowance_transfer suggestion (basic-rate user).
 *            • savings_to_spouse suggestion with concrete £-amount and
 *              estimated_annual_tax_saved > 0.
 *            • isa_topup_spouse suggestion with available_allowance ≈ £15,000
 *              (£20,000 - £5,000 existing).
 *        - "Coordinate as a household" cross-spouse panel NOT shown (that
 *          panel is for path B / dual_earner).
 *
 *   9. DB assertions:
 *        - users.household_calculation_mode = 'single_earner_couple'
 *        - users.marriage_allowance_eligible = true
 *        - tax_strategy_household_inputs row exists
 *          - spouse_existing_isa_balance = 5000.00
 *          - spouse_existing_savings_balance = 0 (or whatever was entered)
 *          - working-spouse fields (spouse_annual_income, spouse_psa_band,
 *            spouse_unrealised_gains, spouse_annual_dividends,
 *            spouse_pension_input_annual) all NULL.
 *
 * Pass: every assertion holds, single-iteration GREEN per Rule #15.
 *
 * Fail-loop: if any assertion fails, diagnose root cause:
 *   - state machine routing wrong (check household_calculation_mode set?)
 *   - capture tool not firing (check OnboardingChatDirector tool whitelist)
 *   - calculator emitting wrong suggestions (check Path C branch in
 *     buildAssetShiftingSuggestions)
 *   - frontend rendering wrong panel (check HouseholdView v-if logic)
 * Fix root cause, re-run from Step 1. No partial-evidence completion.
 */
it('BS-28 savetax campaign married+single_earner_couple', function (): void {
    $this->markPendingInteractiveRun('BS-28');
});
