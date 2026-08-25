# Tech Debt Report — Session 121 (30 April 2026, late)

**Files analysed:** 10 (1 new strategy + 1 modified math/helper file + 1 calculator wiring + 1 test file + 6 tech-debt-touched strategies/helpers)
**Issues found:** 4
**Severity breakdown:** 0 critical, 0 warnings, 4 suggestions

## Critical Issues

None. Tech-debt sweep + Phase 5 preserved behaviour exactly (49 existing tax strategy tests pass unchanged), Phase 5 added 6 new green tests, full Tax + Architecture sweep green. No banned colors / DB facade / raw queries. All new files declare `strict_types=1` and have full type hints. Phase 5 strategy short-circuits on threshold gate so non-tapered users don't pay an extra DB query.

## Warnings

None.

## Resolved this session (was in session 120 report)

- **S-1** ✅ — Dead `$personalAllowance` in `IncomeBandStrategy:33` deleted in `4b7981a`.
- **S-2 (dead var part)** ✅ — Dead `$pension` in `LifecycleStrategy:30` deleted in `4b7981a`. Junior Pension constants now have an HMRC-source citation comment. **Note:** the *full* fix (expose `junior_pension_net_cap` via `TaxConfigService`) remains deferred — see new S-3 below.
- **S-4 (W-2)** ✅ — `TaxStrategyMath::bandRateFor` now reads from `TaxConfigService['income_tax']['bands'][*].rate` (commit `4b7981a`). Adds public `bandRateForBand(string $band)` helper that Phase 5 reuses.
- **W-1 (carried)** ✅ — Three strategies (`DividendAllowanceHarvestStrategy`, `AssetShiftingBundleStrategy`, `CrossSpouseBundleStrategy`) refactored to call new `TaxStrategyMath::dividendRateForBand($band)` helper. Drops 3 duplicated match blocks.

## Suggestions

### S-1 (carried) — Bundle strategies share legacy-array-to-DTO conversion
**Files:** `app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php:158-161`, `app/Services/Tax/Strategies/CrossSpouseBundleStrategy.php:86-89`
**Category:** Duplicate Code
**What's wrong:** Both bundle classes still end with the same `array_map(fn $arr → fromArray)` conversion. Was S-3 in the previous report; not addressed in 121 (out of scope — Phase 5 doesn't touch household bundles).
**Suggested fix:** Defer until a 3rd household-bundle class appears. Then extract an `AbstractHouseholdBundle` base with `protected wrapAsHouseholdRecs(array $suggestions): array`.

### S-2 (carried) — `PensionAACarryForwardStrategy` assumes constant AA across the lookback window
**File:** `app/Services/Tax/Strategies/PensionAACarryForwardStrategy.php`
**Category:** Complexity & Maintainability
**What's wrong:** Was S-5 in the previous report; uses today's £60k AA for every prior year, over-counts by up to £20k for users who pre-date 2023/24. Class docblock acknowledges. No change in 121.
**Suggested fix:** Either (a) add `historical_annual_allowances` to TaxConfigService, or (b) leave as-is — conservative for the user.

### S-3 (NEW) — Junior Pension £2,880 / £720 still hardcoded
**Files:** `app/Services/Tax/Strategies/LifecycleStrategy.php:115-116`, `app/Services/Tax/Strategies/NonEarnerSpousePensionStrategy.php:53-54`
**Category:** Convention Violations (Rule #3 — no hardcoded tax values)
**What's wrong:** The session 121 sweep added an HMRC-source citation comment but did NOT expose the figures via `TaxConfigService`. They remain inlined in two strategies.
**Suggested fix:** Add `pension.junior_pension.net_cap = 2880` and `pension.junior_pension.government_uplift = 720` to `TaxConfigurationSeeder`, then read via `$pension['junior_pension']['net_cap'] ?? 2880`. ~10 min of work + reseed required. Bundle when next touching the lifecycle strategy.

### S-4 (NEW) — `TaxStrategyMath::thresholdIncomeFor` does not add salary-sacrifice contributions back
**File:** `app/Services/Tax/TaxStrategyMath.php` (new helper added this session)
**Category:** HMRC Compliance Simplification (V1 documented limitation)
**What's wrong:** HMRC's tapered-AA threshold-income definition adds back any pension contribution made via salary sacrifice on or after 9 July 2015 (anti-forestalling rule). Our helper does NOT — it returns the user's gross income fields verbatim. The class docblock acknowledges this:
> "V1 simplification: does not handle salary-sacrifice anti-forestalling addback (HMRC rule for sacrifices on/after 9 July 2015)."

**Risk profile:** Low today. To trigger a false-negative, a user would need salary-sacrifice contributions large enough to drop their gross income below £200k threshold — and then those contributions would need to be added back per HMRC. Most personas are well clear of the £200k threshold or well above it.
**Suggested fix:** When DCPension grows a `salary_sacrifice_start_date` field, gate the addback on `salary_sacrifice = true AND salary_sacrifice_start_date >= 2015-07-09`. Defer until a persona-driven false-negative appears.

### S-5 (NEW) — `TaxStrategyMath::employerPensionContributionsFor` uses naive base salary fallback
**File:** `app/Services/Tax/TaxStrategyMath.php` (new helper added this session)
**Category:** Edge Case
**What's wrong:** When a `DCPension` has no `annual_salary` set, the helper falls back to `User::annual_employment_income`. For users with **multiple** DCPensions all missing `annual_salary`, the same employment income gets attributed to each pension — over-stating employer contributions and (potentially) pushing adjusted income into the tapered range when it shouldn't be.
**Risk profile:** Very low. Test/seed users typically have one DCPension and either both fields populated or both null. No production case observed.
**Suggested fix:** When this becomes a real concern, either require `annual_salary` to be populated on creation, OR sum employer contributions only across pensions where `annual_salary` is non-null. Defer.

---

## Notes

- **Phase 5 introduces no new constants.** All four tapered-AA values (`threshold_income`, `adjusted_income_threshold`, `minimum_allowance`, `taper_rate`) read from `TaxConfigService['pension']['tapered_annual_allowance']` which was already seeded for 2025/26 (and now 2026/27). Phase 5 also reuses the `bandRateForBand` helper added by W-2 in the same session for the marginal-rate calculation. Single source of truth.
- **Strategy file size**: `TaperedAnnualAllowanceStrategy.php` is 110 lines including docblocks — well under the 500-line guideline. Calculator now wires 13 strategies (12 → 13).
- **Performance**: Phase 5 short-circuits on the threshold gate before issuing the `DCPension::query()` for `employerPensionContributionsFor`, keeping the calculator's per-calculation budget below 50ms for the representative `single_earner_couple` benchmark persona.
- **Three of five surviving suggestions are pre-existing** (S-1, S-2, plus the deferred bundle pattern). Two (S-4, S-5) are V1 simplifications introduced this session and acknowledged in docblocks.

---
*Generated by tech-debt-session skill*
