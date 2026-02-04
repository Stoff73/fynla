# Deploy Notes - Feb 4, 2025

---

## Goals Life Events Bug Fix ✅ DEPLOYED

### Summary

Life events were not displaying on production because the `life_events` table was empty. The PreviewUserSeeder had been updated to seed life events, but hadn't been run after the migrations.

### Fix Applied

```bash
php artisan db:seed --class=PreviewUserSeeder --force
```

### Files Changed

None - data seeding issue only.

---

## BTL Tax Ownership Percentage Fix ✅ DEPLOYED

### Summary

For jointly-owned Buy-to-Let properties, the rental income and Section 24 tax credit were not being split correctly between owners. The full amount was assigned to the primary owner only.

**Example:**
- BTL Property owned jointly by David (60%) and Sarah (40%)
- Annual taxable rental income: £12,000
- Section 24 credit: £2,400

**Before Fix:**
- David: £7,200 income, £1,440 credit ✅
- Sarah: £0 income, £0 credit ❌

**After Fix:**
- David: £7,200 income, £1,440 credit ✅
- Sarah: £4,800 income, £960 credit ✅

### Root Cause

1. `UserProfileService::calculateAnnualRentalIncome()` only queried properties where `user_id = $user->id`
2. Joint owners (where `joint_owner_id = $user->id`) were never included
3. `PropertyService::calculateTaxPosition()` always used primary owner's percentage

### Files Changed

| File | Change Type |
|------|-------------|
| `app/Services/Property/PropertyService.php` | Modified |
| `app/Services/UserProfile/UserProfileService.php` | Modified |

### Files to Upload

- `app/Services/Property/PropertyService.php`
- `app/Services/UserProfile/UserProfileService.php`

### Post-Upload Commands

```bash
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

---

## UI Improvements ✅ DEPLOYED

### Summary

Minor UI improvements to Expenditure and Estate Planning pages.

### Changes

1. **Expenditure Form** - Changed "Widowed" tab label to "Budget if Widowed" for clarity
2. **IHT Calculation Table** - Added "Expand All / Collapse All" button at top right to toggle all concertina sections

### Files Changed

| File | Change Type |
|------|-------------|
| `resources/js/components/UserProfile/ExpenditureForm.vue` | Modified |
| `resources/js/components/Estate/IHTCalculationTable.vue` | Modified |

### Deployment

**Frontend Rebuild Required:** ✅ YES

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` directory to production.

---

## Pension Fees in Overview Tab ✅ DEPLOYED

### Summary

Added a Fees section to the Overview tab for DC pensions in the detailed view. Shows fee breakdown including platform fee, fund fees (OCF), total annual cost, and annual fee impact.

### Features Added

1. **Platform Fee** - Shows the pension platform fee percentage
2. **Avg Fund Fee (OCF)** - Weighted average OCF across holdings (only shown if pension has holdings)
3. **Total Annual Cost** - Combined platform fee + weighted average OCF
4. **Annual Fee Impact** - Total fees as a pound amount per year

### Files Changed

| File | Change Type |
|------|-------------|
| `resources/js/components/NetWorth/PensionDetailInline.vue` | Modified |
| `resources/js/data/personas/peak_earners.json` | Modified |
| `resources/js/data/personas/young_family.json` | Modified |
| `resources/js/data/personas/entrepreneur.json` | Modified |
| `resources/js/data/personas/young_saver.json` | Modified |
| `database/seeders/PreviewUserSeeder.php` | Modified |

### Changes Made

1. **Vue Component** - Added Fees section to DC pension Overview tab
2. **Persona Data** - Added `platform_fee_percent` to all DC pensions in persona JSON files
3. **Seeder** - Fixed to map `platform_fee_percent` field + fixed LifeEvent soft-delete FK constraint

### Deployment

**Frontend Rebuild Required:** ✅ YES

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` directory to production.

**Seeder Required:** ✅ YES (to populate platform fees)

```bash
php artisan db:seed --class=PreviewUserSeeder --force
```

---

## Complete Upload Checklist

### Goals Life Events Fix ✅

- [x] `php artisan db:seed --class=PreviewUserSeeder --force` (run on server)

### BTL Tax Ownership Fix

**Frontend Rebuild Required:** ❌ NO (backend-only fix)

**Files to Upload:**

- [x] `app/Services/Property/PropertyService.php`
- [x] `app/Services/UserProfile/UserProfileService.php`

### UI Improvements ✅

**Frontend Rebuild Required:** ✅ YES

- [x] Run `./deploy/fynla-org/build.sh`
- [x] Upload `public/build/` directory

### Pension Fees in Overview Tab ✅

**Frontend Rebuild Required:** ✅ YES

- [x] Run `./deploy/fynla-org/build.sh`
- [x] Upload `public/build/` directory
- [x] Upload `database/seeders/PreviewUserSeeder.php`
- [x] Run `php artisan db:seed --class=PreviewUserSeeder --force` on server

### Post-Upload Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Clear caches
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

---

## Verification

After deployment, verify the fix by checking the UK Tax dashboard for peak_earners persona:

- David should show £780 Section 24 credit (50% of £1,560)
- Sarah should show £780 Section 24 credit (50% of £1,560)

Both should have their rental income correctly split by ownership percentage.

---

## Code Quality Audit & Refactoring ✅ DEPLOYED

### Summary

Comprehensive code quality audit and remediation improving codebase score from 82/100 to 89/100. Fixed 18 issues (4 HIGH, 9 MEDIUM, 5 LOW priority) including hardcoded values, duplicated code, and missing documentation.

### Changes

#### New Constants Files
Created centralised constants for UK tax values and estate planning defaults:

| File | Purpose |
|------|---------|
| `app/Constants/TaxDefaults.php` | UK tax values (NRB, ISA, pensions, CGT, etc.) |
| `app/Constants/EstateDefaults.php` | Estate planning estimation constants |

#### New Form Requests
Extracted validation rules from InvestmentController:

| File | Purpose |
|------|---------|
| `app/Http/Requests/StoreInvestmentAccountRequest.php` | Account creation validation |
| `app/Http/Requests/UpdateInvestmentAccountRequest.php` | Account update validation |

#### New Utilities
| File | Purpose |
|------|---------|
| `resources/js/utils/asyncAction.js` | Vuex async action helper to reduce boilerplate |

#### Modified Files

| File | Change |
|------|--------|
| `app/Agents/BaseAgent.php` | Added standardised cache invalidation methods |
| `app/Agents/InvestmentAgent.php` | Use TaxDefaults, improved clearCache() |
| `app/Agents/EstateAgent.php` | Use TaxDefaults and EstateDefaults |
| `app/Agents/RetirementAgent.php` | Use TaxDefaults for growth rate |
| `app/Services/Investment/PortfolioAnalyzer.php` | Implemented geographic allocation |
| `app/Services/Onboarding/EstateOnboardingFlow.php` | Use EstateDefaults constants |
| `app/Jobs/RunMonteCarloSimulation.php` | Use TaxDefaults for cache TTL |
| `app/Http/Controllers/Api/HolisticPlanningController.php` | Use TaxDefaults for cache TTL |
| `app/Http/Controllers/Api/InvestmentController.php` | Removed debug statements |
| `resources/js/store/modules/investment.js` | Added JSDoc to getters |

### Files to Upload

**New Files:**
- `app/Constants/TaxDefaults.php`
- `app/Constants/EstateDefaults.php`
- `app/Http/Requests/StoreInvestmentAccountRequest.php`
- `app/Http/Requests/UpdateInvestmentAccountRequest.php`
- `resources/js/utils/asyncAction.js`

**Modified Backend Files:**
- `app/Agents/BaseAgent.php`
- `app/Agents/InvestmentAgent.php`
- `app/Agents/EstateAgent.php`
- `app/Agents/RetirementAgent.php`
- `app/Services/Investment/PortfolioAnalyzer.php`
- `app/Services/Onboarding/EstateOnboardingFlow.php`
- `app/Jobs/RunMonteCarloSimulation.php`
- `app/Http/Controllers/Api/HolisticPlanningController.php`
- `app/Http/Controllers/Api/InvestmentController.php`

**Modified Frontend Files:**
- `resources/js/store/modules/investment.js`

### Deployment

**Frontend Rebuild Required:** ✅ YES

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` directory to production.

**Database Migrations Required:** ❌ NO

### Post-Upload Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Clear caches
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

### Verification

Changes are backward compatible. Verify by:
1. Testing investment account creation/update
2. Testing estate planning analysis
3. Testing retirement projections
4. Checking Monte Carlo simulations still work

---

## Dashboard Redesign

### Summary

Redesigned the dashboard cards to show more detailed information with real data from APIs.

### Changes

1. **Net Worth Card**
   - Removed "Total Net Worth" heading - just shows the value
   - Lists all assets by category (Pensions, Property, Investments, Cash & Savings, Business Interests, Chattels)
   - Shows Total Assets
   - Lists all liabilities by category (Mortgages, Loans, Credit Cards, Other)
   - Shows Total Liabilities

2. **Retirement Card**
   - Shows Projected Retirement Income (from `analysis.projected_income`)
   - Shows Capital Required (from `requiredCapital.required_capital_at_retirement`)
   - Shows Retirement Age
   - Shows Years to Retirement
   - Added `analyseRetirement` and `fetchRequiredCapital` to data loading

3. **Investments & Savings Card**
   - Lists individual investment accounts as line items with values
   - Shows Total Investments
   - Lists individual cash accounts as line items with values
   - Shows Total Cash

4. **Protection Card**
   - Lists actual policies by type (Life, Critical Illness, Income Protection)
   - Shows policy names with provider and policy type (e.g., "Aviva Level Term")
   - Shows sum assured for each policy
   - Shows total coverage and monthly premium

5. **Estate Planning Card** (NEW)
   - Shows Taxable Estate on Joint Death Now (large blue value)
   - Shows Current Inheritance Tax Liability (Amount Due)
   - Lists user's trusts with name, value, and beneficiaries

6. **Goals & Life Events Card**
   - New simplified GoalsProjectionChartDashboard component (just the chart, no controls)
   - Bar graph displays net worth projection over time
   - Event icons float above bars showing goals and life events
   - Includes retirement age annotation
   - Tooltips on hover for details

7. **Recommended Actions Card**
   - Now positioned as 3rd card in row 2 (part of grid)
   - Changed from card grid to simple line items
   - Each action shows priority dot, title, and module badge
   - Limited to top 5 highest priority actions
   - Simplified header - just "Recommended Actions" heading
   - Clickable items navigate to relevant module

8. **Other Areas to Consider Card** (NEW)
   - Shows incomplete areas of the user's financial profile
   - Checks for missing: Letter to Spouse, Retirement, Protection, Investments, Savings, Goals, Income
   - Each area shows icon, title, description and link to complete
   - Limited to top 5 items
   - Shows "All areas complete!" when everything is filled in

9. **All Cards**
   - Removed arrow icon from top right of all cards

10. **Card Order**
   - Row 1: Net Worth, Estate Planning, Investments & Savings
   - Row 2: Protection, Retirement, Recommended Actions
   - Row 3: Goals (spans 2 columns), Other Areas to Consider

### Files Changed

| File | Change Type |
|------|-------------|
| `resources/js/views/Dashboard.vue` | Modified |
| `resources/js/components/Dashboard/DashboardCard.vue` | Modified |
| `resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue` | New |
| `resources/js/components/Dashboard/ActionsOverviewCard.vue` | Modified |
| `resources/js/components/Dashboard/AreasToConsiderCard.vue` | New |

### Deployment

**Frontend Rebuild Required:** ✅ YES

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` directory to production.
