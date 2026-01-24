# Deployment - 24 January 2026

## Summary
1. **NEW**: BTL Management Agent tab — Buy-to-let properties now show a "Management Agent" tab (4th tab) with agent name, company, email, phone, monthly/annual fee, and ownership share calculation. Empty state shown when no agent data exists.
2. **FIX**: Managing agent fee now included in net rental yield calculation (`PropertyService::calculateTotalMonthlyCosts()`)
3. **FIX**: Managing agent fee flows through to User Profile Expenditure tab with correct ownership multiplier
4. **NEW**: Managing agent fee editable in the Financials tab Edit Costs modal (BTL only)
5. **NEW**: Managing agent fee shown as a row in the Financials tab Monthly Costs grid (BTL only)
6. **CLEANUP**: Removed dead `PropertyDetail.vue` component and its unused `/property/:id` route — app only uses `PropertyDetailInline.vue`
7. **FIX**: Expenditure property costs now individually itemised — Gas, Electricity, Water shown separately (not grouped as "Utilities"); Building Insurance and Contents Insurance shown separately (not grouped as "Insurance")
8. **FIX**: Management Agent fee now displays as a named line item in the Expenditure property cost breakdown
9. **BUG FIX**: Spouse expenditure tab now shows correct ownership percentage for joint/TiC properties — was applying primary owner's percentage to both users instead of inverting for the joint owner
10. **BUG FIX**: Spouse expenditure tab now shows all individual property cost breakdown items (gas, electricity, water, building insurance, contents insurance, management agent) — template was still using old grouped keys
11. **UX**: Mortgage tab co-owner dropdown now auto-defaults from the ownership tab selection (spouse or "Other" with name) — previously required re-selection
12. **UX**: Management Agent tab now positioned between Mortgage and Financials; hidden when no agent data exists (fee field remains in Financials as a reminder)
13. **UX**: Financials tab Management Agent Fee field now always visible for BTL properties — shows "Not set" when no fee entered, serving as a reminder
14. **NEW**: `monthly_interest_portion` field added to mortgages table — used for Section 24 tax credit calculation on repayment/mixed BTL mortgages
15. **NEW**: BTL Financials tab restructured — clear cash flow breakdown (income → costs → mortgage → net) separated from tax position (taxable rental income + Section 24 credit)
16. **NEW**: Section 24 Tax Credit calculation — 20% of mortgage interest shown in a separate "For Your Income Tax Calculation" box, with taxable rental income (excl. mortgage costs). These two figures feed into Income & Occupation tab
17. **NEW**: Interest portion field on mortgage form — shown for BTL repayment/mixed mortgages, with helper text explaining Section 24 usage
18. **NEW**: Amber reminder when repayment/mixed mortgage has no interest portion entered
19. **NEW**: Learning Centre — Section 24 educational content added to Tax Planning category (before/after, 3-step process, mortgage type table, tax band impact, worked example)
20. **NEW**: UK Tax & NI card now shows per-property rental income breakdown (property names with individual taxable amounts)
21. **NEW**: Section 24 tax credit applied to income tax calculation — Earned Income card shows Tax Payable subtotal → Section 24 credit → Tax Payable After Credit flow; TaxSummaryCard removed (info now inline)
22. **REFACTOR**: PropertyFinancials tax section now uses API-returned `tax_position` (single source of truth from `PropertyService::calculateTaxPosition()`)
23. **UX**: Monthly Costs section restyled to match Rental Income Analysis format — simple list rows, no grid/cards, totals inline
24. **FIX**: Removed double border lines between last cost item and totals in both Monthly Costs and Rental Income Analysis sections
25. **UX**: Running Costs and Mortgage Payment labels now show ownership percentage for shared properties (e.g. "Less: Running Costs (70%):") for clarity
26. **UX**: Monthly Costs and Rental Income Analysis cards now side-by-side (2-column grid) on BTL properties for better use of space
27. **UX**: Sidebar auto-collapses on property section for more screen width
28. **CLEANUP**: Removed summary cards (Your Rental Income, Net Rental Income, Net Rental Yield) from top of Rental Income Analysis — info already in cash flow breakdown
29. **CLEANUP**: Removed Equity card from property detail header — header now shows Full Property Value, Your Share, Mortgage Balance (+ Net Rental Yield for BTL)
30. **CLEANUP**: Removed Financial Summary section from bottom of Financials tab — duplicated info already in header
31. **CLEANUP**: Removed redundant `<h3>` headings from Management Agent and Financials tabs — tab labels already identify content
32. **UX**: Removed border/outline from Management Agent tab content for cleaner appearance
33. **CLEANUP**: Removed unused questionnaire-based risk profiling system (System 3) — `RiskProfileController`, `RiskProfiler`, `RiskQuestionnaire`, `CapacityForLossAnalyzer`, 6 API routes, and 5 dead frontend methods. App uses only auto-calculator (7-factor) and self-assessment systems.
34. **REWORK**: Capacity for Loss — 4 threshold levels (was 3), detail view shows formula with actual £ values, spectrum updated to 4 zones, factor breakdown recalculated live
35. **UX**: All risk factor detail views — concise with source data, formula-style calculations, compact thresholds
36. **BUG FIX**: Goals module — all modal buttons unclickable due to CSS z-stacking (fixed backdrop intercepting clicks). Added `relative z-10` to modal panels in GoalFormModal, ContributionModal, and delete modal. Also added form validation error messages and changed submit button to `type="submit"`.
37. **BUG FIX**: Goals module — goals with 0% progress incorrectly showing "On track" status. Backend `is_on_track` now returns `false` when `current_amount <= 0`. Frontend adds "Not started" (gray) state distinct from "Behind" (orange) across GoalsOverview, GoalsByModule, and GoalCard.
38. **FIX**: Pension strategies — now show retirement age and years-to-retirement used in calculations. Each strategy description references the growth period. Context banner shows "Retirement age X · Y years of growth". Income target description no longer hardcodes "age 68". Default retirement age changed from 65 to 68 across both services. If user has no DOB, strategies return amber "Date of Birth Required" message instead of calculating.

---

## Frontend Rebuild Required: YES

Vue components and router were modified.

```bash
./deploy/fynla-org/build.sh
```

---

## Files Changed

```
app/Services/Property/PropertyService.php
app/Services/UserProfile/UserProfileService.php
app/Services/UKTaxCalculator.php
app/Models/Mortgage.php
app/Http/Requests/StoreMortgageRequest.php
app/Http/Requests/UpdateMortgageRequest.php
database/migrations/2026_01_24_091552_add_monthly_interest_portion_to_mortgages_table.php (NEW)
resources/js/views/NetWorth/NetWorthDashboard.vue
resources/js/components/NetWorth/Property/PropertyDetailInline.vue
resources/js/components/NetWorth/Property/PropertyFinancials.vue
resources/js/components/NetWorth/Property/PropertyForm.vue
resources/js/components/UserProfile/ExpenditureForm.vue
resources/js/components/UserProfile/IncomeOccupation.vue
resources/js/components/UserProfile/TaxIncomeCard.vue
resources/js/components/UserProfile/TaxSummaryCard.vue
resources/js/views/Public/LearningCentre.vue
resources/js/components/NetWorth/Property/PropertyDetail.vue  (DELETED)
resources/js/router/index.js
routes/api.php
resources/js/services/investmentService.js
app/Http/Controllers/Api/Investment/RiskProfileController.php  (DELETED)
app/Services/Investment/RiskProfile/RiskProfiler.php  (DELETED)
app/Services/Investment/RiskProfile/RiskQuestionnaire.php  (DELETED)
app/Services/Investment/RiskProfile/CapacityForLossAnalyzer.php  (DELETED)
resources/js/components/Goals/GoalFormModal.vue
resources/js/components/Goals/ContributionModal.vue
resources/js/views/Goals/GoalsDashboard.vue
app/Models/Goal.php
app/Services/Goals/GoalProgressService.php
resources/js/components/Goals/GoalsOverview.vue
resources/js/components/Goals/GoalsByModule.vue
resources/js/components/Goals/GoalCard.vue
app/Services/Retirement/RetirementStrategyService.php
app/Services/Retirement/RetirementProjectionService.php
resources/js/components/Retirement/StrategiesTab.vue
resources/js/components/Retirement/StrategyCard.vue
resources/js/components/NetWorth/PensionList.vue
```

---

## Already Uploaded to Production (Items 1-32)

### Backend (PHP)
```
app/Services/Property/PropertyService.php
app/Services/UserProfile/UserProfileService.php
app/Services/UKTaxCalculator.php
app/Models/Mortgage.php
app/Http/Requests/StoreMortgageRequest.php
app/Http/Requests/UpdateMortgageRequest.php
database/migrations/2026_01_24_091552_add_monthly_interest_portion_to_mortgages_table.php
routes/api.php
app/Http/Controllers/Api/UserProfileController.php
```

### Frontend
```
public/build/  (entire folder)
```

### Already Removed from Server
```
resources/js/components/NetWorth/Property/PropertyDetail.vue
```

### Post-Deployment (already run)
```bash
php artisan migrate --force && php artisan cache:clear
```

- Migration adds `monthly_interest_portion` column to `mortgages` table
- Cache clear required so cached property analysis regenerates with the updated cost calculation

---

## Already Uploaded to Production (Items 33-35 — Risk System)

### Backend (PHP)
```
routes/api.php
app/Http/Controllers/Api/RiskPreferenceController.php
app/Services/Risk/AutoRiskCalculator.php
app/Services/Risk/RiskPreferenceService.php
app/Providers/EventServiceProvider.php
app/Jobs/RecalculateRiskProfileJob.php
app/Observers/DCPensionRiskObserver.php
app/Observers/FamilyMemberRiskObserver.php
app/Observers/InvestmentAccountRiskObserver.php
app/Observers/PropertyRiskObserver.php
app/Observers/SavingsAccountRiskObserver.php
app/Observers/UserRiskObserver.php
database/migrations/2026_01_24_134257_make_factor_breakdown_nullable_on_risk_profiles.php
```

### Frontend
```
public/build/  (entire folder)
```

### Already Removed from Server
```
app/Http/Controllers/Api/Investment/RiskProfileController.php
app/Services/Investment/RiskProfile/  (entire folder)
```

### Post-Deployment (already run)
```bash
php artisan migrate --force && php artisan cache:clear
```

- Migration makes `factor_breakdown` column nullable on `risk_profiles` table

---

## To Upload (Items 36-37 — Goals Module)

### Backend (PHP)
```
app/Http/Controllers/Api/GoalsController.php
app/Agents/GoalsAgent.php
app/Agents/BaseAgent.php
app/Services/Goals/GoalAssignmentService.php
app/Services/Goals/GoalAffordabilityService.php
app/Services/Goals/GoalProgressService.php
app/Services/Goals/GoalRiskService.php
app/Http/Requests/Goals/StoreGoalRequest.php
app/Http/Requests/Goals/UpdateGoalRequest.php
app/Models/Goal.php
app/Models/GoalContribution.php
database/migrations/2026_01_24_160001_create_goals_table_v2.php
database/migrations/2026_01_24_160002_create_goal_contributions_table_v2.php
```

### Frontend
```
public/build/  (entire folder)
```

### Post-Deployment
```bash
php artisan migrate --force && php artisan cache:clear
```

- Migrations create `goals` and `goal_contributions` tables
- `BaseAgent.php` required — contains `clearUserCache()` method called by `GoalsAgent::clearCache()`

### Production Bug Fix (during deployment)

`GoalsController.php` was further patched after initial upload:

1. **`catch (\Throwable $e)`** — changed from `\Exception` to catch PHP Errors (TypeError etc.) that bypass Exception handling
2. **`$goal->fresh()`** — re-fetches model from DB before JSON response to ensure `$appends` attributes are properly calculated
3. **`BaseAgent.php`** — older version on server was missing `clearUserCache()`, causing `Call to undefined method` fatal error after goal creation

These fixes are included in the files listed above.

---

## To Upload (Item 38 — Pension Strategies)

### Backend (PHP)
```
app/Services/Retirement/RetirementStrategyService.php
app/Services/Retirement/RetirementProjectionService.php
```

### Frontend
```
public/build/  (entire folder)
```

### Post-Deployment
```bash
php artisan cache:clear
```

- No migrations required — backend change only adds data to existing API response
