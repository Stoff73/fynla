# FinancialHealthScore Feature Removal

**Date:** 19 March 2026
**Branch:** `main`
**Commit:** `6c96214`

## Summary

Removed the entire FinancialHealthScore feature — component, store, service, controller, route, and tests. This feature was marked DEPRECATED, never imported in any parent component, and violated Rule 13 (No Scores in User-Facing UI).

## Rationale

- The `FinancialHealthScore.vue` component was never rendered — no parent imported or used it
- Rule 13 prohibits numerical scores in user-facing UI (oversimplifies complex financial positions)
- The backend endpoint and store still existed, making unnecessary API calls

## Files Deleted

- `resources/js/components/Dashboard/FinancialHealthScore.vue` — unused component (259 lines)
- `tests/frontend/components/Dashboard/FinancialHealthScore.test.js` — frontend test (385 lines)

## Files Modified

### Frontend
- `resources/js/store/modules/dashboard.js` — removed `financialHealthScore` state, getter, action (`fetchFinancialHealthScore`), mutation (`setFinancialHealthScore`), and references in `fetchAllDashboardData` and `SET_PREVIEW_MODE`
- `resources/js/services/dashboardService.js` — removed `getFinancialHealthScore()` method and from `fetchAllDashboardData()` parallel promises
- `resources/js/store/modules/retirement.js` — removed `retirementReadinessScore` getter (only used by deleted component)

### Backend
- `app/Http/Controllers/Api/DashboardController.php` — removed `financialHealthScore()` action and cache key from `invalidateCache()`
- `app/Services/Dashboard/DashboardAggregator.php` — removed `calculateFinancialHealthScore()` method + 7 private helpers (`getProtectionScore`, `getEmergencyFundScore`, `getRetirementScore`, `getInvestmentScore`, `getEstateScore`, `getHealthLabel`, `getHealthRecommendation`)
- `routes/api.php` — removed `/api/dashboard/financial-health-score` route

### Tests
- `tests/Integration/DashboardIntegrationTest.php` — removed 12 FHS-related tests
- `tests/Feature/Dashboard/DashboardApiTest.php` — removed 6 FHS-related tests
- `tests/Feature/CrossModuleIntegrationTest.php` — removed `test_financial_health_score_aggregates_all_module_scores`

## Impact

- Net deletion: ~1,250 lines
- Dashboard API no longer calls `/financial-health-score` endpoint
- `HolisticPlanner::calculateOverallScore()` retained — private internal method, not user-facing
