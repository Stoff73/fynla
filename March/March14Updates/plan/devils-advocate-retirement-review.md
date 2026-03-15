# Devil's Advocate Review: Retirement Implementation Plan

> **Date:** 2026-03-14
> **Reviewed:** `implementation-plan-retirement.md`
> **Method:** Direct file inspection of all referenced services, models, seeders, and Vue components

---

## CRITICAL (4)

### 1. Withdrawal Rate Scope Is 3x Larger Than Claimed — 16 Locations Not 6

`RetirementStrategyService.php` (not listed in plan's Files Modified table) contains **9 additional** inline `0.047` literals at lines 855, 880, 901, 1588, 1669, 1833, 1895, 1900, 2007. Plus `DecumulationPlanner.php` line 139 (`0.04` in PCLS calc) and `ContributionOptimizer.php` line 143 (`0.04` inline). True count: **16+ locations across 7 files**, not 6 across 5.

**Recommendation:** Add `RetirementStrategyService` to Files Modified. Run grep before coding. Create architecture test post-migration.

### 2. `current_spa` Key Does Not Exist in Seeder — Null Arithmetic

The seeder's `pension.state_pension` section contains only `full_new_state_pension`, `qualifying_years`, `minimum_qualifying_years`. There is no `current_spa` key. The plan's fallback `['state_pension']['current_spa']` returns null, making `years_to_retirement = max(0, null - age) = max(0, -age) = 0` for all users. Silent wrong projections.

`StatePension` model stores `state_pension_age` per user — this should take priority over a global SPA.

**Recommendation:** Add `current_spa` to all 5 tax year configs in the seeder BEFORE any service references it. Clarify priority: (1) StatePension model per user, (2) TaxConfigService SPA, (3) never hardcode.

### 3. smoker_status / health_status Are on ProtectionProfile, Not User

The plan says `$user->smoker_status`. This field does not exist on the User model — it's on `ProtectionProfile`. `$user->smoker_status` returns null silently. Enhanced annuity rates never apply.

**Recommendation:** Use `$user->protectionProfile?->smoker_status`. Eager-load `protectionProfile`. Add unit test asserting enhanced rates apply when true.

### 4. £2,000 Salary Sacrifice Floor Is Not UK Law

HMRC EIM42750 says salary sacrifice cannot reduce pay below NMW/NLW. For 2025/26, NLW (21+) is £12.21/hour ≈ £23,800/year. £2,000 is not a statutory figure. Encoding it as a legal floor will approve arrangements that breach NMW.

**Recommendation:** Either compute NMW floor from contracted hours, or use auto-enrolment earnings trigger (£10,000) as conservative proxy. Do not encode £2,000 as a threshold.

---

## IMPORTANT (5)

### 5. ContributionOptimizer Already Has TaxConfigService
Already injected. Plan's wording may cause confusion — clarify that only `RiskPreferenceService` needs adding.

### 6. RetirementIncomeService DEFAULT_GROWTH_RATE = 0.04 Missing from Growth Rate Table
Line 35, used for ISA/GIA/bond projections. Not listed in Phase 0.5.

### 7. PensionProjector Has No SUSTAINABLE_WITHDRAWAL_RATE
Plan conflates two files. The 0.04 at line 195 is inline, not a named constant. Also `DEFAULT_GROWTH_RATE = 0.05` fallback not listed in growth rate table.

### 8. RetirementIncomeService DEFAULT_STATE_PENSION_AGE = 67 Missing from Age Table
Three usages (lines 578, 581, 590) controlling state pension income phasing. Depends on Issue #2's `current_spa` key.

### 9. Readiness Gate Creates retirementReadinessScore = 100 False Positive
When analysis is null, `incomeGap = 0 - 0 = 0`, score = `100 - (0/500) = 100`. First-time users with no data get "perfect" retirement score on dashboard.

---

## Summary

| # | Severity | Finding |
|---|----------|---------|
| 1 | Critical | 16 withdrawal rate locations, not 6 — RetirementStrategyService omitted |
| 2 | Critical | `current_spa` key missing from seeder — null arithmetic |
| 3 | Critical | smoker/health on ProtectionProfile not User — always null |
| 4 | Critical | £2,000 is not a UK statutory floor — NMW/NLW required |
| 5 | Important | ContributionOptimizer already has TaxConfigService |
| 6 | Important | RetirementIncomeService growth rate constant missing from table |
| 7 | Important | PensionProjector conflated with RetirementProjectionService |
| 8 | Important | DEFAULT_STATE_PENSION_AGE missing from age migration |
| 9 | Minor | Readiness gate false-positive score of 100 |
