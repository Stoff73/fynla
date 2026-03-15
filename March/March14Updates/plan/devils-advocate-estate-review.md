# Devil's Advocate Review: Estate Planning Implementation Plan

> **Date:** 2026-03-14
> **Reviewed:** `implementation-plan-estate-planning.md`
> **Method:** Direct file inspection of all referenced services, models, migrations, seeders, Vue components, and Vuex store modules

---

## HIGH SEVERITY (5) — Block Implementation As Written

### 1. Growth Rate Migration Describes Wrong Constants & Wrong Mechanism

**What the plan says:** Replace hardcoded `4.7%` constant `DEFAULT_INVESTMENT_GROWTH_RATE` in `IHTCalculationService` and `LifeCoverCalculator`. Add `getGrowthRateForRisk()` to `TaxConfigService`.

**What the code shows:**
- `IHTCalculationService` has NO constant named `DEFAULT_INVESTMENT_GROWTH_RATE`. It has a `getFallbackGrowthRate()` method that already defers to `AssumptionsService::getEstateAssumptions()` first — 4.7% is only the last-resort fallback.
- `AssumptionsService` already does exactly what the plan proposes — it calls `RiskPreferenceService::getMainRiskLevel($userId)` and derives risk-based returns. Adding `getGrowthRateForRisk()` to `TaxConfigService` would duplicate this.
- The access pattern `$user->riskProfile->risk_level` throughout the plan is invalid — `User` has no `riskProfile` relationship. Must use `RiskPreferenceService::getMainRiskLevel($userId)`.
- The real unguarded hardcode is `LifePolicyStrategyService::INVESTMENT_RETURN_RATE = 0.047` — this service has no constructor injection at all.

**Recommendation:** Rewrite Phase 0.4. Do NOT add `getGrowthRateForRisk()` to TaxConfigService. Fix `LifePolicyStrategyService` by adding `AssumptionsService` constructor injection. Replace all `$user->riskProfile->risk_level` with `RiskPreferenceService::getMainRiskLevel($user->id)`.

---

### 2. IncomeService / income_profile Does Not Exist

**What the plan says:** Replace surplus income calculation with `$this->incomeService->getSurplusIncome($userId)` or `$user->income_profile->surplus_income`.

**What the code shows:** No `IncomeService` class exists anywhere in `app/`. No `income_profile` model or relationship exists on `User`. These calls will cause fatal errors.

Both `GiftingStrategyOptimizer` and `PersonalizedGiftingStrategyService` calculate surplus by reading User model income fields directly. The `ResolvesIncome` and `ResolvesExpenditure` traits exist in the codebase and provide exactly the standardised mechanism needed.

**Recommendation:** Replace all references to `incomeService` and `income_profile` with: add `ResolvesIncome` and `ResolvesExpenditure` traits to both gifting services. Use `$this->resolveGrossAnnualIncome($user)` and `$this->resolveMonthlyExpenditure($user)`.

---

### 3. EstateOnboardingFlow.php Missed + generateRecommendations() Unprotected

**What the plan says:** Remove `ESTIMATED_PROPERTY_VALUE`, `ESTIMATED_INVESTMENT_VALUE`, etc. from `EstateDefaults`. Add readiness gate to `analyze()`.

**What the code shows:**
- `EstateOnboardingFlow.php` uses all four estimated value constants in `calculateEstimatedEstateValue()` — not in the plan's file list. Removing constants breaks onboarding.
- `EstateAgent::generateRecommendations()` uses `DEFAULT_LIFE_EXPECTANCY` at line 238 as a fallback — called separately from `analyze()`, so the readiness gate doesn't protect it.
- Method parameter defaults in `step4AnnualGiftingStrategy()` and `step6PETGiftingStrategy()` use these constants as default values.

**Recommendation:** Add `EstateOnboardingFlow.php` to the modification list (either source estimates from TaxConfigService or remove the estimation). Apply readiness gate to `generateRecommendations()` as well. Replace method parameter defaults.

---

### 4. joint_life Field Does Not Exist — Requires Migration

**What the plan says:** Check `$policy->joint_life` in `assessExistingPolicies()`.

**What the code shows:** The `life_insurance_policies` table has no `joint_life` column. The model has no such field. Accessing `$policy->joint_life` returns `null` silently in Laravel, meaning `!$policy->joint_life` is always `true` — every policy flagged as non-joint, generating spurious warnings for married users with existing joint policies.

`$policy->in_trust` and `$policy->policy_type` both exist and work correctly.

**Recommendation:** Add a migration creating `joint_life` boolean on `life_insurance_policies`. Add to model `$fillable` and `$casts`. Add UI form field. The plan says "Files created: 0" for Phase 4 — this is wrong.

---

### 5. Readiness Gate Breaks Frontend Unconditionally

**What the plan says:** `analyze()` returns early with `['readiness' => $readiness, 'can_proceed' => false]`.

**What the code shows:**
- `EstateDashboard.vue` has three states: loading, error, render tabs. No handler for `can_proceed = false`.
- The Vuex store's `analyseEstate` action commits `setAnalysis(response.data)` — the unexpected shape sets `state.analysis` to `{readiness: {...}, can_proceed: false}`.
- All four tab components (`IHTPlanning`, `GiftingStrategy`, `LifePolicyStrategy`, `TrustPlanning`) render unconditionally and will access missing keys, causing blank renders or JS errors.
- `MissingDataAlert.vue` handles only 7 data types and routes everything to `/profile` regardless — insufficient for 12 checks across three severity levels.
- The `analyze()` endpoint is separate from `index()` — users see raw data loaded but broken analysis tabs.

**Recommendation:** Include `can_proceed` within the normal response envelope. Update `EstateDashboard.vue` to check `can_proceed` and render `MissingDataAlert` instead of tabs. Expand `MissingDataAlert.vue` substantially before deploying the gate.

---

## MEDIUM SEVERITY (2)

### 6. PET/CLT NRB Deduction — Double-Deduction Risk on Spouse Side

`SpouseNRBTrackerService` already deducts PETs from the deceased spouse's NRB. The plan says to deduct PETs/CLTs "for each person" in `IHTCalculationService` — if this includes the spouse, the spouse's gifts are counted twice (once in `SpouseNRBTrackerService`, once in the new code).

Additionally, `SpouseNRBTrackerService` currently only filters `gift_type = 'pet'` — CLTs are excluded. This is a separate gap.

**Recommendation:** Phase 2.1 must only deduct the primary user's own gifts. Separately extend `SpouseNRBTrackerService` to also cover CLTs. Document which service handles which person.

### 7. 14-Year Rule Seeder Describes Opposite Direction to Plan's Code

The seeder's `fourteen_year_rule` config defines: "failed PETs reduce NRB for CLTs." The plan's code implements: "historical CLTs reduce NRB for PETs." These are opposite directions of the same rule.

**Recommendation:** Clarify which direction is being implemented. Do not reference `getFourteenYearRule()` config for the PET-focused code — the data structure doesn't map to it.

---

## LOW SEVERITY (1)

### 8. Notification Date Comparison & Scale

Use `today()->subYears(7)` not `now()->subYears(7)` when comparing against `gift_date` (date column, not datetime). Add `User::chunk(100, ...)` to the `SendEstateAlerts` command. Specify `return ['database']` in new notification `via()` methods to match the established pattern.

---

## Summary

| # | Severity | Finding | Amendment |
|---|----------|---------|-----------|
| 1 | High | Wrong constant names; AssumptionsService already exists; $user->riskProfile invalid | Rewrite Phase 0.4 |
| 2 | High | IncomeService does not exist | Use ResolvesIncome trait |
| 3 | High | EstateOnboardingFlow.php missed; generateRecommendations() unguarded | Add to file list |
| 4 | High | joint_life column missing from DB | Add migration |
| 5 | High | Readiness gate breaks frontend | Redesign response shape |
| 6 | Medium | Double-deduction risk on spouse NRB | Clarify scope |
| 7 | Medium | 14-year rule direction mismatch | Clarify implementation |
| 8 | Low | Date comparison + command scale | Minor fixes |

## What the Plan Gets Right

1. The estate module IS mature (22 services, 32+ components) — targeted enhancements are the right approach
2. 2027 pension IHT amendment is correctly identified as missing and important
3. RNRB direct descendant clarification (niece/nephew exclusion) is correct
4. Trust NRB avoidance forward projection is a genuinely new and valuable feature
5. Liquidity reclassification (investments → semi-liquid, pensions → illiquid) is correct
6. EstateDefaults removal philosophy is right — the specific implementation needs fixing
7. Phase ordering and dependency graph are correct
