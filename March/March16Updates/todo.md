# March 16 — Outstanding Tasks

Carried forward from March 15 session.
**Status:** ALL COMPLETED AND DEPLOYED TO PRODUCTION — 16 March 2026

---

## Priority 1: Backend Fixes

### Wire Savings into PlanController
The `SavingsPlanService` exists but isn't registered as a plan type in `PlanController.php`. The Actions Dashboard skips savings because `/api/plans/savings` returns a 404. Need to:
- Add `SavingsPlanService` to `PlanController` constructor
- Add `'savings' => $this->savingsPlanService` to the `getPlanService()` match statement
- Add savings to the `statuses()` method
- Test with all personas

**Files:** `app/Http/Controllers/Api/Plans/PlanController.php`

---

### Fix `savings:send-alerts` Missing Column
The `SendSavingsAlerts` command references a `rate_valid_until` column that doesn't exist on the `savings_accounts` table. Need to:
- Create migration to add `rate_valid_until` column to `savings_accounts`
- Or remove the rate expiry alert logic if the column isn't planned

**Files:** `app/Console/Commands/SendSavingsAlerts.php`, `database/migrations/`

---

### Fix Dashboard Aggregator Stub Data
The `DashboardAggregator` `get*Summary()` methods return hardcoded stub data for all users. The dashboard cards show identical values regardless of persona. Need to:
- Wire the summary methods to actual module agents/services
- Return real user-specific data for each module summary

**Files:** `app/Services/Dashboard/DashboardAggregator.php`

---

## Priority 2: Data Quality

### Retired Couple — Retirement Analysis Returns Zeros
The `retired_couple` persona (Patricia & Harold Bennett) retirement analysis returns `projected_income: 0`, `target_income: 0` despite having a Defined Benefit pension and State Pension. The analyzer may be short-circuiting because `years_to_retirement = 0`.

### Estate Assets Empty for Most Personas
Properties and physical assets don't appear in the estate asset list for most personas. The `iht_profile` is null for young_family and peak_earners. May be a seeder gap — personas may need estate asset data.

---

## Priority 3: Testing

### Full Browser Test of All Detail Views
The March 15 session tested top-level pages but didn't click into every individual account, pension, policy, plan, and goal detail view. Need a systematic test of all clickable items across all personas.
