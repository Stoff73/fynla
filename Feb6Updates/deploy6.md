# Deployment - 6 February 2026

## Build Required: YES
## Reseed Required: YES (OccupationCodeSeeder)
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

**Upload Required:**
```text
public/build/   (full rebuild)
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

**Upload Required:**
```text
public/build/   (full rebuild)
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

**Upload Required:**
```text
public/build/   (full rebuild)
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

**Upload Required:**
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

**Deployment Action Required:**
```bash
# SSH to server and run:
php artisan db:seed --class=OccupationCodeSeeder --force
```

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

**Upload Required:**

```text
app/Services/Goals/GoalsProjectionService.php
public/build/   (full rebuild)
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

**Upload Required:**

```text
public/build/   (full rebuild)
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

**Upload Required:**
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

**Upload Required:**
```text
public/build/   (full rebuild)
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

**Upload Required:**
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

**Upload Required:**
```text
public/build/   (full rebuild)
```

---

## Summary of All Files to Upload

### PHP Files (no rebuild needed)

```text
app/Services/UserProfile/ModuleDataRequirementsService.php
app/Services/Goals/GoalsProjectionService.php
app/Http/Resources/PropertyResource.php
app/Http/Resources/GoalResource.php
app/Http/Resources/ChattelResource.php
app/Http/Resources/SavingsAccountResource.php
app/Http/Resources/MortgageResource.php
app/Http/Resources/GoalContributionResource.php
app/Http/Resources/InvestmentAccountResource.php
app/Http/Resources/BusinessInterestResource.php
```

### Frontend Files (rebuild required)

```text
resources/js/components/Shared/DocumentUploadModal.vue
resources/js/components/NetWorth/InvestmentList.vue
resources/js/views/Goals/GoalsDashboard.vue
resources/js/components/Goals/GoalsOverview.vue
resources/js/components/Goals/GoalsProjectionChart.vue
resources/js/components/Goals/ProjectionSummaryCards.vue
resources/js/components/Onboarding/steps/PersonalInfoStep.vue
resources/js/components/UserProfile/PersonalInformation.vue
resources/js/components/NetWorth/Property/PropertyForm.vue
resources/js/components/Estate/AssetForm.vue
resources/js/views/Dashboard.vue
```

### Build & Upload

```bash
# 1. Build locally
./deploy/fynla-org/build.sh

# 2. Upload via SiteGround File Manager:
#    - public/build/                          -> ~/www/fynla.org/public_html/public/build/
#    - app/Services/UserProfile/ModuleDataRequirementsService.php
#    - app/Services/Goals/GoalsProjectionService.php
#    - All 8 files from app/Http/Resources/ (listed above)

# 3. SSH to server
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# 4. Seed occupation codes
php artisan db:seed --class=OccupationCodeSeeder --force

# 5. Clear caches
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```
