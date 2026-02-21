# Savings Module Fixes - Deployment Notes

**Date:** 21 February 2026
**Branch:** `saving`
**Status:** DEPLOYED 21 February 2026

---

## Changes Summary

| # | Fix | What Changed |
|---|-----|-------------|
| 1 | Market benchmark rates to database | `RateComparator` now reads rates from `savings_market_rates` table instead of hardcoded 2024/25 array. Rates updatable via seeder without code deploy. |
| 2 | SavingsGoal legacy model deprecation | `@deprecated` annotations on model and controller endpoints. Migration banner in `SavingsGoals.vue` directing users to the Goals module. |
| 3 | CashFlowCoordinator real surplus calculation | Replaced hardcoded £1,000 surplus and £4,500/£3,200 chart values with real calculations from user income, expenditure, and committed contributions. |
| 4 | Account number format validation | `StoreSavingsAccountRequest` and `UpdateSavingsAccountRequest` now validate account numbers (UK 8-digit or international alphanumeric 4-20 chars) before encryption. |
| 5 | ISA subscription auto-calculation | `ISATracker` now projects ISA subscriptions from regular contributions. Frontend shows projected amounts in `SaveAccountModal.vue` and `ISAAllowanceTracker.vue`. |
| 6 | Expenditure source standardisation | New `ResolvesExpenditure` trait extracts the fallback chain (ExpenditureProfile -> User.monthly_expenditure -> User.annual_expenditure/12) with source tracking. Applied to `SavingsAgent` and `CashFlowCoordinator`. |
| 7 | Soft deletes on savings tables | `SavingsAccount` and `SavingsGoal` now use `SoftDeletes`. Deletes are recoverable. |

---

## Files to Upload

### New PHP Files

```
app/Models/SavingsMarketRate.php
app/Traits/ResolvesExpenditure.php
database/migrations/2026_02_21_120000_add_soft_deletes_to_savings_tables.php
database/migrations/2026_02_21_120001_create_savings_market_rates_table.php
database/seeders/SavingsMarketRatesSeeder.php
```

### Modified PHP Files

```
app/Agents/SavingsAgent.php
app/Http/Controllers/Api/SavingsController.php
app/Http/Requests/Savings/StoreSavingsAccountRequest.php
app/Http/Requests/Savings/UpdateSavingsAccountRequest.php
app/Models/SavingsAccount.php
app/Models/SavingsGoal.php
app/Services/Coordination/CashFlowCoordinator.php
app/Services/Savings/ISATracker.php
app/Services/Savings/RateComparator.php
database/seeders/DatabaseSeeder.php
```

### Frontend Files

```
resources/js/components/Savings/ISAAllowanceTracker.vue
resources/js/components/Savings/SaveAccountModal.vue
resources/js/components/Savings/SavingsGoals.vue
```

### Rebuild Required: YES

Three Vue components were changed, so a frontend rebuild is needed.

```bash
./deploy/fynla-org/build.sh
```

Then upload:

```
public/build/
```

---

## Database Migrations Required: YES

Two migrations must be run **before** clearing caches:

```bash
php artisan migrate --force
```

This will:
1. Add `deleted_at` column to `savings_accounts` and `savings_goals` tables
2. Create the `savings_market_rates` table

Then seed the market rates:

```bash
php artisan db:seed --class=SavingsMarketRatesSeeder --force
```

---

## Post-Upload Commands

Run in order:

```bash
php artisan migrate --force
php artisan db:seed --class=SavingsMarketRatesSeeder --force
php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan optimize
```

---

## To Update Market Rates in Future

Edit `database/seeders/SavingsMarketRatesSeeder.php` with new rate values, then run:

```bash
php artisan db:seed --class=SavingsMarketRatesSeeder --force
```

No code deploy needed -- the seeder uses `updateOrCreate` on the composite key.
