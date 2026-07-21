<?php

declare(strict_types=1);

/**
 * BS-26 — SaveTax campaign, single, employed (Path A)
 *
 * Sprint: SaveTax sections 4-6
 * Spec: April/April29Updates/savetax-campaign-post-expenses-plan.md §Verification
 * Pest sibling tests:
 *   - tests/Unit/Services/Onboarding/CampaignStateMachineBranchTest.php
 *   - tests/Feature/Api/TaxStrategy/ShowEndpointTest.php
 *   - tests/Feature/AI/DirectWrite/CaptureSalarySacrificeTest.php
 *
 * Goal: drive the full single-user SaveTax campaign onboarding end-to-end
 * via Playwright MCP, then confirm the /tax-strategy dashboard renders the
 * single-mode allowance grid and that sliders trigger live recalc.
 *
 * Seed: none (factory test user created inline; or use Personal TestUser).
 *
 * Script (Claude walks all steps via Playwright MCP):
 *
 *   1. browser_navigate('http://localhost:8000/savetax?utm_source=linkedin')
 *      → page loads; sessionStorage('fynla.signup_source') === 'linkedin'.
 *
 *   2. Complete the questionnaire and continue to registration.
 *      → URL becomes /register?from=savetax.
 *
 *   3. browser_fill_form: first_name=Verify, last_name=Single, email
 *      throwaway, password Password1!, marital_status will be set later
 *      via onboarding bubbles.
 *
 *   4. browser_click submit → MFA modal appears.
 *      Fetch verification code from DB:
 *        php artisan tinker --execute="..."
 *      Enter the 6-digit code.
 *
 *   5. Land on /dashboard with Fyn auto-opened. First bubble welcomes the
 *      user with the savetax campaign opener and asks for DOB + marital.
 *      Drive through:
 *        - DOB + marital='single'
 *        - dependants=no
 *        - employment=full_time, occupation, income (£50,000 example)
 *        - employment_more=no
 *        - expenditure (any value)
 *        - profile_review_expenditure → "Looks correct"
 *
 *   6. NEW: campaign branch begins.
 *        - STATE_CAMPAIGN_OCCUPATIONAL_SCHEME: capture pension contribution
 *          % + employer match + salary_sacrifice. LLM calls create_pension
 *          and capture_salary_sacrifice.
 *        - STATE_CAMPAIGN_ISA_HOLDINGS: any/none.
 *        - STATE_CAMPAIGN_BANK_ACCOUNTS: any/none.
 *        - STATE_CAMPAIGN_INVESTMENT_ACCOUNTS: any/none.
 *        - STATE_CAMPAIGN_PENSION_CONTRIBS: any SIPP.
 *        - STATE_CAMPAIGN_SPOUSE_WORK: SKIPPED (single user — skipIfNotMarried).
 *        - STATE_CAMPAIGN_TERMINAL: "We've created your personal tax strategy"
 *          + a "Take me to my tax strategy" button (tap → /tax-strategy).
 *
 *   7. Tap "Take me to my tax strategy" → land on /tax-strategy.
 *      browser_snapshot → docs/savetax-verification/BS-26/01-terminal.png
 *
 *      Assertions on the rendered page:
 *        - Tax year heading visible (e.g. "Tax year 2026/27").
 *        - Single AllowanceGrid (no HouseholdView) with 8 allowance cards.
 *        - StrategySliderPanel visible.
 *        - StrategyRecommendationList visible (may be empty).
 *
 *   8. Drag the pension contribution slider to 20%.
 *      browser_snapshot → docs/savetax-verification/BS-26/02-slider.png
 *      Wait 300ms; verify Pension Annual Allowance card "used" amount
 *      increases.
 *
 *   9. DB assertions:
 *      - users.onboarding_completed = true
 *      - users.onboarding_fyn_path = 'campaign'
 *      - users.onboarding_fyn_selection = 'savetax'
 *      - users.signup_source = 'linkedin'
 *      - users.household_calculation_mode = 'single' (or null — single users
 *        don't trigger capture_spouse_work_status)
 *      - users.marriage_allowance_eligible IS NULL
 *      - tax_strategy_household_inputs has zero rows for this user
 *      - dc_pensions for this user has at least one row with
 *        salary_sacrifice = true (if user said yes during occupational scheme)
 *
 * Pass: every assertion above holds, no console errors, single-iteration
 * GREEN per CLAUDE.md Rule #15.
 *
 * Fail-loop: if any assertion fails, diagnose with file:line evidence
 * (DB row, network request, code path), fix the root cause, re-run from
 * Step 1. Do NOT mark complete on partial evidence.
 */
it('BS-26 savetax campaign single+employed', function (): void {
    $this->markPendingInteractiveRun('BS-26');
});
