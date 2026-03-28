# Bug Updates — 27 March 2026

## Bug #1: Onboarding progress bar shows red dash on Spending & Debts steps

**Reported by:** Claude (brett.isenberg@capitoul.co.uk), User ID 448
**Submitted:** 2026-03-26T13:12:18+00:00

### Problem

Steps 4 (Spending/expenditure) and 6 (Debts/liabilities) showed a red/pink dash icon instead of a green tick after completion. All other steps (About You, Family, Income, Assets, Estate, Goals) showed green ticks correctly.

### Root Cause

Frontend/backend step config mismatch. The frontend `lifeStageConfig.js` defines `expenditure` and `liabilities` as onboarding steps for mid_career, peak, and retirement journeys, but the backend `LifeStageService.php` step field config methods (`buildPeakSteps`, `buildMidCareerSteps`, etc.) did not include these steps.

When the frontend requested step completeness from the API, the response had no entry for `expenditure` or `liabilities`. The frontend fallback logic returned `'skipped'` status, which renders as a red dash.

**Affected journeys:**
| Journey | Missing Steps |
|---------|---------------|
| early_career | expenditure |
| mid_career | expenditure, liabilities |
| peak | expenditure, liabilities |
| retirement | expenditure |

University journey was already correct (had `expenditure` defined).

### Fix

**File:** `app/Services/LifeStage/LifeStageService.php`

Added the missing step field configs to all 4 affected journey builders:

- `buildEarlyCareerSteps()` — added `$steps['expenditure'] = ['has_expenditure'];`
- `buildMidCareerSteps()` — added `$steps['expenditure'] = ['has_expenditure'];` and `$steps['liabilities'] = ['has_liabilities'];`
- `buildPeakSteps()` — added `$steps['expenditure'] = ['has_expenditure'];` and `$steps['liabilities'] = ['has_liabilities'];`
- `buildRetirementSteps()` — added `$steps['expenditure'] = ['has_expenditure'];`

The `isFieldFilled()` method already handled both `has_expenditure` and `has_liabilities` checks correctly — the only issue was that these steps were never included in the journey step configs.

---

## Bug #2: Dashboard shows "6 of 8 steps complete" despite all data entered

**Reported by:** Claude (brett.isenberg@capitoul.co.uk), User ID 448
**Submitted:** 2026-03-26T13:13:28+00:00

### Problem

Dashboard header showed "6 of 8 steps complete" and "Next: Spending" even though all 8 steps had data entered. The progress bar showed "100%" which contradicted the "6 of 8" text.

### Root Cause

Same root cause as Bug #1 — the backend did not return completeness data for `expenditure` and `liabilities` steps.

Three frontend consumers were affected differently by the missing data:

1. **`completedCount`** (`JourneyProgressHero.vue:126-128`) — filters onboarding steps where `completeness[step]?.status === 'complete'`. Missing steps returned `undefined`, so only 6 of 8 counted as complete.

2. **`nextStep`** (`lifeStage.js:71-75`) — finds first step where status is not `'complete'`. Found `expenditure` (missing = not complete), so showed "Next: Spending".

3. **`progressPercentage`** (`lifeStage.js:49-68`) — skips steps with no completeness data entirely (`if (stepInfo)` guard), so it calculated 6/6 fields = 100% instead of 6/8.

This caused the contradictory display: 100% progress bar but "6 of 8 steps complete".

### Fix

Same fix as Bug #1 — no additional changes needed. The `LifeStageService.php` fix ensures the backend returns completeness data for all steps, so all three consumers now get correct data.

---

## Bug #3: Pension summary card shows "Monthly Contribution: £0" with percentage contributions

**Reported by:** Claude (brett.isenberg@capitoul.co.uk), User ID 448
**Submitted:** 2026-03-26T13:14:21+00:00

### Problem

Onboarding pension summary card displayed "Monthly Contribution: £0" despite entering 5% employee and 3% employer contributions on a £95,000 salary. The data was saved correctly — Annual Allowance showed £7,600 used (8% of £95,000).

### Root Cause

`AssetsStep.vue` displayed `pension.monthly_contribution_amount` directly. This field is only populated for SIPP/personal pensions with flat monthly amounts. Workplace pensions store contributions as percentages (`employee_contribution_percent` + `employer_contribution_percent`) with `annual_salary`, but the summary card didn't calculate the monthly amount from these.

The backend `PensionProjector.calculateAnnualContribution()` already handles both methods correctly — the gap was only in the onboarding summary card display.

### Fix

**File:** `resources/js/components/Onboarding/steps/AssetsStep.vue`

Added `getPensionMonthlyContribution(pension)` helper that mirrors the backend logic:
1. If `monthly_contribution_amount > 0`, use it (SIPP/personal)
2. Otherwise, calculate from `annual_salary * (employee% + employer%) / 12` (workplace)
3. Returns 0 if neither method applies

Updated the template to use the helper instead of the raw field.

---

## Bug #4: Retirement age shows 67 instead of user's entered 65

**Reported by:** Claude (brett.isenberg@capitoul.co.uk), User ID 448
**Submitted:** 2026-03-26T13:15:03+00:00

### Problem

Retirement age displayed as 67 (UK State Pension age) across the platform despite user entering 65 as their target retirement age during onboarding.

### Root Cause

Two issues:

1. **Onboarding pension card** (`AssetsStep.vue:66`) — displayed `pension.retirement_age || 67`. Since DC pensions don't always have `retirement_age` set (it's per-pension, not per-user), it fell back to 67 without checking the user's `target_retirement_age`.

2. **Retirement page** (`RetirementController.php:77`) — returned `RetirementProfile` as the `profile` response. But if no RetirementProfile exists yet (common during onboarding), or if the profile's `target_retirement_age` isn't set, all frontend components fell back to 67.

### Fix

**File 1:** `resources/js/components/Onboarding/steps/AssetsStep.vue`
- Changed fallback chain: `pension.retirement_age || currentUser?.target_retirement_age || 67`

**File 2:** `app/Http/Controllers/Api/RetirementController.php`
- When RetirementProfile exists but lacks `target_retirement_age`, populate it from `$user->target_retirement_age`
- When no RetirementProfile exists, create a synthetic profile object with the user's `target_retirement_age` and `current_age`

---

## Bug #5: ISA Allowance tracker shows £0 used despite monthly contributions

**Reported by:** Claude (brett.isenberg@capitoul.co.uk), User ID 448
**Submitted:** 2026-03-26T13:15:37+00:00

### Problem

Dashboard ISA allowance tracker showed "£0 used, £20,000 remaining" despite having a S&S ISA with £500/month contributions. Pension allowance tracker on the same page worked correctly.

### Root Cause

`ISATracker.php` calculated S&S ISA usage from `isa_subscription_current_year` on InvestmentAccount records (line 63-66). This field is a manually tracked subscription amount — it's not auto-populated when the user enters monthly contributions during onboarding.

The existing projection logic (`calculateProjectedSubscriptions`) only covered Cash ISAs (SavingsAccount), not S&S ISAs (InvestmentAccount).

### Fix

**File:** `app/Services/Savings/ISATracker.php`

1. When `isa_subscription_current_year` is 0, estimate S&S ISA usage from `monthly_contribution_amount * months_elapsed_in_tax_year`
2. Added `estimateStocksSharesIsaUsage()` method that calculates elapsed months since tax year start (April 6) and multiplies by monthly contributions
3. Updated projected usage to include S&S ISA projections (full year estimate)

---

## Bug #6: NRB line items show £0 in IHT allowance breakdown

**Reported by:** Guest (not logged in)
**Submitted:** 2026-03-26T13:16:24+00:00

### Problem

In the Estate Planning IHT calculation table (Joint Death Scenario), the Nil Rate Band line items for both users showed £0 despite the total allowances showing the correct £1,000,000. RNRB values (£175,000 each) displayed correctly.

### Root Cause

`IHTCalculationTable.vue` imported `IHT_NIL_RATE_BAND` from `@/constants/taxConfig` (line 485) but never exposed it to the Vue template. In Vue 3 Options API, module-level imports are NOT available in templates — only `data`, `computed`, `methods`, and `props` are. So `IHT_NIL_RATE_BAND` resolved to `undefined` in the template, and `formatCurrency(undefined)` rendered as `£0`.

The total was correct because `totalAllowances` is a computed property using `allowances.totalNrb` (passed as prop from the parent) which correctly included NRB values. Only the line-item display was broken.

### Fix

**File:** `resources/js/components/Estate/IHTCalculationTable.vue`

Added `IHT_NIL_RATE_BAND` to the component's `data()` return object, making it accessible in the template.

---

## Bug #7: Credit card balance persists in IHT projection despite being on track to be paid off

**Reported by:** Guest (not logged in)
**Submitted:** 2026-03-26T13:17:03+00:00

### Problem

Credit card (£3,200 balance, £250/month, 21.9% APR) was included at full value in IHT estate projection at age 84, despite the repayment calculator showing it would be paid off within ~15 months.

### Root Cause

Two issues in `IHTCalculationService.php`:

1. **Wrong field name:** `projectLiabilities()` referenced `$liability->end_date` (lines 962, 986) but the Liability model uses `maturity_date`. Since `end_date` doesn't exist, it returned `null`, falling through to the default "assume cleared at retirement age" heuristic.

2. **No payoff estimation:** When `maturity_date` is null (common for credit cards), and the user is past retirement age or hasn't set one, the liability was included at full balance. No calculation was done from `monthly_payment` and `interest_rate` to estimate when the debt would be paid off.

### Fix

**File:** `app/Services/Estate/IHTCalculationService.php`

1. Changed `$liability->end_date` to `$liability->maturity_date` for both user and spouse liabilities
2. Added `estimatePayoffDate()` fallback — when no maturity date is set, calculates payoff date from `current_balance`, `monthly_payment`, and `interest_rate` using standard amortisation formula
3. Applied to both user and spouse liability projections

---

## Bug #8: Dividend tax rate label shows "34%" instead of "33.75%"

**Reported by:** Guest (not logged in)
**Submitted:** 2026-03-26T13:17:40+00:00

### Problem

Dividend tax rate label displayed "34%" instead of the correct 33.75% higher rate. The calculation was correct (£1,013 tax), only the label was wrong.

### Root Cause

`TaxIncomeCard.vue` line 336: `Math.round((value ?? 0) * 100)` — the rate arrives as a decimal (0.3375), gets multiplied by 100 to give 33.75, then `Math.round()` rounds it to 34. UK dividend tax rates are 8.75%, 33.75%, and 39.35% — all non-integer percentages that would be incorrectly rounded.

### Fix

**File:** `resources/js/components/UserProfile/TaxIncomeCard.vue`

Updated `formatPercent()` to preserve decimal places: shows integer percentages without decimals (e.g. "20%") and non-integer percentages with up to 2 decimal places (e.g. "33.75%").

---

## Bug #9: Fyn chatbot reports incorrect financial data (net worth, pensions, mortgage)

**Reported by:** Guest (not logged in)
**Submitted:** 2026-03-26T13:18:23+00:00

### Problem

Fyn chatbot reported:
- Net worth £576,800 (dashboard correctly shows £430,800)
- Pension £0 (should be £185,000)
- No mortgage (should be £71,000 individual share)
- Only credit card in liabilities (missing mortgage)

### Root Cause

The chatbot's system prompt financial context was built from the **Estate module analysis**, not a dedicated net worth calculation. The estate module's `net_estate` (an IHT concept) differs from net worth because:
- DC pensions are IHT-exempt and excluded from `net_estate`
- Joint property handling differs (estate uses second-death aggregation)
- The `mapEstateAnalysis()` method mapped `summary['net_estate']` to a field called `net_worth`, creating a misleading label

The financial context at `HasAiChat.php:801` included "Net estate value" from the estate module, which the AI interpreted as "net worth" when answering user questions.

### Fix

**File:** `app/Traits/HasAiChat.php`

1. Added proper net worth calculation using `NetWorthService.calculateNetWorth()` at the top of the financial context — this includes all assets (pensions, property, investments, cash) and all liabilities (mortgages, credit cards, loans) with correct joint ownership shares
2. Removed the misleading "Net estate value" line from the estate section (it conflated IHT estate with net worth)
3. Fixed property ownership check to use `forUserOrJoint()` instead of `where('user_id', ...)`

---

## Bug #10: Goals page shows different net worth (£328,560) from dashboard (£430,800)

**Reported by:** Guest (not logged in)
**Submitted:** 2026-03-26T13:19:01+00:00

### Problem

Goals page net worth projection chart started at £328,560 while the dashboard correctly showed £430,800 — a £102,240 discrepancy.

### Root Cause

Three issues in `GoalsProjectionService.php`:

1. **First data point adjusted**: The projection loop immediately deducted annual expenditure from cash at `$age = $currentAge` (line 163). The first data point should show the actual current snapshot, not an adjusted value.

2. **Income never credited**: Annual expenditure was deducted from cash each year (line 163) but annual income was never added back. The `$annualNetIncome` was only used for the output `surplus` field, not applied to cash. This caused the projection to show a dramatic decline.

3. **Missing non-mortgage liabilities**: `$totalLiabilities = $mortgage` (line 205) only included mortgages, ignoring credit cards and loans from the net worth breakdown.

### Fix

**File:** `app/Services/Goals/GoalsProjectionService.php`

1. First year (current age) now records the actual snapshot — no income/expense adjustments
2. Subsequent years apply annual surplus (income minus expenditure) instead of just deducting expenditure
3. Added non-mortgage liabilities (credit cards, loans) to the total, with year-on-year reduction
