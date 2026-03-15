# Devil's Advocate Review: Protection Decision Engine Implementation Plan

**Date:** 2026-03-14
**Reviewed:** `implementation-plan-protection.md`
**Method:** Direct file inspection of all referenced services, models, tests, Vue components, and store modules

---

## CRITICAL (2)

### Critical 1: AssumptionsService for Withdrawal Rate — Wrong Method, Wrong Key, Wrong Domain

**What the plan says:** Replace 4.7% with `$this->assumptionsService->getEstateAssumptions($userId)['return_rate']`

**What the code shows:**
- `CoverageGapAnalyzer::calculateHumanCapital(float $annualIncomeNeed)` has NO `$userId` in scope — cannot call AssumptionsService
- `AssumptionsService::getEstateAssumptions()` takes `User $user`, not `$userId`
- The return array contains `inflation_rate`, `property_growth_rate`, `investment_growth_method`, `custom_investment_rate`, `has_overrides` — **there is NO `return_rate` key**
- 4.7% is a sustainable withdrawal rate (actuarial concept), NOT an investment return rate. A high-risk 25-year-old with 9% return assumption would produce a wildly incorrect (too low) life cover need
- `ScenarioBuilder.php` line 20 has a separate 3% rate with the same problem

**Recommendation:** Move both rates to `TaxConfigService` as `protection.withdrawal_rates.human_capital` (4.7%) and `protection.withdrawal_rates.scenario` (3%). Pass configured rate into methods at the call site. Do NOT inject AssumptionsService.

### Critical 2: Action Definition Expansion — Evaluator Methods Entirely Absent

**What the plan says:** Expand seeder from 8 to ~25 triggers.

**What the code shows:** `ProtectionActionDefinitionService` has exactly 6 evaluator conditions in its match block:
```
gap_exists, strategy_recommendation, policies_exist_with_gaps,
multiple_policies, profile_missing, no_policies_with_gaps
```

All 17 new trigger conditions (`dis_reliance_warning`, `policy_not_in_trust`, `self_employed_no_ip`, etc.) will hit `default => null` — producing silent no-ops. No error, no exception, no log entry.

**Recommendation:** Phase 3 must include: (1) ~17 new private evaluator methods, (2) ~17 new branches in the match statement, (3) unit tests for each evaluator. The seeder alone is cosmetic without this.

---

## HIGH (2)

### High 1: Constructor Injection Breaks Tests

Tests directly instantiate `RecommendationEngine` and `ScenarioBuilder` with zero arguments:
- `new RecommendationEngine;` (RecommendationEngineTest.php)
- `new ScenarioBuilder;` (ScenarioBuilderTest.php)

Adding required constructor parameters breaks all existing tests immediately.

**Recommendation:** Add test updates to Phase 0 scope explicitly. Mock new dependencies in test setUp().

### High 2: State Benefits SSP Formula Is Wrong

The plan calculates `$monthlyStateSupport_ssp = ($sspWeekly * 52) / 12`. SSP is paid for a MAXIMUM of 28 weeks, not 52. This annualises SSP as if paid indefinitely, overstating state support and understating the protection gap.

Additional issues:
- No employment status check (self-employed get no SSP)
- ESA eligibility not determinable from stored data
- SSP rate conflict: seeder uses £116.75, frontend hardcodes £118.75

**Recommendation:** Use `$sspWeekly * $maxWeeks` (28 weeks total, not annualised). Check `User.employment_status` before applying SSP. Resolve the rate conflict.

---

## MEDIUM (4)

### Medium 1: Employer Benefits Creates Third Income Source
Adding employer fields to `protection_profiles` creates a third source for employment data (alongside `User.annual_employment_income` and `ProtectionProfile.annual_income`). Death-in-service formula using `profile->annual_income` will be wrong if User income was updated but profile wasn't.

**Recommendation:** Consider a separate `employer_benefits` table, or at minimum use `User.annual_employment_income` directly for death-in-service calculation.

### Medium 2: Score Removal Targets Wrong Component
`CoverageAdequacyGauge.vue` already shows text ("Adequate"/"Partial"/"Limited"/"Insufficient"), not numbers. The real Rule #13 violation is in `ComprehensiveProtectionPlanService::generateExecutiveSummary()` which puts `'overall': 75` into the API response.

**Recommendation:** Target ComprehensiveProtectionPlanService, not the gauge component.

### Medium 3: Frontend Gap Multipliers Differ from Backend
Frontend uses 75% income / 2x CI. Backend uses 60% income / 3x CI. Replacing frontend calculations with backend values silently changes displayed numbers.

**Recommendation:** Standardise multipliers in TaxConfigService first, then remove frontend calculations.

### Medium 4: Readiness Gate — Vuex Store Not Handling success:false
Removing auto-create means `analyseProtection` can return `{success: false}`. The Vuex store commits this as analysis state. Components reading `state.analysis.data.gaps` get `undefined`.

**Recommendation:** Update Vuex store to handle `success: false`. Update `ProtectionProfileResource` for null safety.

---

## Summary

| # | Severity | Finding |
|---|----------|---------|
| C1 | Critical | AssumptionsService wrong method/key/domain — will not work |
| C2 | Critical | 17 new trigger evaluator methods missing — seeder alone produces no-ops |
| H1 | High | Constructor changes break existing tests |
| H2 | High | SSP formula annualises 28-week benefit over 52 weeks |
| M1 | Medium | Employer benefits creates third income source |
| M2 | Medium | Score violation is in ComprehensiveProtectionPlanService, not gauge |
| M3 | Medium | Frontend/backend multipliers differ |
| M4 | Medium | Vuex store doesn't handle readiness gate response |
