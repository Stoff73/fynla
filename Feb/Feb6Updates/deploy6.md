# Deployment - 6 February 2026

## Build Required: YES
## Reseed Required: YES (OccupationCodeSeeder, PreviewUserSeeder)
## Migration Required: NO

---

## Changes

### 1. Document Upload Modal - Privacy Disclaimer

**Status:** Deployed

**Description:** Added an informational banner to the document upload modal (shared across all sections) warning users that uploaded data is processed via Anthropic's Haiku 3.5 model and is not anonymised.

**What was done:**
- Added blue info banner to the upload step of the DocumentUploadModal
- Message displayed above the file drop zone on step 1 of every upload flow

**Modified Files (1):**

```text
resources/js/components/Shared/DocumentUploadModal.vue
```

---

### 2. Investment Tab - Data Provider Info Message

**Status:** Deployed

**Description:** Added an informational banner to the Investment tab in the Net Worth module explaining future Bloomberg/Morningstar/FE Analytics integration and the current Monte Carlo capability.

**What was done:**
- Added blue info banner to the InvestmentList dashboard view
- Displayed between the header buttons and account cards/loading state

**Modified Files (1):**

```text
resources/js/components/NetWorth/InvestmentList.vue
```

---

### 3. Goals & Life Events Dashboard - Development Notice

**Status:** Deployed

**Description:** Added an informational banner to the Goals & Life Events dashboard explaining the feature is under development and will integrate with the wider site for AI-driven strategy recommendations.

**What was done:**
- Added blue info banner between the page header and main content area

**Modified Files (1):**

```text
resources/js/views/Goals/GoalsDashboard.vue
```

---

### 4. Fix 500 Error on Info Guide Requirements Endpoint

**Status:** Deployed

**Description:** Fixed a 500 Internal Server Error on `GET api/info-guide/requirements?module=dashboard` caused by referencing a non-existent column `annual_amount` on the `state_pensions` table. The correct column is `state_pension_forecast_annual`.

**What was done:**
- Fixed column reference in `isFieldFilled()` method from `annual_amount` to `state_pension_forecast_annual`

**Modified Files (1):**

```text
app/Services/UserProfile/ModuleDataRequirementsService.php
```

---

### 5. Occupation Lookup Not Working During Onboarding

**Status:** Deployed

**Description:** The occupation autocomplete search during onboarding returned no results because the `occupation_codes` table was empty. The migration had been run previously but the seeder was never executed.

**What was done:**
- Ran `php artisan db:seed --class=OccupationCodeSeeder --force` to populate 406 ONS SOC 2020 occupation codes

**Modified Files:** None (data-only fix)

---

### 6. Goals Projection - Fix Unrealistic Growth Figures

**Status:** Deployed

**Description:** The Goals projection chart was showing unrealistic growth (e.g. 366% return) because the projection model used a complex income/expenditure/cash flow simulation instead of a simple Future Value calculation. Additionally, the investment growth rate was hardcoded at 4.7% instead of reading from the user's assumptions.

**What was done:**
- Replaced the complex projection model (pension drawdown, retirement depletion, etc.) with a simple FV calculation: `FV = PV * (1 + real_rate)^n`
- Real rate = investment_growth - inflation_rate (from user's assumptions)
- Each asset class (investments, pensions, property) grows at its own real rate
- Annual expenditure deducted from cash each year (draws from investments if cash runs out)
- Life events and goals still applied as one-off impacts
- Fixed `getProjectionAssumptions()` to read investment growth from `AssumptionsService` instead of hardcoded 4.7%
- Projection now always runs to age 90 (previously capped at retirement for younger users)
- Summary cards updated: card 2 shows net worth at retirement age, card 3 shows net worth at 90
- Removed % growth display from the projected net worth at 90 card

**Modified Files (2):**

```text
app/Services/Goals/GoalsProjectionService.php
resources/js/components/Goals/ProjectionSummaryCards.vue
```

---

### 7. Remove Postcode Address Lookup Feature

**Status:** Deployed

**Description:** GetAddress.io (the postcode lookup provider) has been shut down due to a court decision. Removed the "Find Address by Postcode" lookup from all forms. Manual address entry fields remain unchanged.

**What was done:**
- Removed PostcodeLookup component usage from all 4 forms (onboarding, user profile, property, estate)
- Removed import and component registration from each parent
- PostcodeLookup.vue, postcodeService.js, and PostcodeLookupController.php left in place (unused) for future re-enablement with a new provider

**Modified Files (4):**

```text
resources/js/components/Onboarding/steps/PersonalInfoStep.vue
resources/js/components/UserProfile/PersonalInformation.vue
resources/js/components/NetWorth/Property/PropertyForm.vue
resources/js/components/Estate/AssetForm.vue
```

---

### 8. Fix 500 Error on Properties Endpoint (and other API Resources)

**Status:** Deployed

**Description:** Fixed 500 Internal Server Error on `GET /api/properties` caused by `new UserResource(null)` when a property's `jointOwner` relationship is eager loaded but resolves to null (individually-owned properties). Fixed the same bug across all 8 API Resource files.

**Root Cause:**
- `$this->when($this->relationLoaded('jointOwner'), fn () => new UserResource($this->jointOwner))` crashes when `jointOwner` is loaded but null
- `relationLoaded()` returns true for eager-loaded relationships even when the FK is null (the relationship resolved to null)
- `new UserResource(null)` throws TypeError when serialized: `null->id` is invalid in PHP 8

**Fix:**
- Changed all resources to use `$this->whenLoaded('relationship', fn () => ...)` pattern
- `whenLoaded()` natively handles "loaded but null" by returning null (safe for serialization)
- Consistent pattern across all 8 resource files

**Modified Files (8):**

```text
app/Http/Resources/PropertyResource.php
app/Http/Resources/GoalResource.php
app/Http/Resources/ChattelResource.php
app/Http/Resources/SavingsAccountResource.php
app/Http/Resources/MortgageResource.php
app/Http/Resources/GoalContributionResource.php
app/Http/Resources/InvestmentAccountResource.php
app/Http/Resources/BusinessInterestResource.php
```

---

### 9. Goals Chart Click Navigation Fix

**Status:** Deployed

**Description:** Clicking the Goals & Life Events chart on the Dashboard required 2-3 clicks to navigate. The chart wrapper div had `@click.stop` which prevented click events from reaching the parent DashboardCard's navigation handler.

**What was done:**
- Removed `@click.stop` from the chart wrapper div in Dashboard.vue
- Added `cursor-pointer` class so the entire chart area shows as clickable
- Clicks anywhere on the Goals card now immediately navigate to `/goals`

**Modified Files (1):**

```text
resources/js/views/Dashboard.vue
```

---

### 10. Goals Cashflow Chart - Income Showing as Zero

**Status:** Deployed

**Description:** The Goals & Life Events cashflow chart view showed income as 0 despite the user having income entered. The backend was hardcoding `'income' => 0` in the yearly projection data instead of calculating the user's actual net income.

**What was done:**
- Injected `UKTaxCalculator` into `GoalsProjectionService`
- Added `getAnnualNetIncome()` method that calculates the user's annual net income (after tax and NI) using the same `calculateNetIncome()` method used by the Income tab
- Added `calculateUserNetIncome()` helper that sums all user income fields (employment, self-employment, rental, dividend, interest, other, trust) and runs them through the tax calculator
- Updated yearly data to use actual net income and calculate surplus (income - expenditure)
- Supports household mode (sums spouse's net income when applicable)

**Modified Files (1):**

```text
app/Services/Goals/GoalsProjectionService.php
```

---

### 11. Remove Chart Type Toggle from Goals Overview

**Status:** Deployed

**Description:** The bar/line chart type toggle icon on the Goals & Life Events projection chart was not working (ApexCharts does not support reactive type changes). Removed the toggle entirely so the chart always displays as a bar chart.

**What was done:**
- Removed `<ChartTypeToggle>` component usage from GoalsProjectionChart.vue
- Removed import and component registration

**Modified Files (1):**

```text
resources/js/components/Goals/GoalsProjectionChart.vue
```

---

### 12. Fix Mortgage Data Not Showing in Property Cards

**Status:** Deployed

**Description:** Mortgage values were not showing in PropertyCard or PropertyDetailInline components, and equity was not deducting mortgage balances. The MortgageResource renamed model fields (e.g. `outstanding_balance` → `current_balance`, `lender_name` → `lender`, `original_loan_amount` → `original_amount`, `maturity_date` → `end_date`) but the frontend components used the original model field names.

**Root Cause:**
- `MortgageResource.php` returned `current_balance` but `PropertyCard.vue` checked `outstanding_balance`
- `hasMortgage` computed always returned `false` (field was `undefined`)
- `mortgageAmount` computed always returned `0`
- `equity` displayed as full property value with no mortgage deduction

**Fix:**
- Added original model field name aliases to MortgageResource alongside the renamed fields
- Added: `outstanding_balance`, `lender_name`, `original_loan_amount`, `maturity_date`
- No frontend changes needed - the fields now match what the components expect

**Modified Files (1):**

```text
app/Http/Resources/MortgageResource.php
```

---

### 13. Hide DC Fund Depletion Warning When No DC Pensions

**Status:** Deployed

**Description:** The "DC fund depletes at age X" warning was showing on the Pension tab even for users who only have DB or State pensions and no DC pensions.

**What was done:**
- Added `hasDCPensions` check to the `v-if` condition on the depletion warning in PensionList.vue
- Warning now only displays when the user actually has DC pensions

**Modified Files (1):**

```text
resources/js/components/NetWorth/PensionList.vue
```

---

### 14. Wealth Summary - Values Cut Off and Not Responsive

**Status:** Deployed

**Description:** In the Wealth Summary tab, large currency values were getting cut off because the column values had `overflow: hidden; text-overflow: ellipsis` which clipped numbers that didn't fit. Grid columns used rigid `minmax()` widths, and font sizes (especially the 20px net worth value) were too large for the available space.

**What was done:**
- Removed `overflow: hidden` and `text-overflow: ellipsis` from `.column-value` so values are always fully visible
- Changed grid columns to flexible `minmax(0, 1fr)` so columns share space equally and shrink as needed
- Used `clamp()` for font sizes on total values so they scale smoothly between mobile and desktop
- Made net worth row the same size as other total rows (was oversized at 20px)
- Updated mobile breakpoints with proportional column sizing and responsive font clamping

**Modified Files (1):**

```text
resources/js/components/NetWorth/WealthSummary.vue
```

---

### 15. Wealth Summary - Exclude DB Pensions from Display

**Status:** Deployed

**Description:** Defined benefit (DB) pensions are not accessible capital (same rationale as excluding State Pension). The wealth summary was showing a combined DC+DB pension value which was misleading. Now only DC pension fund values are displayed, with a note when DB pensions exist. If a user only has DB pensions, a "DB only" message shows instead of £0.

**What was done:**
- Backend: Replaced `calculatePensionValue()` with `calculatePensionBreakdown()` in NetWorthService returning `['dc' => float, 'has_db' => bool]`
- Backend: Only DC fund values included in pension breakdown and total assets
- Backend: Added `has_db_pensions` flag to API response (both user and spouse)
- Vuex store: Added `hasDbPensions` to overview state
- Frontend: Pensions row shows "(not incl. defined benefit pensions)" in small text when DB pensions exist
- Frontend: If no DC value but DB exists, shows "DB only" instead of £0
- Fixed peak_earners persona: Added `"owner": "spouse"` to NHS DB pension so it correctly belongs to Sarah Mitchell

**Modified Files (5):**

```text
app/Services/NetWorth/NetWorthService.php
app/Http/Controllers/Api/NetWorthController.php
resources/js/store/modules/netWorth.js
resources/js/components/NetWorth/NetWorthWealthSummary.vue
resources/js/components/NetWorth/WealthSummary.vue
resources/js/data/personas/peak_earners.json
```

---

### 16. Info Guide - DC Pensions Showing as Missing When User Has DB Pensions

**Status:** Deployed

**Description:** The "What powers this view?" info guide panel was showing "Your money purchase pensions" as missing data for users who only have DB pensions. If a user has DB pensions, it is legitimate to not have DC pensions — this should not be flagged as missing.

**What was done:**
- Updated `isRelationshipFilled()` to also mark `dc_pensions` as filled when the user has DB pensions

**Modified Files (1):**

```text
app/Services/UserProfile/ModuleDataRequirementsService.php
```

---

### 17. Dashboard - Joint Accounts Showing Full Value Instead of Ownership Share

**Status:** Deployed

**Description:** Joint investment accounts and cash/savings accounts on the Dashboard "Investments & Savings" card were displaying 100% of the account value instead of the user's ownership percentage share. For example, a joint premium bond worth £50,000 owned 50/50 was showing as £50,000 instead of £25,000.

**What was done:**
- Added `ownershipValue()` method to Dashboard.vue that applies `ownership_percentage` for joint and tenants-in-common accounts (defaults to 50%)
- Updated investment account line items, cash account line items, and both totals to use ownership-adjusted values

**Modified Files (1):**

```text
resources/js/views/Dashboard.vue
```

---

### 18. Chattels - Joint Ownership Showing 100% Instead of Correct Share

**Status:** Deployed

**Description:** Joint chattels were showing "100% Ownership" and the full value instead of the user's actual share. Two issues: the seeder defaulted `ownership_percentage` to 100 for joint items (should be 50), and `ChattelCard.vue` determined joint status by checking `ownership_percentage < 100` instead of checking `ownership_type`.

**What was done:**
- Fixed `PreviewUserSeeder` to default `ownership_percentage` to 50 for joint/tenants-in-common chattels and business interests
- Fixed `ChattelCard.vue` `isJoint` computed to check `ownership_type` instead of `ownership_percentage < 100`

**Modified Files (2):**

```text
database/seeders/PreviewUserSeeder.php
resources/js/components/NetWorth/ChattelCard.vue
```

---

### 19. Dashboard - Retirement Card Not Showing Projected Income for DB/State-Only Users

**Status:** Deployed

**Description:** The retirement card on the dashboard showed no projected income for users like Sarah Mitchell who only have DB pensions (no DC pensions). The retirement analysis API requires a `RetirementProfile` record — without one, it returns early with no data. Spouses in preview personas were missing retirement profile fields (`target_retirement_age`, `target_retirement_income`), so no profile was created.

**Root Cause:**
- `RetirementAgent::analyze()` returns early if no `RetirementProfile` exists for the user
- Spouse sections in persona JSON files had no `target_retirement_income` or `target_retirement_age` fields
- `PreviewUserSeeder::createRetirementProfiles()` only creates profiles when these fields are present

**Fix (two parts):**
1. **Frontend fallback (Dashboard.vue):** If `projectedIncome` from analysis is 0 but user has DB/State pensions, calculates guaranteed income directly from pension records (DB accrued annual pension + state pension annual amount). This handles both preview users and real users who haven't set up a retirement profile yet.
2. **Persona data fix:** Added `target_retirement_age` and `target_retirement_income` to spouse sections in peak_earners.json (Sarah Mitchell: 60/£55k), retired_couple.json (Harold Bennett: 65/£30k), and young_family.json (Emily Carter: 65/£30k). Also added `target_retirement_income` (£35k) to Patricia Bennett's primary user data.

**Modified Files (4):**

```text
resources/js/views/Dashboard.vue
resources/js/data/personas/peak_earners.json
resources/js/data/personas/retired_couple.json
resources/js/data/personas/young_family.json
```

---

### 20. Vue Best Practices - Chart Keys, mapGetters, Watcher Fixes, setTimeout Cleanup

**Status:** Pending
**Build Required:** YES
**Migration Required:** NO

**Description:** Applied 5 priority actions from the Vue best practices audit across ~100 files to improve performance and prevent memory leaks.

**What was done:**

1. **Chart `:key` pattern (32 files):** Added `chartKey` computed properties and `:key="chartKey"` bindings to all ApexCharts components. Keys use lightweight data identity (array lengths, rounded totals) to prevent unnecessary re-renders from new object references in computed properties.

2. **Replace `$store.state` with `mapState`/`mapGetters` (19 components + 6 store modules):** Replaced direct `this.$store.state.module.prop` access with proper `mapGetters` spread in computed sections. Added missing getters to estate, investment, netWorth, protection, retirement, and trusts store modules.

3. **Fix deep watchers (3 files):** Removed unnecessary `deep: true` from GoalsList and GoalsProjectionChart watchers (props replaced as new objects, shallow watch suffices). Added 150ms debounce to ExpenditureForm's formData deep watcher.

4. **setTimeout cleanup (36 files):** Stored all setTimeout return values in named data properties/refs, added `clearTimeout` in `beforeUnmount`/`onBeforeUnmount` to prevent memory leaks from stale callbacks.

5. **Consolidate duplicate init watchers (3 files):** Removed redundant `isOpen` watchers from GoalFormModal and LifeEventForm (kept `goal`/`event` immediate watchers). Removed redundant `mounted()` call from BusinessInterestForm.

**Modified Files (81 Vue + 6 store modules):**

```text
resources/js/store/modules/estate.js
resources/js/store/modules/investment.js
resources/js/store/modules/netWorth.js
resources/js/store/modules/protection.js
resources/js/store/modules/retirement.js
resources/js/store/modules/trusts.js
resources/js/views/Dashboard.vue
resources/js/views/Admin/AdminPanel.vue
resources/js/views/Investment/AccountPerformancePanel.vue
resources/js/views/Investment/AccountRebalancingPanel.vue
resources/js/views/Retirement/PortfolioAnalysis.vue
resources/js/views/Trusts/TrustDetailView.vue
resources/js/components/Admin/UserManagement.vue
resources/js/components/Auth/ChangePasswordModal.vue
resources/js/components/Cash/BalanceTrendChart.vue
resources/js/components/Cash/SpendingDonutChart.vue
resources/js/components/Dashboard/AreasToConsiderCard.vue
resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue
resources/js/components/Dashboard/GoalsProjectionChartMini.vue
resources/js/components/Dashboard/TaxOptimisationCard.vue
resources/js/components/Estate/AssetsLiabilities.vue
resources/js/components/Estate/CashFlowProjectionChart.vue
resources/js/components/Estate/GiftingStrategy.vue
resources/js/components/Estate/GiftingTimelineChart.vue
resources/js/components/Estate/IHTLiabilityGauge.vue
resources/js/components/Estate/IHTPlanning.vue
resources/js/components/Estate/LifePolicyStrategy.vue
resources/js/components/Estate/NetWorthWaterfallChart.vue
resources/js/components/Estate/WillPlanning.vue
resources/js/components/Goals/GoalFormModal.vue
resources/js/components/Goals/GoalsList.vue
resources/js/components/Goals/GoalsProjectionChart.vue
resources/js/components/Goals/LifeEventForm.vue
resources/js/components/Holistic/CashFlowAllocationChart.vue
resources/js/components/Holistic/NetWorthProjectionChart.vue
resources/js/components/Investment/AllocationComparison.vue
resources/js/components/Investment/AssetAllocationChart.vue
resources/js/components/Investment/EfficientFrontier.vue
resources/js/components/Investment/GeographicAllocationMap.vue
resources/js/components/Investment/Holdings.vue
resources/js/components/Investment/HoldingsTable.vue
resources/js/components/Investment/InvestmentProjectionChart.vue
resources/js/components/Investment/InvestmentRecommendationsTracker.vue
resources/js/components/Investment/MonteCarloResults.vue
resources/js/components/Investment/PerformanceLineChart.vue
resources/js/components/Investment/PortfolioOptimization.vue
resources/js/components/Investment/PortfolioOverview.vue
resources/js/components/Investment/Recommendations.vue
resources/js/components/Investment/TaxFees.vue
resources/js/components/Investment/WhatIfScenarios.vue
resources/js/components/NetWorth/AssetAllocationDonut.vue
resources/js/components/NetWorth/AssetBreakdownBar.vue
resources/js/components/NetWorth/BusinessInterestForm.vue
resources/js/components/NetWorth/InvestmentList.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/components/NetWorth/PropertyList.vue
resources/js/components/Onboarding/steps/FamilyInfoStep.vue
resources/js/components/Protection/CoverageAdequacyGauge.vue
resources/js/components/Protection/CoverageGapChart.vue
resources/js/components/Protection/CoverageTimelineChart.vue
resources/js/components/Protection/CurrentSituation.vue
resources/js/components/Protection/PolicyDetail.vue
resources/js/components/Protection/PremiumBreakdownChart.vue
resources/js/components/Retirement/AccumulationChart.vue
resources/js/components/Retirement/DrawdownSimulator.vue
resources/js/components/Retirement/IncomeDrawdownChart.vue
resources/js/components/Retirement/IncomeProjectionChart.vue
resources/js/components/Retirement/PensionPotProjectionChart.vue
resources/js/components/Retirement/TargetIncomeDrawdownChart.vue
resources/js/components/Savings/EmergencyFundGauge.vue
resources/js/components/Savings/InterestRateComparisonChart.vue
resources/js/components/Shared/CountrySelector.vue
resources/js/components/Shared/DocumentUploadModal.vue
resources/js/components/Shared/OccupationAutocomplete.vue
resources/js/components/UserProfile/DomicileInformation.vue
resources/js/components/UserProfile/ExpenditureForm.vue
resources/js/components/UserProfile/ExpenditureOverview.vue
resources/js/components/UserProfile/FamilyMembers.vue
resources/js/components/UserProfile/IncomeOccupation.vue
resources/js/components/UserProfile/LetterToSpouse.vue
resources/js/components/UserProfile/PersonalInformation.vue
```

---

### 21. Fix Goals Projection 500 Error - Lazy Loading InvestmentAccount

**Status:** Pending
**Build Required:** NO (PHP only)
**Migration Required:** NO

**Description:** The Goals projection endpoint (`GET /api/goals/projection`) returned a 500 error: "Attempted to lazy load [holdings] on model [InvestmentAccount] but lazy loading is disabled." The `GoalsProjectionService` loaded the User with only `['goals', 'spouse']` eager loaded, but `AssumptionsService::calculateWeightedFees()` accesses `$user->investmentAccounts` and then `$account->holdings`.

**What was done:**
- Added `investmentAccounts.holdings` to the eager load in `GoalsProjectionService::generateProjection()`

**Modified Files (1):**

```text
app/Services/Goals/GoalsProjectionService.php
```

---

### 22. Fix Undefined Chart Colors and ApexCharts "Element not found" Errors

**Status:** Pending
**Build Required:** YES
**Migration Required:** NO

**Description:** Three chart components referenced non-existent color keys (`SUCCESS_COLORS[400]`, `SECONDARY_COLORS[400]`) from the design system, causing "undefined color" warnings in ApexCharts. Additionally, GoalsProjectionChart and GoalsProjectionChartDashboard threw "Element not found" errors when ApexCharts tried to render during reactive state changes before the DOM element was ready.

**What was done:**

- Fixed `SUCCESS_COLORS[400]` to `SUCCESS_COLORS[100]` in PensionPotProjectionChart and InvestmentProjectionChart
- Fixed `SECONDARY_COLORS[400]` to `SECONDARY_COLORS[500]` in AccumulationChart
- Added `isComponentMounted` guard to chart `v-else-if` in GoalsProjectionChart and GoalsProjectionChartDashboard to prevent rendering before mount or after unmount

**Modified Files (5):**

```text
resources/js/components/Retirement/PensionPotProjectionChart.vue
resources/js/components/Investment/InvestmentProjectionChart.vue
resources/js/components/Retirement/AccumulationChart.vue
resources/js/components/Goals/GoalsProjectionChart.vue
resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue
```

---

## Pending Deployment Summary (Items 20-22)

**Build Required:** YES
**Migration Required:** NO

### PHP Files

```text
app/Services/Goals/GoalsProjectionService.php
```

### Frontend Files

```text
resources/js/store/modules/estate.js
resources/js/store/modules/investment.js
resources/js/store/modules/netWorth.js
resources/js/store/modules/protection.js
resources/js/store/modules/retirement.js
resources/js/store/modules/trusts.js
resources/js/views/Dashboard.vue
resources/js/views/Admin/AdminPanel.vue
resources/js/views/Investment/AccountPerformancePanel.vue
resources/js/views/Investment/AccountRebalancingPanel.vue
resources/js/views/Retirement/PortfolioAnalysis.vue
resources/js/views/Trusts/TrustDetailView.vue
resources/js/components/Admin/UserManagement.vue
resources/js/components/Auth/ChangePasswordModal.vue
resources/js/components/Cash/BalanceTrendChart.vue
resources/js/components/Cash/SpendingDonutChart.vue
resources/js/components/Dashboard/AreasToConsiderCard.vue
resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue
resources/js/components/Dashboard/GoalsProjectionChartMini.vue
resources/js/components/Dashboard/TaxOptimisationCard.vue
resources/js/components/Estate/AssetsLiabilities.vue
resources/js/components/Estate/CashFlowProjectionChart.vue
resources/js/components/Estate/GiftingStrategy.vue
resources/js/components/Estate/GiftingTimelineChart.vue
resources/js/components/Estate/IHTLiabilityGauge.vue
resources/js/components/Estate/IHTPlanning.vue
resources/js/components/Estate/LifePolicyStrategy.vue
resources/js/components/Estate/NetWorthWaterfallChart.vue
resources/js/components/Estate/WillPlanning.vue
resources/js/components/Goals/GoalFormModal.vue
resources/js/components/Goals/GoalsList.vue
resources/js/components/Goals/GoalsProjectionChart.vue
resources/js/components/Goals/LifeEventForm.vue
resources/js/components/Holistic/CashFlowAllocationChart.vue
resources/js/components/Holistic/NetWorthProjectionChart.vue
resources/js/components/Investment/AllocationComparison.vue
resources/js/components/Investment/AssetAllocationChart.vue
resources/js/components/Investment/EfficientFrontier.vue
resources/js/components/Investment/GeographicAllocationMap.vue
resources/js/components/Investment/Holdings.vue
resources/js/components/Investment/HoldingsTable.vue
resources/js/components/Investment/InvestmentProjectionChart.vue
resources/js/components/Investment/InvestmentRecommendationsTracker.vue
resources/js/components/Investment/MonteCarloResults.vue
resources/js/components/Investment/PerformanceLineChart.vue
resources/js/components/Investment/PortfolioOptimization.vue
resources/js/components/Investment/PortfolioOverview.vue
resources/js/components/Investment/Recommendations.vue
resources/js/components/Investment/TaxFees.vue
resources/js/components/Investment/WhatIfScenarios.vue
resources/js/components/NetWorth/AssetAllocationDonut.vue
resources/js/components/NetWorth/AssetBreakdownBar.vue
resources/js/components/NetWorth/BusinessInterestForm.vue
resources/js/components/NetWorth/InvestmentList.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/components/NetWorth/PropertyList.vue
resources/js/components/Onboarding/steps/FamilyInfoStep.vue
resources/js/components/Protection/CoverageAdequacyGauge.vue
resources/js/components/Protection/CoverageGapChart.vue
resources/js/components/Protection/CoverageTimelineChart.vue
resources/js/components/Protection/CurrentSituation.vue
resources/js/components/Protection/PolicyDetail.vue
resources/js/components/Protection/PremiumBreakdownChart.vue
resources/js/components/Retirement/AccumulationChart.vue
resources/js/components/Retirement/DrawdownSimulator.vue
resources/js/components/Retirement/IncomeDrawdownChart.vue
resources/js/components/Retirement/IncomeProjectionChart.vue
resources/js/components/Retirement/PensionPotProjectionChart.vue
resources/js/components/Retirement/TargetIncomeDrawdownChart.vue
resources/js/components/Savings/EmergencyFundGauge.vue
resources/js/components/Savings/InterestRateComparisonChart.vue
resources/js/components/Shared/CountrySelector.vue
resources/js/components/Shared/DocumentUploadModal.vue
resources/js/components/Shared/OccupationAutocomplete.vue
resources/js/components/UserProfile/DomicileInformation.vue
resources/js/components/UserProfile/ExpenditureForm.vue
resources/js/components/UserProfile/ExpenditureOverview.vue
resources/js/components/UserProfile/FamilyMembers.vue
resources/js/components/UserProfile/IncomeOccupation.vue
resources/js/components/UserProfile/LetterToSpouse.vue
resources/js/components/UserProfile/PersonalInformation.vue
```

### Server Commands

```bash
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```
