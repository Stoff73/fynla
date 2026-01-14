# Estate Module Updates - January 14, 2026

## Overview

Refactored the Estate Planning module UI, consolidated life expectancy data sources, and replaced "IHT" acronym with "Inheritance Tax" throughout the application.

---

## UI Changes

### 1. Removed Tab Navigation from Estate Dashboard

**File:** `resources/js/views/Estate/EstateDashboard.vue`

- Removed the horizontal tab menu (IHT Planning, Gifting Strategy, Life Policy Strategy, Trust Strategy)
- Tabs are now accessed via links in the IHT Mitigation Strategies section
- IHT Planning tab is shown by default

### 2. Added Back Navigation Links

**Files:**
- `resources/js/components/Estate/GiftingStrategy.vue`
- `resources/js/components/Estate/LifePolicyStrategy.vue`
- `resources/js/components/Estate/TrustPlanning.vue`

Added "Back to Estate Dashboard" link at the top of each strategy component that navigates back to IHT Planning tab.

### 3. Updated Trust Card in IHT Planning

**File:** `resources/js/components/Estate/IHTPlanning.vue`

- Removed "Coming Soon" styling and messaging
- Trust card now matches Gifting and Life Policy card format
- Displays:
  - Total Trust Value
  - Value Outside Estate
  - Efficiency percentage
- Clickable to navigate to Trust Strategy tab
- Uses indigo color scheme

**Added method:** `navigateToTrustsTab()` - emits `switch-tab` event with `'trusts'`

---

## Life Expectancy Data Consolidation

### Problem

Three separate sources of life expectancy data existed:
1. `actuarial_life_tables` (DB) - used by IHTCalculationService
2. `uk_life_expectancy_tables` (DB) - used by ActuarialLifeTableService (orphaned)
3. `uk_life_expectancy` (config) - used by FutureValueCalculator

### Solution

Consolidated to single source: `actuarial_life_tables` database table.

### Files Removed

| File | Reason |
|------|--------|
| `database/seeders/UKLifeExpectancySeeder.php` | Orphaned - seeded unused table |
| `app/Services/Estate/ActuarialLifeTableService.php` | Orphaned - not used by any code |
| `tests/Unit/Services/Estate/ActuarialLifeTableServiceTest.php` | Test for removed service |
| `config/uk_life_expectancy.php` | Replaced by database lookup |

### Files Updated

#### `app/Services/Estate/FutureValueCalculator.php`

Changed `lookupLifeExpectancy()` method to query database instead of config:

```php
// Before
$tables = config('uk_life_expectancy');

// After
$exactMatch = DB::table('actuarial_life_tables')
    ->where('age', $age)
    ->where('gender', $gender)
    ->where('table_year', '2020-2022')
    ->value('life_expectancy_years');
```

Features:
- Exact match lookup first
- Linear interpolation for ages between data points
- Edge case handling for ages outside table range
- Fallback to `85 - age` if no data

#### `database/seeders/DatabaseSeeder.php`

Removed `UKLifeExpectancySeeder` from seeder list.

#### `CLAUDE.md`

- Removed `UKLifeExpectancySeeder` from required seeders lists
- Updated troubleshooting table: Life expectancy errors now reference `ActuarialLifeTablesSeeder`

#### `seedMigration.md`

- Removed all `UKLifeExpectancySeeder` references
- Updated Phase 1 Required Seeders table
- Updated Database Tables Reference
- Updated Symptoms of Missing Seeders table

---

## Single Source of Truth

All life expectancy data now comes from:

| Table | Seeder | Data Source |
|-------|--------|-------------|
| `actuarial_life_tables` | `ActuarialLifeTablesSeeder` | UK ONS National Life Tables 2020-2022 |

**Used by:**
- `IHTCalculationService` - IHT projections
- `IHTController` - API endpoints
- `FutureValueCalculator` - Estate/gifting projections

---

## IHT to Inheritance Tax Terminology Update

### Requirement

Replace all user-facing instances of "IHT" acronym with "Inheritance Tax" for better clarity.

### Completed Files

The following files have been fully updated:

| File | Changes |
|------|---------|
| `resources/js/components/Estate/IHTPlanning.vue` | All user-facing IHT references updated |
| `resources/js/views/Estate/EstateDashboard.vue` | Tab labels and descriptions updated |
| `resources/js/components/Estate/IHTMitigationStrategies.vue` | Headings and messages updated |
| `resources/js/components/Estate/GiftingStrategy.vue` | All IHT references updated |
| `resources/js/components/Estate/LifePolicyStrategy.vue` | All IHT references updated |
| `resources/js/components/Estate/TrustPlanning.vue` | Trust section descriptions and recommendations |
| `resources/js/components/Estate/TrustPlanningStrategy.vue` | IHT Saving labels updated |
| `resources/js/components/Estate/EstateOverviewCard.vue` | Liability labels and status banners |
| `resources/js/components/Estate/GiftCard.vue` | Effective rate and status text |
| `resources/js/components/Estate/GiftForm.vue` | Form subtitle and gift type descriptions |
| `resources/js/components/Estate/IHTLiabilityGauge.vue` | Header, labels, and status messages |
| `resources/js/components/Estate/NRBRNRBTracker.vue` | Allowance tracker heading and tooltips |
| `resources/js/components/Estate/AssetsLiabilities.vue` | Table header for tax status |
| `resources/js/components/Estate/SpouseExemptionNotice.vue` | Data sharing description |
| `resources/js/components/Estate/MissingDataAlert.vue` | Default message |
| `resources/js/components/Estate/WillPlanning.vue` | Bequest calculation messages |
| `resources/js/components/Estate/LifeCoverRecommendations.vue` | Coverage label |
| `resources/js/components/Estate/GiftingTimelineChart.vue` | Legend, notes, and status labels |
| `resources/js/components/Estate/EstateProjectionComparison.vue` | Liability row header |

### Key Changes Made

1. **IHTPlanning.vue:**
   - Updated heading explanations
   - Added detailed explanation box for Second Death scenario
   - Updated all "IHT" labels to "Inheritance Tax"
   - Updated error messages and tooltips

2. **EstateDashboard.vue:**
   - Tab label: "IHT Planning" → "Inheritance Tax Planning"
   - Description updated

3. **IHTMitigationStrategies.vue:**
   - Section heading updated
   - All savings/liability labels updated

4. **GiftingStrategy.vue:**
   - All IHT rate references updated
   - Taper relief explanations updated

5. **LifePolicyStrategy.vue:**
   - All IHT liability references updated

### Improved Explanation (IHTPlanning.vue)

Added a blue info box below the "Inheritance Tax Calculation (Second Death Scenario)" heading in **both** calculation sections (Second Death section and Standard section for married users):

```
What this calculation shows: This scenario assumes both you and your spouse pass away
at the same time, with the combined estate then passing to your beneficiaries.
The projected age is based on your life expectancy and may differ from your spouse's.

If one spouse dies first: Under most wills, the entire estate passes to the surviving
spouse tax-free (spouse exemption). Inheritance Tax would then be calculated on the
surviving spouse's estate at their death, potentially with different allowances and values.
```

**Note:** The explanation box only appears for married users. Single users see a simpler description.

### Files Still Requiring Updates

The following files may contain additional IHT references (non-Estate components):

- `resources/js/components/Holistic/ModuleSummaries.vue`
- `resources/js/views/Estate/ComprehensiveEstatePlan.vue`
- `resources/js/views/Trusts/TrustDetailView.vue`
- `resources/js/views/Trusts/TrustsDashboard.vue`
- `resources/js/views/Public/LandingPage.vue`
- `resources/js/views/Public/LearningCentre.vue`
- `resources/js/components/Admin/TaxSettings.vue`
- `resources/js/components/Onboarding/FocusAreaSelection.vue`
- `resources/js/components/Onboarding/steps/DomicileInformationStep.vue`
- `resources/js/components/Onboarding/steps/FamilyInfoStep.vue`
- `resources/js/components/Onboarding/steps/TrustInfoStep.vue`

**Note:** Code-level variable names (e.g., `ihtData`, `iht_liability`) remain unchanged as they are not user-facing.

---

## Testing Notes

After these changes, ensure:

1. Estate Dashboard loads with IHT Planning visible
2. Clicking Trust card navigates to Trust Strategy tab
3. Back links on all strategy tabs return to IHT Planning
4. Life expectancy calculations work correctly (test with preview personas)

```bash
# Verify actuarial data is seeded
php artisan tinker --execute="echo DB::table('actuarial_life_tables')->count();"
# Should output: 44
```
