# Tech Debt Report — Session 2026-09-30 (session 4)

**Files analysed:** 18 (released: #1022-#1024 in #1025; #1026, #1027 in #1028; #1029 in #1030)
**Issues found:** 8
**Severity breakdown:** 0 critical, 5 warnings, 3 suggestions

## Warnings

- **`app/Services/Tax/TaxStrategyCalculator.php:68` and `app/Services/Tax/TaxStrategyService.php:57-61`**: Category 4 (performance). One dashboard request works out the affordability figure twice: once in the calculator and again for the tile. That means two `CompositePlanService::financials()` calls, each building the profile. And when no spending is recorded, the tile calls `financials()` a third time. Fix: have the calculator return the money it used (for example on `TaxStrategyOutputDTO`) and let the tile read it.
- **`app/Services/Coordination/HouseholdFinancialContext.php:66-67`**: Category 4 (performance). `availability()` now resolves `PensionAffordability` twice. It reads the expenditure breakdown, and for anyone who is retired or unemployed it also reads `incomePartsFor` (IncomeDefinitionsService), on every composer call. Fix: resolve once, and check `fundedFromCash` only when spending is not recorded (it already short-circuits on `||`, so only the double resolution remains).
- **`app/Services/Tax/Strategies/PensionTaxReliefStrategy.php:60` and `app/Services/Tax/Strategies/IncomeBandStrategy.php:54`**: Category 1. The same cap block (read `tax_relief.basic_rate`, `pensionFundableGross`, `min($availableAA, …)`) is written twice. Fix: a `TaxStrategyContext::capToFundable(float $availableAA, float $basicRelief)`, or keep the basic relief rate on the context so strategies call `$context->pensionFundableGross()` with no argument.
- **`resources/js/components/TaxStrategy/AllowanceCard.vue:72-84` and `resources/mobile/views/TaxStrategy.vue:232-239`**: Category 6 (Rule 20). Each client works out the allowance status label ("Fully used", "£0 of headroom" / "£0 available", "Not available"…) on its own, so today's fix had to be made twice. iOS was not checked. Fix: the server sends a `remaining_label` on each position (`TaxStrategyCalculator::position`) and every client renders it.
- **`app/Services/Tax/PensionAffordability.php:51, 68`; `app/Services/Tax/Strategies/NonEarnerSpousePensionStrategy.php:52`; `app/Services/Tax/Strategies/CrossSpouseBundleStrategy.php:83`; `app/Services/Onboarding/CaptureForms.php:922`; `app/Services/Onboarding/OnboardingStateMachine.php:2648`**: Category 6. New `app()` service-locator calls, some added to avoid constructor cycles (CompositePlanService sits above the tax plan). Fix: inject where no cycle exists (the strategies can take `HouseholdFinancialContext` in the constructor). Keep the lazy resolution only in `PensionAffordability`, with its comment.

## Suggestions

- **`app/Services/Onboarding/CaptureForms.php:120, 279, 1291`**: Category 2. `expenditureTax()` / `EXPENDITURE_TAX` is no longer offered (#1027). It is kept only so a conversation already sitting on that step can save it. Remove it after a few weeks, once no onboarding conversation's last form is `expenditure_tax`.
- **`app/Services/Onboarding/OnboardingChatDirector.php:3514-3519`**: Category 6. The linked-partner retry text is a state-specific branch in `emitRetry`, beside the prompt builder in `OnboardingStateMachine` (#1024). Two places hold the linked-partner wording rule. Fix: let a state's `retry_text` be a builder, as `prompt_text` is, and move the branch into the state machine.
- **`tests/Unit/Services/Tax/PartnerPensionAffordabilityTest.php:103`**: Category 3 (Rule 2, in a test). It asserts `* 720 / 2880`, typed-in figures. Fix: read `TaxStrategyMath::nonEarnerPensionContribution()` for `relief` and `net`.

---

# Carried forward — Session 2026-09-30 (session 3)

**Files analysed:** 18 (merged to `dev`: #1016, #1018-#1021; open PR #1022)
**Issues found:** 7
**Severity breakdown:** 1 critical, 4 warnings, 2 suggestions

## Critical Issues

- **`app/Services/Onboarding/OnboardingService.php:598-613`** — Rule 23 (unsourced figures). The old setup wizard (`/onboarding/full`) creates a mortgage for any property with a balance, with an invented lender ("Mortgage Provider"), a 3.5% rate, a start 5 years ago and 20 years left, and computes a monthly payment from them. The rate is also stored as `0.0350` in a percentage column (0.035%). Only reachable by typing the URL (its one link, `ProfileCompletionCards`, is unrendered). Fix: store only the balance the user gave, or retire the wizard's asset step; CSJ to decide which.

## Warnings

- **`app/Services/Documents/FieldMappers/DCPensionMapper.php:39`, `InvestmentAccountMapper.php:32`, `LifeInsuranceMapper.php:38`** — Category 6. `platform_fee_percent` and `indexation_rate` still go through `parsePercentage()`, which returns a fraction. #1018 found the same mapper storing fractions into percentage columns for savings and mortgage rates. Verify each column's convention (readers, validation) and move the percentage ones to `parseRatePercent()`.
- **`app/Services/Onboarding/SpouseHoldingTransfer.php:28-31` and `app/Services/Tax/TaxStrategyCalculator.php:482-483`** — Category 1. The working and not-working status lists are written out twice. One public constant (or a small value class) both read.
- **`app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php:86-135`** — Category 4. `$stackedCapacity`, `$psaBasic`, `$reportedTransfer`, `$taxableInterestSheltered` are assigned inside the first `if` and read inside the second, which is safe only because `$estimatedAnnualTaxSaved >= 1` implies the first ran. Merge the two blocks into one `if` with an early skip.
- **`app/Services/Account/RetentionPurgeService.php:27, 98, 101`** — Category 1. `ANONYMISED_TABLES` names `audit_logs` and `ai_cost_attribution`, and Phase 6 anonymises each with its own hardcoded query. Adding a table to the constant does not anonymise it. Drive the phase from a map of table => columns to null.

## Suggestions

- **`app/Services/Onboarding/WalkFormPrefill.php:120-121`** — Category 4. `recordsFor()` loads the user's savings and pensions on every call, whatever the form. Load them lazily inside the `match` arms that need them.
- **`app/Services/Tax/TaxStrategyCalculator.php`** (646 lines) — Category 4. Over 500 lines; the allowance-grid builders (`buildUserAllowanceGrid`, the three spouse grids, `stackInterest`, `pensionPosition`, `spousePensionPosition`) are a natural `AllowanceGridBuilder` extraction.

---

# Carried forward — Session 2026-09-30 (session 2)


**Files analysed:** 17 (all merged to `dev`: #1009, #1010, #1011, #1013, #1014)
**Issues found:** 7
**Severity breakdown:** 0 critical, 4 warnings, 3 suggestions

## Critical Issues

None in the changed files. (The Retirement page for already-retired users is a product defect in unchanged files; it is on `CSJTODO.md`, not here.)

## Warnings

1. **`resources/js/utils/dateFormatter.js:18, 51, 79, 109, 126, 203`**: Inconsistency (Category 6).
   - **Problem:** `formatDate`, `formatDateForInput` for full timestamps, `parseDate`, `formatDateLong` and the helper at 203 all turn a `YYYY-MM-DD` string into a `Date` with `new Date(string)`. That is UTC midnight, so west of Greenwich the date shows a day early.
   - **Scope:** this session fixed only the date-of-birth path (`formatDateOnlyLong`, plus the date-only pass-through in `formatDateForInput`). `formatDateLong` alone has 16 callers.
   - **Fix:** read the calendar day from the string, as `formatDateOnlyLong` does, inside `parseDate`, and route the others through it.

2. **`app/Agents/CoordinatingAgent.php:6576-6600`** with the create path at `:3433`: two homes for one figure (Category 6).
   - **Problem:** `users.annual_dividend_income` is a running total maintained only by Fyn's create and edit tools. The web investment account edit (HTTP controller → `InvestmentAccountStore::update`) changes an account's dividends without moving the user's total, and deleting an account never subtracts it.
   - **Fix:** derive the taxable dividend total from the non-ISA accounts in one place, and stop keeping a running sum.

3. **`resources/js/components/UserProfile/PersonalInformation.vue:731`** against **`resources/js/components/Onboarding/ProfileReviewPanel.vue:88-89`**: duplicate label maps (Category 1).
   - **Problem:** the two employment-status maps disagree ("Full-Time" against "Full-time"). `ProfileReviewPanel` also labels `employed` as "Full-time". This was carried from session 1.
   - **Fix:** one shared map in `constants/profileOptions`.

4. **csjones `public/.htaccess`** (deploy, not code): fragility (Category 6).
   - **Problem:** the checkout's local subdirectory edit is lost to any `git reset --hard` or `checkout -f`, and the proxy cache then keeps the wrong site. `skip-worktree` cannot protect it because the checkout is sparse.
   - **Fix:** point the sparse checkout at `deploy/csjones-fynla/.htaccess` through a server-side symlink, or keep csjones's `.htaccess` outside git management. Memory `feedback_never_reset_hard_on_csjones` holds meanwhile.

## Suggestions

1. **`app/Services/Tax/TaxStrategyMath.php:558-560, 625-631`**: efficiency (Category 4).
   - **Problem:** `marriageAllowance` calls `linkedSpouseWithIncome`, which computes `incomePartsFor($linked)`, then computes it again on line 560, so income definitions are built twice per call.
   - **Fix:** return the parts from the helper, or memoise per user id.

2. **`app/Services/Onboarding/WalkFormPrefill.php:152-158`**: efficiency (Category 4).
   - **Problem:** `formSaved` loads every user message's metadata in the conversation, on each form emission.
   - **Fix:** a JSON `where` on `metadata->form->name` with `exists()`.

3. **`app/Services/Onboarding/SpouseHoldingTransfer.php`** (`splitIncome`): magic value (Category 4).
   - **Problem:** `'retired'` is compared as a bare string while its sibling lists are class constants (`WORKING_STATUSES`, `NON_WORKING_STATUSES`).
   - **Fix:** a `RETIRED_STATUS` constant, or a pension-income status list.

---
*Generated by tech-debt-session skill*
