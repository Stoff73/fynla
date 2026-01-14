# Estate Module Updates - January 14, 2026

## Overview

Refactored the Estate Planning module UI, consolidated life expectancy data sources, and replaced "IHT" acronym with "Inheritance Tax" throughout the application.

**Branch:** `estate`
**Commit:** `8c58e2f`

---

## UI Changes

### 1. Removed Tab Navigation from Estate Dashboard

**File:** `resources/js/views/Estate/EstateDashboard.vue`

- Removed the horizontal tab menu (Inheritance Tax Planning, Gifting Strategy, Life Policy Strategy, Trust Strategy)
- Tabs are now accessed via links in the Inheritance Tax Mitigation Strategies section
- Inheritance Tax Planning tab is shown by default

### 2. Added Back Navigation Links

**Files:**
- `resources/js/components/Estate/GiftingStrategy.vue`
- `resources/js/components/Estate/LifePolicyStrategy.vue`
- `resources/js/components/Estate/TrustPlanning.vue`

Added "Back to Estate Dashboard" link at the top of each strategy component that navigates back to Inheritance Tax Planning tab.

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
- **Only displays when taxable estate exceeds £2 million** (trust planning typically only relevant for larger estates)

**Added method:** `navigateToTrustsTab()` - emits `switch-tab` event with `'trusts'`

### 4. Removed Profile Completeness Alert

**File:** `resources/js/views/Estate/EstateDashboard.vue`

- Removed the `ProfileCompletenessAlert` component from the Estate Dashboard
- Missing data information is now available more subtly in the "What powers this view" tab
- Removed associated import, component registration, data properties, and `loadProfileCompleteness()` method

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
- `IHTCalculationService` - Inheritance Tax projections
- `IHTController` - API endpoints
- `FutureValueCalculator` - Estate/gifting projections

---

## IHT to Inheritance Tax Terminology Update

### Requirement

Replace all user-facing instances of "IHT" acronym with "Inheritance Tax" for better clarity.

### Completed Files (19 components)

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
   - Added detailed explanation box for Joint Death scenario
   - Updated all "IHT" labels to "Inheritance Tax"
   - Updated error messages and tooltips
   - Renamed "Second Death Scenario" to "Joint Death Scenario"

2. **EstateDashboard.vue:**
   - Tab label: "IHT Planning" → "Inheritance Tax Planning"
   - Description updated
   - Removed Profile Completeness Alert

3. **IHTMitigationStrategies.vue:**
   - Section heading updated
   - All savings/liability labels updated

4. **GiftingStrategy.vue:**
   - All IHT rate references updated
   - Taper relief explanations updated

5. **LifePolicyStrategy.vue:**
   - All IHT liability references updated

### Improved Explanation (IHTPlanning.vue)

Added a blue info box below the "Inheritance Tax Calculation (Joint Death Scenario)" heading in **both** calculation sections (for married users with and without linked spouse data):

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

## Tax Calculation Projection Columns

### Requirement

Add two additional projection columns to the Inheritance Tax calculation tables:
- **-5 years**: Shows projected values if death occurs 5 years before life expectancy
- **+5 years**: Shows projected values if death occurs 5 years after life expectancy

### Implementation

**File:** `resources/js/components/Estate/IHTPlanning.vue`

#### New Table Structure (5 columns)

| Column | Description | Color |
|--------|-------------|-------|
| Line Item | Asset/liability name | Gray |
| Now | Current value | Gray |
| Age -5 years | Projection at life expectancy minus 5 | Amber |
| Life expectancy | Projection at estimated death age | Purple |
| Age +5 years | Projection at life expectancy plus 5 | Blue |

#### Added Computed Properties

```javascript
// Growth rate for projections (4.7% annual compound growth)
growthRate() {
  return 0.047;
},

// Years to each projection point
yearsToDeathMinus5() {
  return Math.max(0, this.baseYearsToDeath - 5);
},

yearsToDeathPlus5() {
  return this.baseYearsToDeath + 5;
},

// Ages for column headers
projectedAgeMinus5() {
  const baseAge = this.projection?.at_death?.estimated_age_at_death || 0;
  return Math.max(baseAge - 5, this.getCurrentAge());
},

projectedAgePlus5() {
  const baseAge = this.projection?.at_death?.estimated_age_at_death || 0;
  return baseAge + 5;
},

// Full projections for Standard table (non-married)
projectionMinus5() { /* calculates net_estate, taxable_estate, iht_liability */ },
projectionPlus5() { /* calculates net_estate, taxable_estate, iht_liability */ },

// Full projections for Second Death table (married users)
secondDeathProjectionMinus5() { /* uses combined estate with spouse allowances */ },
secondDeathProjectionPlus5() { /* uses combined estate with spouse allowances */ },
```

#### Added Methods

```javascript
getCurrentAge() {
  // Calculates user's current age from date_of_birth
},

getProjectedValueMinus5(currentValue) {
  const years = this.yearsToDeathMinus5;
  return currentValue * Math.pow(1 + 0.047, years);
},

getProjectedValuePlus5(currentValue) {
  const years = this.yearsToDeathPlus5;
  return currentValue * Math.pow(1 + 0.047, years);
},
```

#### Calculation Logic

- **Assets**: Compound growth at 4.7% per annum
- **Liabilities**: Remain constant (conservative assumption)
- **Allowances**: NRB (£325,000) and RNRB (£175,000) remain constant
- **Taxable Estate**: Max(0, Net Estate - Total Allowances)
- **IHT Liability**: Taxable Estate × 40%

#### Updated Tables

Both tables updated with 5-column format:
1. **Second Death table** (married users with linked spouse) - lines 276-641
2. **Standard table** (non-married or married without linked spouse) - lines 659-850

#### Rows Updated

All row types now include -5 and +5 year projections:
- User Assets (Property, Investment, Cash, Business, Chattel)
- Spouse Assets (Property, Investment, Cash, Business, Chattel)
- User Liabilities (Mortgages, Other)
- Spouse Liabilities (Mortgages, Other)
- Subtotals and Totals
- Net Estate
- Allowances (NRB, RNRB - constant across all columns)
- Taxable Estate
- Inheritance Tax Liability

### Visual Design

Column headers display:
```
Age 77          Age 82              Age 87
-5 years        Life expectancy     +5 years
(amber)         (purple)            (blue)
```

---

## Testing Notes

After these changes, ensure:

1. Estate Dashboard loads with Inheritance Tax Planning visible
2. Clicking strategy cards navigates to respective tabs
3. Trust card only appears when taxable estate > £2 million
4. Back links on all strategy tabs return to Inheritance Tax Planning
5. Life expectancy calculations work correctly (test with preview personas)
6. Joint Death Scenario explanation box displays for married users
7. **Tax calculation table displays 5 columns** (Now, -5 years, Life expectancy, +5 years)
8. **Column headers show correct ages** based on user's life expectancy
9. **Asset projections increase** with compound growth across columns
10. **Liability values remain constant** (conservative assumption)
11. **IHT liability increases** as projected estate grows

```bash
# Verify actuarial data is seeded
php artisan tinker --execute="echo DB::table('actuarial_life_tables')->count();"
# Should output: 44
```

### Test Scenarios for Projection Columns

| Persona | Expected Life Expectancy | -5 Years | +5 Years |
|---------|-------------------------|----------|----------|
| young_family (James, 35) | ~82 | Age 77 | Age 87 |
| peak_earners (David, 52) | ~83 | Age 78 | Age 88 |
| widow (Margaret, 68) | ~86 | Age 81 | Age 91 |

---

## Bug Fixes

### 1. Director's Loan Terminology Fix

**Issue:** Alex Chen's liability showed "Director's Loan to Company" which incorrectly implies an asset (money owed TO Alex).

**Fix:** Changed to "Director's Loan from Company" to correctly indicate a liability (money Alex owes TO the company).

**Files Updated:**
- `resources/js/data/personas/entrepreneur.json` - Updated `liability_name`
- `Jan13Updates/personas.md` - Updated documentation

**Logic:**
- Loan **TO** the company = Asset (company owes Alex)
- Loan **FROM** the company = Liability (Alex owes company) ✓

### 2. Spouse Requirement Warning for Non-Married Users

**Issue:** The "What powers this view" panel showed a warning about missing spouse info for single, divorced, and widowed users who don't have a spouse.

**Fix:** Modified the spouse requirement check to return "filled" for non-married users.

**File Updated:** `app/Services/UserProfile/ModuleDataRequirementsService.php`

**Added Method:**
```php
private function isSpouseRequirementFilled(User $user): bool
{
    // Single, divorced, or widowed users don't need spouse info
    $nonMarriedStatuses = ['single', 'divorced', 'widowed'];

    if (in_array($user->marital_status, $nonMarriedStatuses, true)) {
        return true;
    }

    // Married users need spouse_id to be set
    return $user->spouse_id !== null;
}
```

**Result:**
| Marital Status | Spouse Requirement |
|----------------|-------------------|
| single | ✓ filled (no warning) |
| divorced | ✓ filled (no warning) |
| widowed | ✓ filled (no warning) |
| married (no spouse linked) | ✗ missing (shows warning) |
| married (spouse linked) | ✓ filled (no warning) |

### 3. Spouse Data Visibility for Widowed/Divorced Users

**Issue:** Widowed and divorced users were seeing spouse-related UI elements throughout the application, including:
- Spouse column in Net Worth wealth summary
- "View Spouse Letter" button in Letter to Spouse
- Spouse data sharing options in User Profile
- Spouse exemption notices in Estate module
- Joint death calculations in IHT Planning

**Fix:** Added marital status checks across multiple components to hide spouse data for widowed and divorced users.

**Files Updated:**

| File | Change |
|------|--------|
| `resources/js/components/NetWorth/NetWorthWealthSummary.vue` | Added `shouldShowSpouseData` computed property, passes null for spouse data when user is widowed/divorced |
| `resources/js/components/UserProfile/LetterToSpouse.vue` | Updated `checkSpouse()` method to set `hasSpouse: false` for widowed/divorced users |
| `resources/js/store/modules/spousePermission.js` | Updated `hasSpouse` getter to check marital status from auth store |
| `resources/js/components/Estate/IHTPlanning.vue` | Updated `checkUserMaritalStatus()` method and preview mode logic to exclude widowed/divorced users |

**Key Code Pattern:**

```javascript
// Check if user should see spouse data
const excludedStatuses = ['widowed', 'divorced'];
const shouldShowSpouse = !excludedStatuses.includes(user.marital_status);
```

**Result:**
| Marital Status | Spouse Data Visible |
|----------------|---------------------|
| single | ✗ No spouse data exists |
| married | ✓ Shows spouse data |
| divorced | ✗ Hidden (ex-spouse not relevant) |
| widowed | ✗ Hidden (deceased spouse not relevant) |

**Components Already Correct:**
- `EstateOverviewCard.vue` - Already checks `marital_status === 'married'`
- `SpouseExemptionNotice.vue` - Only shown when parent passes `showSpouseExemptionNotice: true` (married users only)
- `NRBRNRBTracker.vue` - Not currently in use

---

## Net Worth Wealth Summary - Clickable Row Navigation

### Requirement

Make each line item in the Wealth Summary table a clickable link that navigates to the relevant section of the application.

### Implementation

**File:** `resources/js/components/NetWorth/WealthSummary.vue`

#### Asset Row Links

| Line Item | Destination |
|-----------|-------------|
| Property | `/net-worth/property` |
| Investments | `/net-worth/investments` |
| Cash & Savings | `/net-worth/cash` |
| Pensions | `/net-worth/retirement` |
| Business | `/net-worth/business` |
| Chattels | `/net-worth/chattels` |

#### Liability Row Links

| Line Item | Destination |
|-----------|-------------|
| Mortgages | `/net-worth/property` (mortgages tied to properties) |
| Loans | `/profile?section=liabilities` |
| Credit Cards | `/profile?section=liabilities` |
| Other | `/profile?section=liabilities` |

#### Added Methods

```javascript
getAssetLink(type) {
  const isPreview = this.$route.path.startsWith('/preview');
  const basePath = isPreview ? '/preview/net-worth' : '/net-worth';
  const routes = {
    property: `${basePath}/property`,
    investments: `${basePath}/investments`,
    cash: `${basePath}/cash`,
    pensions: `${basePath}/retirement`,
    business: `${basePath}/business`,
    chattels: `${basePath}/chattels`,
  };
  return routes[type] || basePath;
},

getLiabilityLink(type) {
  if (type === 'mortgages') {
    const basePath = isPreview ? '/preview/net-worth' : '/net-worth';
    return `${basePath}/property`;
  }
  return '/profile?section=liabilities';
},
```

#### UI/UX Features

- **Full row clickable**: Entire row is wrapped in `<router-link>`, not just the label
- **Hover effects**:
  - Background: Light gray (`#f3f4f6`) covers entire row
  - Label: Text turns blue (`#3b82f6`)
  - Value cells: Background darkens to `#e5e7eb`
- **Smooth transitions**: All hover effects animate over 0.15s
- **Cursor**: Pointer cursor indicates clickability
- **Preview mode support**: Automatically uses `/preview/net-worth/...` paths when in preview mode

---

## Retirement Dashboard Redesign

### Requirement

Redesign the Retirement dashboard to show key information at a glance:
- Monte Carlo simulation of combined pension pots as main content
- Pension cards in a compact sidebar on the left
- Strategies summary below Monte Carlo (if there is a shortfall)
- Retirement income summary on the right side
- Auto-collapsed sidebar (icons only, like Cash tab)
- **Preserve tab navigation** for detailed views (Future Value, Strategies, Retirement Income)

### Implementation

**Files Updated:**
- `resources/js/components/NetWorth/PensionList.vue` - Complete redesign with tabs preserved
- `resources/js/views/NetWorth/NetWorthDashboard.vue` - Auto-collapse sidebar on retirement tab

### Tab Navigation (Preserved)

The existing tabs remain for access to detailed views:

| Tab | Content |
|-----|---------|
| Pensions | New 3-column overview layout (see below) |
| Future Value | FutureValueTab component (detailed Monte Carlo analysis) |
| Strategies | StrategiesTab component (full strategy recommendations) |
| Retirement Income | RetirementIncomeTab component (detailed income breakdown) |

### New 3-Column Layout (Pensions Tab Only)

The "Pensions" tab now displays a 3-column overview:

| Column | Width | Content |
|--------|-------|---------|
| Left | 3 cols | Pension sidebar (compact cards, totals, actions) |
| Center | 6 cols | Monte Carlo projection + Strategies summary (if shortfall) |
| Right | 3 cols | Retirement income summary |

### Left Panel - Pension Summary

- **Header**: "Pensions" title with Add button
- **Risk Profile Link**: Quick access to risk profile page
- **Total DC Value**: Summary card showing total DC pension value
- **Compact Pension Cards**: Clickable cards for each pension:
  - DC Pensions: Type badge, scheme name, current value
  - DB Pensions: Type badge, scheme name, annual income
  - State Pension: Forecast amount
- **Upload Statement**: Button to upload pension statements

### Center Panel - Projections & Strategies

- **Summary Cards**: 2-column grid showing:
  - Projected Pot at Retirement (median value)
  - Retirement Readiness status (colour-coded)
- **Monte Carlo Chart Card** (clickable → Future Value tab):
  - PensionPotProjectionChart component
  - Subtitle showing DC pension count, retirement age, risk level
  - "View details →" link in header
- **Strategies Section** (only shown if not on track, clickable → Strategies tab):
  - Top 3 applicable strategies with numbered steps
  - Each strategy shows title, description, and probability impact
  - "View all X strategies →" link

### Right Panel - Retirement Income

Clickable card that navigates to Retirement Income tab:

- **Target Annual Income**: Blue section showing target from retirement profile
- **Guaranteed Income**: Green section showing DB pension + State Pension breakdown
- **Income Gap/Surplus**: Red (shortfall) or green (surplus) section showing difference
- **Fund Depletion Warning**: Amber warning if DC fund depletes before end of life expectancy
- **Chevron icon**: Indicates card is clickable for more detail

### Clickable Summary Cards

Summary cards in the Pensions tab link to their respective detailed tabs:

| Card | Destination Tab | Interaction |
|------|-----------------|-------------|
| Monte Carlo Chart | Future Value | Click anywhere on card or "View details" link |
| Strategies Summary | Strategies | Click anywhere on card or "View all" link |
| Retirement Income | Retirement Income | Click anywhere on card |

### Sidebar Auto-Collapse

**File:** `resources/js/views/NetWorth/NetWorthDashboard.vue`

Updated the `currentSection` watcher to collapse sidebar on both cash and retirement tabs:

```javascript
watch: {
  currentSection: {
    immediate: true,
    handler(section) {
      // Auto-collapse sidebar on cash and retirement tabs for more screen space
      if (section === 'cash' || section === 'retirement') {
        this.sidebarCollapsed = true;
      }
    },
  },
},
```

### Data Flow

```
mounted() {
  fetchRetirementData()     // Load DC/DB/State pensions
  loadProjectionsAndStrategies() {
    fetchProjections()       // Run Monte Carlo simulation
    if (showStrategies) {
      fetchStrategies()      // Only load strategies if not on track
    }
  }
}
```

### Testing Notes

1. Retirement tab should auto-collapse the sidebar to icons
2. **Tab navigation still visible** at top (Pensions, Future Value, Strategies, Retirement Income)
3. Left panel shows compact pension cards (click to view details)
4. Center panel shows Monte Carlo chart with projections
5. **Clicking Monte Carlo chart card navigates to Future Value tab**
6. Strategies section only appears if retirement readiness is "Needs Attention" or "At Risk"
7. **Clicking strategies card navigates to Strategies tab**
8. Right panel shows income summary with gap/surplus calculation
9. **Clicking income summary navigates to Retirement Income tab**
10. Fund depletion warning appears if applicable
11. Add Pension and Upload Statement buttons remain functional

---

## Summary of Changes

| Category | Changes |
|----------|---------|
| Files Modified | 33 |
| Files Deleted | 4 |
| Files Created | 1 |
| Vue Components Updated | 25 |
| Store Modules Updated | 1 (spousePermission.js) |
| Terminology Updates | "IHT" → "Inheritance Tax" throughout |
| Data Sources Consolidated | 3 → 1 (actuarial_life_tables) |
| Tax Calculation Columns | 3 → 5 (added -5 and +5 year projections) |
| New Computed Properties | 21 (projection calculations + spouse visibility + retirement income) |
| New Methods | 9 (projection, navigation, formatting methods) |
| Bug Fixes | 3 (Director's Loan terminology, Spouse requirement warning, Spouse data visibility for widowed/divorced) |
| UX Enhancements | 2 (Wealth Summary clickable rows, Retirement dashboard redesign) |
