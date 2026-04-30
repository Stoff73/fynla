# Tech Debt Report — Session 120 (30 April 2026, evening)

**Files analysed:** 28 (12 new strategy/math/contract files + 1 model + 2 migrations + 1 strategy + 1 calculator slim + 5 wiring touches + 3 test files + 3 already-tracked auxiliaries)
**Issues found:** 5
**Severity breakdown:** 0 critical, 0 warnings, 5 suggestions

## Critical Issues

None. Phase A refactor preserved behaviour exactly (81 existing tests pass unchanged), Phase B + C added 12 new green tests, parity test green, architecture suite green, Pint clean. No security or correctness concerns. No banned colors / DB facade / raw queries. All new files declare `strict_types=1` and have full type hints.

## Warnings

None.

## Suggestions

### S-1 — Dead `$personalAllowance` variable in `IncomeBandStrategy`
**File:** `app/Services/Tax/Strategies/IncomeBandStrategy.php:33`
**Category:** Dead & Redundant Code
**What's wrong:** `$personalAllowance = (float) ($income['personal_allowance'] ?? 12570);` is assigned but never read. Carried verbatim from the original `buildIncomeBandRecommendations` (pre-existing debt). Rule #1 (preserve behaviour) meant we kept it; rule against adding cleanup beyond scope meant we didn't fix it.
**Suggested fix:** Delete the line. `$additionalRateThreshold` is the only PA-related value the method uses, and it's read directly from `bandThresholds()`.

### S-2 — Dead `$pension` variable in `LifecycleStrategy`
**File:** `app/Services/Tax/Strategies/LifecycleStrategy.php:30`
**Category:** Dead & Redundant Code
**What's wrong:** `$pension = $this->taxConfig->getPensionAllowances();` is assigned but never read. Same pre-existing debt — the Junior Pension constants are hard-coded as `2880.0` / `720.0` rather than reading from `$pension`. Carried verbatim from the original method.
**Suggested fix:** Either delete the line OR swap the hardcoded `2880.0` / `720.0` for `$pension['junior_pension']['net_cap'] ?? 2880` and `$pension['junior_pension']['government_uplift'] ?? 720`. The CSJTODO already tracks this as **Phase 3 S-3** ("Hardcoded Junior Pension £2,880 / £720 — add a comment citing HMRC source or expose via TaxConfigService"). Phase 3 added a second instance for `non_earner_spouse_pension` (now also at `NonEarnerSpousePensionStrategy.php:53-54`); Phase 4 didn't make this worse but didn't address it.

### S-3 — Bundle strategies share legacy-array-to-DTO conversion
**Files:** `app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php:153-156`, `app/Services/Tax/Strategies/CrossSpouseBundleStrategy.php:81-84`
**Category:** Duplicate Code (within the new Strategies namespace)
**What's wrong:** Both bundle classes end with the same conversion:
```php
return array_map(
    fn (array $arr) => StrategyRecommendation::fromArray(StrategyCategory::Household, $arr),
    $suggestions,
);
```
**Suggested fix:** Extract an `AbstractHouseholdBundle` base class with a protected `wrapAsHouseholdRecs(array $suggestions): array` helper, OR keep the duplication (only 2 callers, both household-mode bundles, low growth pressure). Defer until a 3rd bundle class appears.

### S-4 — `TaxStrategyMath::bandRateFor()` still hardcodes marginal rates
**File:** `app/Services/Tax/TaxStrategyMath.php:75-82`
**Category:** Convention Violations (CLAUDE.md Rule #3 — no hardcoded tax values)
**What's wrong:**
```php
return match ($this->bandFromIncome((float) ($user->annual_employment_income ?? 0))) {
    'basic' => 0.20,
    'higher' => 0.40,
    'additional' => 0.45,
};
```
Pre-existing debt; was in the calculator's `bandRateFor()`. Carried verbatim into `TaxStrategyMath`. The CSJTODO already tracks this as **W-2** ("Read marginal income-tax rates from `getIncomeTax()['bands']` rather than hardcoding"). Phase 4 didn't make this worse but didn't address it either.
**Suggested fix:** Read from `$this->taxConfig->getIncomeTax()['bands']` and look up by band name. Same fix that the dividend-rate / CGT-rate match-statements need (CSJTODO **W-1** + Phase 3 W-1). Bundle when actioning.

### S-5 — `PensionAACarryForwardStrategy` assumes constant AA across the lookback window
**File:** `app/Services/Tax/Strategies/PensionAACarryForwardStrategy.php:55-67`
**Category:** Complexity & Maintainability
**What's wrong:** The strategy uses today's `annual_allowance` (£60,000) for every prior year in the carry-forward calculation. For 2025/26 → 2022/23 this matches HMRC (AA was £40,000 in 2022/23 then £60,000 from 2023/24, so over-counts unused AA for the 2022/23 entry by up to £20k). The class docblock acknowledges this:
> "AA is held at the current value across the window — a conservative simplification (AA was the same £40k/£60k over the relevant period); refine if HMRC changes mid-window."
**Suggested fix:** Either (a) read AA per-year from `TaxConfigService` if a `historical_annual_allowances` table is added, or (b) leave as-is and revisit if HMRC changes the AA mid-window. Conservative behaviour is in the user's favour today (we may slightly over-state available carry-forward by £20k for users who pre-date 2023/24, recouping ~£8k at 40% rate). No action needed unless CSJ wants exact historical figures.

---

## Notes

- **Phase 4 introduced three new constants correctly** — `GiftAidHigherRateReliefStrategy::HIGHER_RATE_FACTOR` (0.25), `::ADDITIONAL_RATE_FACTOR` (0.3125), `PensionAACarryForwardStrategy::LOOKBACK_YEARS` (3). These follow the "magic numbers → named constants" pattern.
- **No new hardcoded tax values added.** All UK figures (PA, AA, ISA cap, CGT AEA, dividend allowance, NI rates) read from `TaxConfigService`. Only the marginal-rate match (S-4) and the Junior Pension £2,880/£720 (S-2) remain pre-existing.
- **Strategy classes have clean dependency injection** — Math, TaxConfig, no Eloquent reaches across module boundaries (each strategy only queries its own module's models: DCPension, SavingsAccount, InvestmentAccount, Holding, FamilyMember, PensionInputHistory).
- **Two of five suggestions (S-2, S-4) are already tracked in CSJTODO.** Recommendation: bundle S-1 + S-2 + S-4 into a single ~30-min tech-debt sweep before Phase 5 starts.
- **Calculator file size dropped 81%** (1301 → 250 lines). Next-largest file in the new Strategies namespace is `AssetShiftingBundleStrategy.php` at 163 lines — well under the 500-line guideline. The S-1 refactor goal of "no file pushed past 1500 lines as Phase 4 lands" is not just met but exceeded.

---
*Generated by tech-debt-session skill*
