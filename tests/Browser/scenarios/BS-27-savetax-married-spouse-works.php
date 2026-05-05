<?php

declare(strict_types=1);

/**
 * BS-27 — SaveTax campaign, married, spouse works (Path B / dual_earner)
 *
 * Sprint: SaveTax sections 4-6
 * Spec: April/April29Updates/savetax-campaign-post-expenses-plan.md §Verification
 * Pest siblings:
 *   - tests/Unit/Services/Onboarding/CampaignStateMachineBranchTest.php
 *   - tests/Feature/AI/DirectWrite/CaptureSpouseHouseholdDataTest.php
 *
 * Goal: drive the dual_earner path end-to-end. SPOUSE_HOUSEHOLD state IS
 * visited; SPOUSE_NON_WORKING_ASSETS state is SKIPPED. Terminal page renders
 * HouseholdView with twin grids + cross-spouse coordination suggestions.
 *
 * Seed: none.
 *
 * Script:
 *   1. Navigate /savetax (any utm_source or none).
 *   2. Register as a married persona; complete MFA.
 *   3. Onboarding chat captures DOB + marital='married', spouse details
 *      (spouse first_name, DOB, email — STATE_BASE_SPOUSE), dependants,
 *      employment (user works), expenditure.
 *   4. Confirm "Looks correct" at STATE_PROFILE_REVIEW_EXPENDITURE → enters
 *      the campaign branch.
 *   5. Walk through OCCUPATIONAL_SCHEME → ISA → BANK → INVESTMENT → PENSION.
 *   6. STATE_CAMPAIGN_SPOUSE_WORK bubble: click "Yes, they work".
 *      → capture_spouse_work_status fires:
 *           household_calculation_mode = 'dual_earner'
 *           marriage_allowance_eligible = false
 *      → router goes to STATE_CAMPAIGN_SPOUSE_HOUSEHOLD (NOT
 *         SPOUSE_NON_WORKING_ASSETS).
 *   7. STATE_CAMPAIGN_SPOUSE_HOUSEHOLD: capture spouse_annual_income
 *      (~£40,000), psa_band, spouse ISA balance, etc.
 *      → capture_spouse_household_data fires; tax_strategy_household_inputs
 *         row created.
 *   8. STATE_CAMPAIGN_TERMINAL → navigate to /tax-strategy.
 *   9. browser_snapshot → docs/savetax-verification/BS-27/01-twin-grids.png
 *      Assertions on rendered page:
 *        - HouseholdView renders TWO AllowanceGrid (user + spouse).
 *        - "Coordinate as a household" panel visible if cross-spouse
 *          suggestions exist.
 *        - AssetShiftingPanel NOT shown (path B doesn't asset-shift).
 *
 *  10. DB assertions:
 *        - users.household_calculation_mode = 'dual_earner'
 *        - users.marriage_allowance_eligible = false
 *        - tax_strategy_household_inputs row exists
 *          - spouse_annual_income > 0
 *          - spouse_psa_band IN ('basic', 'higher', 'additional')
 *        - non-working-spouse fields all NULL on the row
 *
 * Pass: every assertion holds, single-iteration GREEN per Rule #15.
 */
it('BS-27 savetax campaign married+dual_earner', function (): void {
    $this->markPendingInteractiveRun('BS-27');
});
