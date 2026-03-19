# 15 — Data Readiness & Prerequisite Gate Overhaul

**Branch: dataReadiness (in progress — not yet merged)**
**Date:** 19 March 2026 (session 2)

## Summary

Refactored PrerequisiteGateService to delegate to the actual DataReadiness services instead of duplicating blocking checks. Enriched the completeness endpoint with field-level detail. Updated the AI prompt context to show specific blocking reasons.

## Changes

### PrerequisiteGateService.php
- Added constructor injection for 5 DataReadiness services (Protection, Savings, Retirement, Investment, Estate)
- 5 module gate methods (canAnalyseProtection/Savings/Retirement/Investment/Estate) now call `DataReadinessService::assess()` and pass result to `delegateToReadiness()`
- `delegateToReadiness()`: converts assessment to gate response, only includes FAILED blocking checks
- `normaliseAssessment()`: handles inconsistent key names across services (some use `total_checks`, others use `checks` array)
- `assessAll()`: returns full field-level assessment for all 5 modules
- `buildCompletenessContext()`: enriched to show field-level blocking detail + completion percentages
- Removed duplicated `hasExpenditure()` helper (now handled by DataReadiness services)
- Kept `calculateTotalIncome()` (still used by `canAnalyseTax`)
- Goals and tax gates unchanged (no DataReadiness service for these)

### LifeStageController.php
- `buildModuleCompleteness()`: enriched response with `completeness_percent`, `blocking`, `warnings`, `total_checks`, `passed_checks` per module from DataReadiness assessments
- Removed duplicated `calculateTotalIncome()` method

### completeness.js (frontend store)
- New getters: `moduleCompleteness(module)`, `moduleBlocking(module)`, `moduleWarnings(module)`, `overallCompleteness`
- Blocking/warning getters filter to only failed checks

## Files Changed

| File | Lines Changed |
|------|--------------|
| `app/Services/PrerequisiteGateService.php` | Major refactor |
| `app/Http/Controllers/Api/LifeStageController.php` | Enriched completeness |
| `resources/js/store/modules/completeness.js` | +30 (new getters) |

## Still To Do
- Journey progress calculation using data completeness
- Pest tests for refactored service
- PR and merge
