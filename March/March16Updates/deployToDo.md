# Deploy: Outstanding Tasks (March 16)

**Date:** 2026-03-16
**Branch:** `toDo`

---

## Pre-Deployment

### 1. Build locally
```bash
./deploy/fynla-org/build.sh
```

### 2. Run migration on server
```bash
php artisan migrate
```

This adds 1 column:
- `rate_valid_until` (nullable date) on `savings_accounts` table

### 3. Seed
```bash
php artisan db:seed --force
```

### 4. Clear caches
```bash
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Files to Upload

### New PHP Files (1)

```
database/migrations/2026_03_16_300001_add_rate_valid_until_to_savings_accounts_table.php
```

### Modified PHP Files (4)

```
app/Http/Controllers/Api/Plans/PlanController.php          — added SavingsPlanService to constructor, getPlanService(), statuses()
app/Models/SavingsAccount.php                              — added rate_valid_until to fillable + casts
app/Services/Dashboard/DashboardAggregator.php             — wired all 5 agents (Protection, Savings, Investment, Retirement, Estate) with real data
app/Services/Retirement/RetirementDataReadinessService.php — income check downgraded to warning for already-retired users
```

### Test Files (not deployed)

```
tests/Integration/DashboardIntegrationTest.php             — updated alert tests for real agent data
```

### Built Assets

```
public/build/                                              — entire directory (built by ./deploy/fynla-org/build.sh)
```

---

## Changes

### 1. Savings Wired into PlanController

The `SavingsPlanService` was not registered in `PlanController`. The Actions Dashboard skipped savings because `/api/plans/savings` returned 404.

**Fixed:**
- Added `SavingsPlanService` to constructor injection
- Added `'savings'` to `getPlanService()` match statement
- Added savings to `statuses()` method for plan readiness checks

### 2. rate_valid_until Column Added

The `savings:send-alerts` artisan command referenced a `rate_valid_until` column that didn't exist on `savings_accounts`.

**Fixed:**
- Created migration adding nullable date column
- Added to SavingsAccount model `$fillable` and `$casts`

### 3. DashboardAggregator Wired to Real Agents

All `get*Summary()`, `get*Score()`, and `get*Alerts()` methods returned hardcoded stub data for every user. Dashboard cards showed identical values regardless of persona.

**Fixed — summary methods now return real user-specific data:**

| Module | Data Source | Key Fields |
|--------|------------|------------|
| Protection | ProtectionAgent | adequacy score, total coverage, premium total, gap count |
| Savings | SavingsAgent | total savings, emergency fund runway, ISA usage %, goals on track |
| Investment | InvestmentAgent | portfolio value, YTD return, holdings count, rebalancing status |
| Retirement | RetirementAgent | projected income, target income, income gap, years to retirement |
| Estate | EstateAgent | net worth, IHT liability, effective tax rate |

**Score methods derive from real analysis data.** Alert methods generate real alerts based on gaps, thresholds, and opportunities. All agent calls wrapped in try/catch with sensible defaults on failure.

### 4. Retired Couple Retirement Analysis Fixed

Patricia & Harold Bennett (retired_couple) retirement analysis returned zeros because the `RetirementDataReadinessService` blocked analysis when no employment income was recorded — correct for working-age users but wrong for already-retired users.

**Fixed:**
- Income check downgraded from `blocking` to `warning` when `current_age >= target_retirement_age`
- Message updated to explain pension income will be used instead
- Patricia now shows: DB pension £18,500 + State pension £11,500 = £30,000 projected income

---

## Post-Deployment Verification

1. `/api/plans/savings` returns data (was 404)
2. Plan statuses include `savings` key
3. Dashboard cards show different values per persona (not identical stubs)
4. David Mitchell dashboard: savings £102k, investments £220k, retirement £46k projected
5. Patricia Bennett retirement: projected income £18,500 (DB) + £11,500 (state after SPA)
6. `php artisan savings:send-alerts` runs without column error

---

## Summary

| Metric | Count |
|--------|-------|
| New migration | 1 |
| Modified PHP files | 4 |
| Modified test files | 1 |
| **Total files changed** | **6** |
