# Deployment Notes - January 28, 2026

---

## UI Consistency Overhaul - Design System Implementation

**Branch:** main (merged from `ui`)

**PR:** #38

**Status:** ✅ Deployed to production

### Description

Comprehensive UI audit and fix of the entire Fynla application. Created a centralized design system and eliminated 1,332 hardcoded hex colors across 120+ Vue components.

### Summary

| Metric           | Before | After      |
|------------------|--------|------------|
| Hardcoded colors | 1,334  | 2          |
| Files with issues| 90     | 2          |
| Reduction        | -      | **99.85%** |

### New Files Created

| File                                      | Lines | Description                                              |
|-------------------------------------------|-------|----------------------------------------------------------|
| `designStyle.md`                          | 1,198 | Comprehensive design system document - single source of truth |
| `resources/js/constants/designSystem.js`  | 319   | JavaScript design tokens for charts, colors, helper functions |

### Bug Fixes Included

1. **Circular CSS class definitions** - Fixed `.text-gray-500 { @apply text-gray-500; }` patterns
2. **Invalid Tailwind classes** - Changed `border-3` to `border-4` (Tailwind only supports 0, 2, 4, 8)
3. **Malformed @apply syntax** - Fixed `border-@apply` to `@apply border-`

---

## Rebuild Required: YES

All frontend Vue components changed. Full rebuild required.

```bash
./deploy/fynla-org/build.sh
```

---

## Files to Upload (Manual - Not in Build)

These files are NOT compiled into the build and must be uploaded manually:

```text
designStyle.md                           → ~/www/fynla.org/public_html/designStyle.md
.claude/agents/premium-ui-designer.md    → ~/www/fynla.org/public_html/.claude/agents/premium-ui-designer.md
```

**Note:** The `designStyle.md` file is documentation only and not required for the application to function. Upload is optional.

---

## Files Changed (Included in Build)

All files below are compiled into `public/build/` during the build process. No manual upload required for these - just upload the built assets.

### CSS (1 file)

```text
resources/css/app.css
```

### JavaScript Constants (1 file)

```text
resources/js/constants/designSystem.js
```

### Vue Components - Actions (1 file)

```text
resources/js/components/Actions/RecommendationFilters.vue
```

### Vue Components - Auth (2 files)

```text
resources/js/components/Auth/MFASetupModal.vue
resources/js/components/Auth/MFAVerifyModal.vue
```

### Vue Components - Cash (5 files)

```text
resources/js/components/Cash/AccountGroupList.vue
resources/js/components/Cash/AccountSummaryPanel.vue
resources/js/components/Cash/BalanceTrendChart.vue
resources/js/components/Cash/CashActionsPanel.vue
resources/js/components/Cash/SpendingDonutChart.vue
```

### Vue Components - Common (1 file)

```text
resources/js/components/Common/PrintHeader.vue
```

### Vue Components - Dashboard (4 files)

```text
resources/js/components/Dashboard/ActionsOverviewCard.vue
resources/js/components/Dashboard/FinancialHealthScore.vue
resources/js/components/Dashboard/NetWorthOverviewCard.vue
resources/js/components/Dashboard/RetirementOverviewCard.vue
```

### Vue Components - Estate (13 files)

```text
resources/js/components/Estate/AssetForm.vue
resources/js/components/Estate/CashFlowProjectionChart.vue
resources/js/components/Estate/DualGiftingTimeline.vue
resources/js/components/Estate/EstateOverviewCard.vue
resources/js/components/Estate/GiftCard.vue
resources/js/components/Estate/GiftForm.vue
resources/js/components/Estate/GiftingStrategy.vue
resources/js/components/Estate/GiftingTimelineChart.vue
resources/js/components/Estate/IHTLiabilityGauge.vue
resources/js/components/Estate/LiabilityForm.vue
resources/js/components/Estate/NRBRNRBTracker.vue
resources/js/components/Estate/NetWorthWaterfallChart.vue
resources/js/components/Estate/WillPlanning.vue
```

### Vue Components - Holistic (3 files)

```text
resources/js/components/Holistic/CashFlowAllocationChart.vue
resources/js/components/Holistic/NetWorthProjectionChart.vue
resources/js/components/Holistic/RiskAssessment.vue
```

### Vue Components - Investment (21 files)

```text
resources/js/components/Investment/AccountStrategyCard.vue
resources/js/components/Investment/AllocationComparison.vue
resources/js/components/Investment/AssetAllocationChart.vue
resources/js/components/Investment/AssetLocationOptimizer.vue
resources/js/components/Investment/BenchmarkComparison.vue
resources/js/components/Investment/ComprehensiveInvestmentPlan.vue
resources/js/components/Investment/ContributionPlanner.vue
resources/js/components/Investment/CorrelationMatrix.vue
resources/js/components/Investment/EfficientFrontier.vue
resources/js/components/Investment/FeeSavingsCalculator.vue
resources/js/components/Investment/GeographicAllocationMap.vue
resources/js/components/Investment/GoalProjection.vue
resources/js/components/Investment/HoldingsTable.vue
resources/js/components/Investment/InvestmentProjectionChart.vue
resources/js/components/Investment/MonteCarloResults.vue
resources/js/components/Investment/Performance.vue
resources/js/components/Investment/PerformanceAttribution.vue
resources/js/components/Investment/PerformanceLineChart.vue
resources/js/components/Investment/PortfolioOptimizer.vue
resources/js/components/Investment/PortfolioOverview.vue
resources/js/components/Investment/WrapperOptimizer.vue
```

### Vue Components - Legal (1 file)

```text
resources/js/components/Legal/StrategyDisclaimer.vue
```

### Vue Components - NetWorth (25 files)

```text
resources/js/components/NetWorth/AssetAllocationDonut.vue
resources/js/components/NetWorth/AssetBreakdownBar.vue
resources/js/components/NetWorth/BusinessInterestCard.vue
resources/js/components/NetWorth/BusinessInterestsList.vue
resources/js/components/NetWorth/ChattelCard.vue
resources/js/components/NetWorth/ChattelDetailInline.vue
resources/js/components/NetWorth/ChattelsList.vue
resources/js/components/NetWorth/FeesDetail.vue
resources/js/components/NetWorth/HoldingsDetail.vue
resources/js/components/NetWorth/InvestmentDetailInline.vue
resources/js/components/NetWorth/InvestmentList.vue
resources/js/components/NetWorth/InvestmentProjections.vue
resources/js/components/NetWorth/JointAccountHistory.vue
resources/js/components/NetWorth/NetWorthOverview.vue
resources/js/components/NetWorth/NetWorthTrendChart.vue
resources/js/components/NetWorth/NetWorthWealthSummary.vue
resources/js/components/NetWorth/PensionDetailInline.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/components/NetWorth/Property/PropertyDetailInline.vue
resources/js/components/NetWorth/PropertyCard.vue
resources/js/components/NetWorth/PropertyForm.vue
resources/js/components/NetWorth/PropertyList.vue
resources/js/components/NetWorth/StrategyDetail.vue
resources/js/components/NetWorth/TaxEfficiencyDetail.vue
resources/js/components/NetWorth/WealthSummary.vue
```

### Vue Components - Onboarding (1 file)

```text
resources/js/components/Onboarding/steps/AssetsStep.vue
```

### Vue Components - Protection (9 files)

```text
resources/js/components/Protection/CoverageAdequacyGauge.vue
resources/js/components/Protection/CoverageGapChart.vue
resources/js/components/Protection/CoverageTimelineChart.vue
resources/js/components/Protection/PolicyDetail.vue
resources/js/components/Protection/PolicyFormModal.vue
resources/js/components/Protection/PremiumBreakdownChart.vue
resources/js/components/Protection/ProtectionOverviewCard.vue
resources/js/components/Protection/ScenarioBuilder.vue
resources/js/components/Protection/WhatIfScenarios.vue
```

### Vue Components - Retirement (15 files)

```text
resources/js/components/Retirement/AccumulationChart.vue
resources/js/components/Retirement/DBPensionForm.vue
resources/js/components/Retirement/DCPensionForm.vue
resources/js/components/Retirement/DrawdownSimulator.vue
resources/js/components/Retirement/FundDepletionChart.vue
resources/js/components/Retirement/FutureValueTab.vue
resources/js/components/Retirement/IncomeDrawdownChart.vue
resources/js/components/Retirement/IncomeProjectionChart.vue
resources/js/components/Retirement/IncomeSourceSlider.vue
resources/js/components/Retirement/PensionPotProjectionChart.vue
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/components/Retirement/StrategiesTab.vue
resources/js/components/Retirement/StrategyCard.vue
resources/js/components/Retirement/TargetIncomeDrawdownChart.vue
resources/js/components/Retirement/TaxBreakdownCard.vue
```

### Vue Components - Savings (6 files)

```text
resources/js/components/Savings/CurrentSituation.vue
resources/js/components/Savings/EmergencyFund.vue
resources/js/components/Savings/EmergencyFundGauge.vue
resources/js/components/Savings/InterestRateComparisonChart.vue
resources/js/components/Savings/SaveAccountModal.vue
resources/js/components/Savings/SaveGoalModal.vue
```

### Vue Components - Shared (2 files)

```text
resources/js/components/Shared/CountrySelector.vue
resources/js/components/Shared/RiskLevelSelector.vue
```

### Vue Components - Trusts (2 files)

```text
resources/js/components/Trusts/TrustCard.vue
resources/js/components/Trusts/TrustsOverviewCard.vue
```

### Vue Views (5 files)

```text
resources/js/views/Investment/AccountDetailView.vue
resources/js/views/Investment/AccountHoldingsPanel.vue
resources/js/views/Retirement/RetirementReadiness.vue
resources/js/views/Settings/PrivacySettings.vue
resources/js/views/Trusts/TrustsDashboard.vue
```

---

## Upload Checklist

### Step 1: Run Build

```bash
cd /Users/Chris/Desktop/fynla
./deploy/fynla-org/build.sh
```

### Step 2: Upload Built Assets

Upload the entire `public/build/` directory to:

```text
~/www/fynla.org/public_html/public/build/
```

### Step 3: Upload Manual Files (Optional)

```text
designStyle.md → ~/www/fynla.org/public_html/designStyle.md
```

### Step 4: Clear Cache (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear
```

---

## Verification

After deployment, verify:

1. **No Console Errors**: Check browser console for any CSS/Vite errors
2. **Visual Consistency**: Navigate through all modules:
   - Dashboard
   - Protection
   - Savings
   - Investment
   - Retirement (including Pensions tab)
   - Estate Planning
   - Trusts
3. **Chart Colors**: Verify charts use consistent color palette
4. **No Mustard/Pastel Colors**: All colors should be solid, professional tones
5. **Loading States**: Spinners should display correctly (border-4 styling)

---

## Form Modal Persistence - Prevent Accidental Close

**Branch:** userProReformat

**Status:** ✅ Deployed to production

### Description

Updated all add/edit form modals to persist when clicking outside the modal. Forms now only close via Cancel button, X button, or successful form submission. This prevents users from accidentally losing form data by clicking outside.

### Files Changed (17 files - Included in Build)

| Module | Component | Change |
|--------|-----------|--------|
| Admin | UserFormModal.vue | Removed backdrop click handler |
| Estate | TrustForm.vue | Removed backdrop click handler |
| Estate | TrustFormModal.vue | Removed backdrop click handler |
| Goals | GoalFormModal.vue | Removed backdrop click handler |
| Goals | ContributionModal.vue | Removed backdrop click handler |
| Investment | AccountForm.vue | Removed backdrop click handler |
| Investment | GoalForm.vue | Removed backdrop click handler |
| Investment | HoldingForm.vue | Removed backdrop click handler |
| NetWorth | ChattelFormModal.vue | Removed backdrop click handler |
| Protection | PolicyFormModal.vue | Removed backdrop click handler |
| Retirement | DBPensionForm.vue | Removed backdrop click handler |
| Retirement | DCPensionForm.vue | Removed backdrop click handler |
| Retirement | StatePensionForm.vue | Removed backdrop click handler |
| Retirement | UnifiedPensionForm.vue | Removed backdrop click handler |
| Savings | SaveAccountModal.vue | Removed backdrop click handler |
| Savings | SaveGoalModal.vue | Removed backdrop click handler |
| UserProfile | FamilyMemberFormModal.vue | Removed backdrop click handler |

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

Test form modals across all modules:
1. Open any add/edit form modal
2. Click outside the modal (on the grey backdrop)
3. Verify the modal remains open
4. Verify Cancel/X button still closes the modal
5. Verify form submission still works

---

## User Profile Redesign - View/Edit Mode & Tab Reorganisation

**Branch:** userProReformat

**Status:** ✅ Deployed to production

### Description

Redesigned the User Profile page with two major improvements:

1. **View/Edit Mode Display**: Input boxes now only appear when user clicks "Edit". In normal view mode, values display as plain text without box styling, creating a cleaner presentation.

2. **Tab Reorganisation**:
   - Renamed "Income & Occupation" to "Income" (occupation moved elsewhere)
   - Moved occupation fields (job title, employer, industry, employment status, retirement age/date) to Personal Info tab
   - Moved domicile fields (country of birth, UK arrival date, domicile status) to Personal Info tab
   - Removed standalone Domicile Status tab

### Files Changed (9 files - Included in Build)

| File | Change |
|------|--------|
| `resources/js/views/UserProfile.vue` | Removed Domicile tab, renamed Income tab |
| `resources/js/components/UserProfile/PersonalInformation.vue` | Clean two-column view, card wrapper, btn-secondary Edit button |
| `resources/js/components/UserProfile/HealthInformation.vue` | Clean two-column view layout |
| `resources/js/components/UserProfile/FamilyMembers.vue` | Card wrapper, btn-secondary Add button |
| `resources/js/components/UserProfile/IncomeOccupation.vue` | Clean two-column view, card wrapper, btn-secondary Edit button |
| `resources/js/components/UserProfile/ExpenditureOverview.vue` | Card wrapper with consistent styling |
| `resources/js/components/UserProfile/ExpenditureForm.vue` | Complete redesign with view/edit mode, three-column layout, section sub-totals |
| `resources/js/services/userProfileService.js` | Added `updateDomicile()` method |
| `resources/js/store/modules/userProfile.js` | Added `updateDomicile` action |

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

Test all User Profile tabs:

**Personal Info tab:**
1. Verify clean two-column layout with label-value pairs (labels left, values right)
2. Verify "Edit" button (btn-secondary) in header
3. Click "Edit" and verify input boxes appear
4. Verify occupation fields are present
5. Verify domicile fields are present (with country selector)
6. Test Cancel and Save functionality

**Health tab:**
1. Verify clean two-column layout
2. Verify "Edit" button in header
3. Test edit mode with input fields

**Family tab:**
1. Verify card wrapper with header
2. Verify "Add" button (btn-secondary) in header
3. Test adding/editing family members

**Income tab:**
1. Verify clean two-column layout for income values
2. Verify "Edit" button in header
3. Verify Tax Calculations and Disposable Income sections display correctly

**Expenditure tab:**

1. Verify clean grid layout with columns: Category | User | Spouse | Household (for married users)
2. For single users, verify two-column layout: Category | Total
3. Verify "Edit" button in header
4. Click "Edit" and verify:
   - "Enter separate expenditure" checkbox appears (with tick mark when checked)
   - Entry method toggle appears (Monthly/Annual)
   - Form inputs appear for manual entry fields
   - Auto-pulled data (Financial Commitments) shows blue boxes (bg-blue-50)
5. In view mode, verify:
   - Financial Commitments row shows "Auto-calculated" badge
   - Section sub-totals appear after each category (Essential, Communication, Lifestyle, Children, Other)
   - Grand total appears at bottom
6. Test Cancel and Save functionality

---

## Expenditure Tab - Bug Fixes & Layout Improvements

**Date:** 28 January 2026

**Branch:** userProReformat

**Status:** ✅ Deployed to production

### Description

Fixed multiple bugs in the Expenditure tab and improved the grid layout for better alignment and readability.

### Bug Fixes

| Bug | Issue | Fix |
|-----|-------|-----|
| BUG-043 | Financial commitments not loading - double `api/api/` prefix in URL | Changed `response.data` to `response.data.data` and fixed spouse endpoint URL |
| BUG-044 | Expenditure save failing with 500 error - enum value mismatch | Changed `'detailed'` to `'category'` to match database enum `('simple', 'category')` |
| BUG-045 | Spouse expenditure URL incorrect - 405 Method Not Allowed | Removed `/api` prefix from service URL (api instance already adds it) |

### Layout Improvements

1. **Column alignment**: Replaced `text-right` with CSS classes for proper centering
2. **Equal column widths**: All value columns now use `minmax(90px, max-content)`
3. **Gap between labels and figures**: Added `padding-left: 2rem` to first value column
4. **Right-aligned totals**: Household/Total column right-aligned for visual consistency
5. **Grand total alignment**: Removed gray card container that was causing misalignment

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/UserProfile/ExpenditureForm.vue
resources/js/services/userProfileService.js
```

**Backend (Manual Upload Required):**
```text
app/Http/Controllers/Api/UserProfileController.php
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Backend Changes

The controller now:
- Accepts `use_simple_entry` and `use_separate_expenditure` from frontend
- Maps to database columns: `expenditure_entry_mode` and `expenditure_sharing_mode`
- Added validation for missing fields: `school_lunches`, `school_extras`, `university_fees`

### Verification

1. Navigate to User Profile → Expenditure tab
2. Verify Financial Commitments section shows data (Property Expenses, Protection Premiums, etc.)
3. Click Edit → Check "Enter separate expenditure" → Click Save
4. Verify the setting persists (shows "Separate" in description, checkbox remains checked on re-edit)
5. Verify all columns are equally sized and aligned

---

## Expenditure Tab - Budget Tabs Feature (Full Implementation)

**Date:** 28 January 2026

**Branch:** userProReformat

**Status:** ✅ Deployed to production

### Description

Added three budget tabs to the Expenditure form to support different financial planning scenarios:

1. **Current Budget** - Existing expenditure tracking (fully functional)
2. **Retired Budget** - Auto-calculated retirement spending based on retirement age, with user overrides
3. **Widowed Budget** - Auto-calculated single-person household expenses for protection planning (married users only)

### Auto-Adjustments - Retired Budget

Based on user's retirement age (from profile) and spouse's retirement age (if married):

| Category | Adjustment | Reason |
|----------|------------|--------|
| Transport & Fuel | 40% of current | No commuting required |
| Clothing & Personal Care | 70% of current | No work wardrobe needed |
| Healthcare & Medical | 130% of current | Costs typically rise with age |
| Holidays & Travel | 120% of current | More leisure time |
| Childcare | £0 | Children typically independent |
| School Fees | £0 | Children finished education |
| School Lunches/Extras | £0 | Children finished school |
| University Fees | £0 | Children finished education |
| Children's Activities | £0 | Children independent |
| **Pension Contributions** | £0 | Stop at retirement |
| **Mortgage Payments** | £0 | Assumed paid off by age 65 |
| **Loan Repayments** | £0 | Assumed paid off by retirement |

### Auto-Adjustments - Widowed Budget

Single-person household adjustments:

| Category | Adjustment | Reason |
|----------|------------|--------|
| Food & Groceries | 60% of household | Single person meals |
| Transport & Fuel | 60% of household | One car/person |
| Mobile Phones | 50% of household | One contract |
| Subscriptions | 70% of household | Fewer shared subscriptions |
| Clothing & Personal Care | 50% of household | Single person |
| Entertainment & Dining | 60% of household | Dining alone less |
| Holidays & Travel | 70% of household | Single traveller |
| Childcare | 130% of household | May need additional care |
| **Property Expenses** | 100% | Full costs continue |
| **Protection Premiums** | User's only | Spouse policies no longer needed |

### Features

1. **View/Edit Mode**: Each budget tab has view and edit modes
2. **Auto/Custom Badges**: Fields show "Auto" badge for automatic adjustments, "Custom" for user overrides
3. **Reset Button**: In edit mode, users can reset individual fields to auto-calculated values
4. **Auto-Adjustments Summary**: Blue info box shows all automatic adjustments applied
5. **Monthly Reduction**: Widowed budget shows how much less is needed vs current budget

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/UserProfile/ExpenditureForm.vue
```

### Changes Made

1. Added tab navigation with three buttons (Current, Retired, Widowed)
2. Added `activeBudgetTab` ref to manage active tab state
3. Added `retiredBudgetData` and `widowedBudgetData` refs for storing adjusted values
4. Added `retiredBudgetOverrides` and `widowedBudgetOverrides` for user modifications
5. Added `retirementInfo` computed property to calculate user/spouse retirement ages
6. Added auto-adjustment rules for both budgets
7. Added edit/save/cancel functions for both budgets
8. Added CSS grids for retired and widowed budget layouts

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

**Current Budget Tab:**
1. Verify existing functionality works unchanged

**Retired Budget Tab:**
1. Navigate to User Profile → Expenditure tab → Retired Budget
2. Verify retirement age is displayed in header (defaults to 65 if not set)
3. Verify "Automatic Adjustments Applied" blue box shows list of adjustments
4. Verify grid shows Current vs Retired values for each category
5. Verify "Auto" badges appear on auto-adjusted fields
6. Click Edit and modify a value
7. Verify "Custom" badge appears and reset button shows
8. Click reset button and verify field reverts to auto-calculated value
9. Click Save and verify changes persist
10. Verify pension contributions show £0
11. Verify mortgage payments show £0

**Widowed Budget Tab:**
1. Navigate to User Profile → Expenditure tab → Widowed Budget (married users only)
2. Verify "Automatic Adjustments Applied" blue box shows list of adjustments
3. Verify grid shows Current (Joint) vs Widowed values
4. Verify "Monthly Reduction" green box shows savings calculation
5. Test edit mode similar to Retired Budget
6. Verify single users do not see this tab

---

## Expenditure Tab - Budget Tabs Color & Layout Fixes

**Date:** 28 January 2026

**Branch:** userProReformat

**Status:** ✅ Deployed to production

### Description

Fixed forbidden color usage and improved column layouts for the Retired and Widowed budget tabs per design system requirements.

### Color Fixes

Removed all forbidden colors (amber/mustard/purple) and replaced with design system colors:

| Old Color | New Color | Usage |
|-----------|-----------|-------|
| `text-amber-600` | `text-primary-600` | Auto-adjusted labels, joint indicators |
| `bg-amber-100` | `bg-primary-100` | Auto badge background |
| `bg-purple-50` | `bg-primary-50` | Widowed info banner |
| `text-purple-*` | `text-primary-*` | Widowed tab elements |
| `bg-purple-100` | `bg-primary-100` | Auto badge in widowed |

### Layout Improvements

**Retired Budget:**
- Changed columns from "Category | Current | Retired | Household" to "Category | userName | spouseName"
- Added change indicators: green `-£X` for savings, red `+£X` for increases
- Removed redundant "Current" column - changes shown inline with indicators
- Added summary banner showing total monthly savings

**Widowed Budget:**
- Changed columns from "Category | Current (Joint) | Widowed" to "Category | userName"
- Single value column showing widowed amount with change indicators
- Removed redundant "Current (Joint)" column
- Added summary banner showing monthly reduction

### Helper Functions Added

Added JavaScript helper functions for budget calculations:

```javascript
// Retired Budget helpers
getRetiredUserValue(key)    // User's retired value with adjustment
getRetiredSpouseValue(key)  // Spouse's retired value with adjustment
getRetiredChange(key, isSpouse) // Change from current to retired

// Widowed Budget helpers
getWidowedValue(key)        // Widowed value for field
getWidowedChange(key)       // Change from household to widowed
```

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/UserProfile/ExpenditureForm.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

**Retired Budget Tab:**
1. Verify NO amber/mustard colors anywhere
2. Verify columns show user name and spouse name (not "Current"/"Retired")
3. Verify change indicators: green for savings, red for increases
4. Verify "Auto" badges are blue (primary-100)
5. Verify "Custom" badges are green (success-100)
6. Verify summary banner uses success colors (green)

**Widowed Budget Tab:**
1. Verify NO purple colors anywhere
2. Verify single column shows user's name only
3. Verify change indicators appear next to each value
4. Verify info banner uses primary colors (blue)
5. Verify summary banner uses success colors (green)

---

## Expenditure Tab - Budget Tabs Visual Polish

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Deployed to production

### Description

Final visual polish for the Retired and Widowed budget tabs to ensure proper rendering of colored boxes and improved alignment.

### Fixes Applied

| Issue | Fix |
|-------|-----|
| Auto-Adjustments box not rendering green | Changed from `bg-success-*` to `bg-green-*` (standard Tailwind classes) |
| Column headers (Chris, Angela, Household) centered | Changed to left-aligned to match value columns |
| Widowed TOTAL MONTHLY showing redundant bracketed change | Removed bracketed number (shown below in "Monthly Reduction" row) |

### Color Class Changes

The `success-*` color classes weren't rendering properly. Changed to standard Tailwind `green-*` classes:

| Old Class | New Class |
|-----------|-----------|
| `bg-success-50` | `bg-green-50` |
| `border-success-200` | `border-green-200` |
| `text-success-900` | `text-green-900` |
| `text-success-800` | `text-green-800` |
| `text-success-600` | `text-green-600` |

Applied to both Retired Budget and Widowed Budget "Automatic Adjustments Applied" boxes.

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/UserProfile/ExpenditureForm.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Navigate to User Profile → Expenditure tab → Retired Budget
2. Verify "Automatic Adjustments Applied" box has green background (matching the blue info box above)
3. Verify column headers (Chris, Angela, Household) are left-aligned
4. Navigate to Widowed Budget tab
5. Verify "Automatic Adjustments Applied" box has green background
6. Verify TOTAL MONTHLY row shows only the value (no bracketed change indicator)
7. Verify "Monthly Reduction from Current" row shows the savings amount

---

## Expenditure Tab - Collapsible Sections & Budget Consistency

**Date:** 28 January 2026

**Branch:** userProReformat

**Status:** ✅ Deployed to production

### Description

Added collapsible/accordion sections to all budget tabs and ensured consistent structure across Current, Retired, and Widowed budgets.

### Changes Made

| Change | Description |
|--------|-------------|
| Collapsible sections | Each category (Essential, Communication, Lifestyle, Children, Other) now has an arrow that expands/collapses the items |
| Sections start collapsed | All sections collapsed by default, user clicks to expand |
| Category totals always visible | Even when collapsed, the category totals show in the header row |
| Manual Expenditure Total | Added to Retired and Widowed budgets (was only in Current) |
| Financial Commitments header | Changed from h4 to span for consistent sizing across all tabs |

### Collapsible Section Behaviour

- Arrow next to category heading (rotates 90° when expanded)
- Category totals always visible in the grid columns
- Items indented when expanded (pl-7)
- Clicking header row toggles expansion

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/UserProfile/ExpenditureForm.vue
```

### Computed Properties Added

```javascript
retiredManualExpenditureTotal    // User's retired manual expenditure (excluding commitments)
retiredSpouseManualExpenditureTotal  // Spouse's retired manual expenditure
retiredHouseholdManualExpenditureTotal  // Household total
widowedManualExpenditureTotal    // Widowed manual expenditure (excluding commitments)
```

### Bug Fix

Fixed `isMarried.value` reference error in `getRetiredSectionTotal` - changed to `props.isMarried`.

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Navigate to User Profile → Expenditure tab → Current Budget
2. Verify all category sections are collapsed by default
3. Click arrow next to "Essential Living" - verify items expand below
4. Verify category totals remain visible in header row when collapsed
5. Navigate to Retired Budget tab
6. Verify "Manual Expenditure Total" row appears before Financial Commitments
7. Verify same collapsible behaviour as Current Budget
8. Navigate to Widowed Budget tab
9. Verify "Manual Expenditure Total" row appears before Financial Commitments
10. Verify Financial Commitments header is same size as other category headers

---

## Income Tab - Side-by-Side Layout & Inline Disposable Income

**Date:** 28 January 2026

**Branch:** userProReformat

**Status:** ✅ Deployed to production

### Description

Redesigned the Income tab to display Income and Tax & NI calculations side-by-side, and moved disposable income to be inline with income totals.

### Changes Made

| Change | Description |
|--------|-------------|
| Side-by-side layout | Income card and Tax & NI card now display in two columns on larger screens |
| Inline disposable income | Net Income, Annual Expenditure, and Disposable Income shown as line items below Total Annual Income |
| Removed separate card | Disposable Income card section removed (content moved inline) |
| Simplified view mode | Income list changed from two-column to single-column layout (fits narrower card) |

### Layout Structure

```
┌─────────────────────────────────────────────────────────┐
│  ┌─────────────────────┐  ┌─────────────────────────┐   │
│  │ Income              │  │ Tax & NI                │   │
│  │ ─────────────────── │  │ ─────────────────────── │   │
│  │ Employment: £X      │  │ [TaxIncomeCard]         │   │
│  │ Self-Employment: £X │  │ [TaxIncomeCard]         │   │
│  │ ...                 │  │                         │   │
│  │ ─────────────────── │  │ Note: Tax calculations  │   │
│  │ Total Income: £X    │  │ use 2025/26 rates...    │   │
│  │ ─────────────────── │  └─────────────────────────┘   │
│  │ Net Income: £X      │                                │
│  │ Expenditure: £X     │                                │
│  │ Disposable: £X      │                                │
│  └─────────────────────┘                                │
└─────────────────────────────────────────────────────────┘
```

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/UserProfile/IncomeOccupation.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Navigate to User Profile → Income tab
2. Verify Income card and Tax & NI card are side-by-side on desktop
3. Verify Tax & NI card stacks below on mobile
4. Below "Total Annual Income", verify these line items appear:
   - Net Income (after tax)
   - Annual Expenditure
   - Disposable Income (green if positive, red if negative)
5. Verify no separate "Disposable Income" card exists below

---

## Tab Reorganisation - User Profile & Valuable Info

**Date:** 28 January 2026

**Branch:** userProReformat

**Status:** ✅ Deployed to production

### Description

Reorganised tabs between User Profile and Valuable Information pages for better information architecture.

### Changes Made

| Change | From | To |
|--------|------|-----|
| Income tab | User Profile | Valuable Info |
| Expenditure tab | User Profile | Valuable Info |
| Balance Sheet tab | Valuable Info | **Removed** |
| Income Statement/Cash Flow tab | Valuable Info | **Removed** |

### Final Tab Structure

**User Profile:**
- Personal Info
- Health
- Family

**Valuable Information:**
- Letter to Spouse / Expression of Wishes
- Will
- Income
- Expenditure
- Risk Profile

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/views/UserProfile.vue
resources/js/views/ValuableInfo.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Navigate to User Profile
2. Verify only 3 tabs: Personal Info, Health, Family
3. Navigate to Valuable Information
4. Verify 5 tabs: Letter to Spouse, Will, Income, Expenditure, Risk Profile
5. Verify Balance Sheet and Income Statement tabs no longer exist
6. Test Income and Expenditure tabs function correctly in new location

---

## View/Edit Mode for Letter to Spouse & Will Planning

**Date:** 28 January 2026

**Branch:** userProReformat

**Status:** ✅ Deployed to production

### Description

Applied the same view/edit mode pattern used in User Profile to the Letter to Spouse and Will Planning tabs in Valuable Information.

### Letter to Spouse Changes

| Section | View Mode | Edit Mode |
|---------|-----------|-----------|
| Part 1: What to Do Immediately | Plain text display of contacts and checklists | Input fields for all editable data |
| Part 2: Financial Overview | Auto-populated (unchanged) | Auto-populated (unchanged) |
| Part 3: Additional Information | Plain text display | Input fields |
| Part 4: Funeral and Final Wishes | Plain text display | Input fields |

**Features:**
- Edit button in header to enter edit mode
- Cancel button to revert unsaved changes
- Save button commits changes
- Spouse view toggle remains functional (read-only when viewing spouse's letter)

### Will Planning Changes

| Section | View Mode | Edit Mode |
|---------|-----------|-----------|
| Has Will question | Radio buttons (always interactive) | Radio buttons (always interactive) |
| Will Configuration | Formatted display (date, executor, spouse %) | Form inputs |
| Bequests | List with Add/Edit/Delete (unchanged) | List with Add/Edit/Delete (unchanged) |

**Features:**
- Edit button in Will Configuration header
- Cancel button to revert unsaved changes
- Formatted date display (e.g., "28 January 2026")
- Spouse percentage shown as summary text

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/UserProfile/LetterToSpouse.vue
resources/js/components/Estate/WillPlanning.vue
resources/js/views/UserProfile.vue
resources/js/views/ValuableInfo.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

**Letter to Spouse:**
1. Navigate to Valuable Information → Letter to Spouse tab
2. Verify all sections display as plain text (no input boxes)
3. Click Edit button
4. Verify input fields appear for Parts 1, 3, 4
5. Make a change, click Cancel - verify change is reverted
6. Make a change, click Save - verify change is persisted
7. If married, test spouse view toggle still works

**Will Planning:**
1. Navigate to Valuable Information → Will tab
2. Select "Yes, I have a will"
3. Verify Will Configuration shows plain text (date, executor name)
4. Click Edit button
5. Verify form inputs appear
6. Make a change, click Cancel - verify change is reverted
7. Make a change, click Save - verify change is persisted

---

## Expenditure Form - Conditional Edit Mode & Budget Tabs

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Deployed to production

### Description

Added props to ExpenditureForm component to control behaviour based on context:

1. **`startInEditMode`** - Controls whether the form loads in edit mode or view mode
2. **`showBudgetTabs`** - Controls whether Retired and Widowed budget tabs are shown

| Context | Edit Mode | Budget Tabs |
|---------|-----------|-------------|
| Onboarding | Yes (edit immediately) | No (Current only) |
| Valuable Info | No (view mode) | Yes (all tabs) |

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/UserProfile/ExpenditureForm.vue
resources/js/components/Onboarding/steps/ExpenditureStep.vue
```

### Changes Made

| File | Change |
|------|--------|
| ExpenditureForm.vue | Added `startInEditMode` prop (default: `false`), `showBudgetTabs` prop (default: `true`) |
| ExpenditureStep.vue | Added `:start-in-edit-mode="true"` and `:show-budget-tabs="false"` props |

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

**Onboarding:**
1. Start new onboarding flow
2. Navigate to Household Expenditure step
3. Verify form loads in edit mode (input fields visible immediately)
4. Verify NO budget tabs shown (no Current/Retired/Widowed tabs)

**Valuable Info:**
1. Navigate to Valuable Information → Expenditure tab
2. Verify form loads in view mode (values displayed as text)
3. Verify budget tabs ARE shown (Current, Retired, Widowed for married users)
4. Click Edit button and verify form switches to edit mode

---

## Retired Budget - Section Totals Fix

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Deployed to production

### Description

Fixed the Retired Budget tab where the Angela (spouse) and Household columns were showing blank values for category section totals (Essential Living, Communication, etc.).

### Issue

The section header rows were only showing the user's total in the Chris column. The Angela and Household columns were empty because the template was using `getRetiredSectionTotal()` (which returned the household total) in the wrong column.

### Fix

Added separate functions for calculating section totals:

| Function | Returns |
|----------|---------|
| `getRetiredUserSectionTotal(section)` | User's section total only |
| `getRetiredSpouseSectionTotal(section)` | Spouse's section total only |
| `getRetiredSectionTotal(section)` | Household total (user + spouse) |

Updated all 5 section headers (Essential, Communication, Lifestyle, Children, Other) to use the correct function for each column.

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/UserProfile/ExpenditureForm.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Navigate to Valuable Information → Expenditure → Retired Budget tab
2. Verify all three columns (Chris, Angela, Household) show values for each category section
3. Verify the totals add up correctly (Chris + Angela = Household)

---

## Budget Adjustments - Wording Change

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Deployed to production

### Description

Changed the wording in the "Automatic Adjustments Applied" info box from "Reduced to X%" to "Reduced by Y%".

### Changes

**Retired Budget adjustments:**
| Category | Old Wording | New Wording |
|----------|-------------|-------------|
| Transport & Fuel | Reduced to 40% | Reduced by 60% |
| Clothing & Personal Care | Reduced to 70% | Reduced by 30% |

**Widowed Budget adjustments:**
| Category | Old Wording | New Wording |
|----------|-------------|-------------|
| Food & Groceries | Reduced to 60% | Reduced by 40% |
| Transport & Fuel | Reduced to 60% | Reduced by 40% |
| Mobile Phones | Reduced to 50% | Reduced by 50% |
| Subscriptions | Reduced to 70% | Reduced by 30% |
| Clothing & Personal Care | Reduced to 50% | Reduced by 50% |
| Entertainment & Dining | Reduced to 60% | Reduced by 40% |
| Holidays & Travel | Reduced to 70% | Reduced by 30% |

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/UserProfile/ExpenditureForm.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Navigate to Valuable Information → Expenditure → Retired Budget tab
2. Check "Automatic Adjustments Applied" box shows "Reduced by X%" wording
3. Navigate to Widowed Budget tab
4. Check "Automatic Adjustments Applied" box shows "Reduced by X%" wording

---

## Will Planning - Unified Card & View/Edit Mode

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Deployed to production

### Description

Redesigned the Will Planning tab in Valuable Info to use a single unified card with proper view/edit mode separation.

### Changes

- Removed separate "Will Status" and "Will Configuration" cards
- Combined everything into one "Will Planning" card
- **View mode**: Shows will last updated, executor, spouse beneficiary info, and bequests section
- **Edit mode**: Shows "Do you have a will?" Yes/No question plus all form fields
- Added `startInEditMode` prop for potential onboarding use

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/Estate/WillPlanning.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Navigate to Valuable Information → Will tab
2. Verify single "Will Planning" card is shown
3. In view mode, verify last updated date, executor, and bequests are visible
4. Click Edit, verify "Do you have a will?" question appears with form fields
5. Save changes and verify view mode returns

---

## Income Step - Dividend & Interest Income Fields

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Deployed to production

### Description

Added Dividend Income and Interest Income fields to the onboarding Income step, making these always visible so users can enter them during onboarding or through the Valuable Info Income tab.

### Changes

- Added `annual_dividend_income` field with description "Income from shares and investments"
- Added `annual_interest_income` field with description "Interest from savings accounts and bonds"
- Both fields included in total income calculation
- Fields visible when employment status is selected

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/Onboarding/steps/IncomeStep.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

**Onboarding:**
1. Start onboarding flow
2. Navigate to Employment & Income step
3. Select any employment status
4. Verify Dividend Income and Interest Income fields are visible
5. Enter values and verify they're included in Total Annual Income

**Valuable Info:**
1. Navigate to Valuable Information → Income tab
2. Click Edit
3. Verify Dividend Income and Interest Income fields are visible and editable

---

## Expenditure Onboarding - Skip Modal & UX Improvements

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Deployed to production

### Description

Fixed bug where the expenditure form reverted to view mode when clicking continue without data, and improved the UX for skipping the expenditure section during onboarding.

### Issues Fixed

| Issue | Fix |
|-------|-----|
| Form reverts to view mode when continue clicked without data | Added `isOnboarding` prop that prevents `isEditing` from being set to false during onboarding |
| Cancel/Save buttons shown during onboarding | Buttons now hidden when `isOnboarding` prop is true |
| Red error message when no data entered | Replaced with informative modal explaining what expenditure data is used for |

### Skip Modal Content

When user clicks Continue without entering any expenditure data, a modal now appears explaining:

- Expenditure data is used for affordability assessments and budget planning
- Risk tolerance evaluation
- Investment, retirement and savings strategies
- Inheritance tax planning
- Protection needs analysis
- User can add this information later through Valuable Info section

### Props Added

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `isOnboarding` | Boolean | false | Controls onboarding-specific behaviour |

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/UserProfile/ExpenditureForm.vue
resources/js/components/Onboarding/steps/ExpenditureStep.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Start new onboarding flow
2. Navigate to Household Expenditure step
3. Without entering any data, click Continue
4. Verify modal appears explaining what expenditure data is used for
5. Click "Go Back" and verify form remains in edit mode
6. Click Continue again, then click "Skip & Continue"
7. Verify onboarding proceeds to next step
8. Verify no Cancel/Save buttons at bottom of expenditure form during onboarding

---

---

## Employment Status & Retirement Age Save Fix

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Deployed to production

### Description

Fixed bug where changing employment status (e.g., self-employed to employed) and target retirement age wasn't saving correctly through the Personal Information form.

### Root Cause

The `UpdateIncomeOccupationRequest` validation was missing several fields, causing them to be filtered out before reaching the service:
- `target_retirement_age`
- `retirement_date`
- `part_time` employment status
- `annual_interest_income`
- `annual_trust_income`

### Files Changed

**Backend:**
```text
app/Http/Requests/UpdateIncomeOccupationRequest.php
```

### Rebuild Required: NO (PHP only)

### Verification

1. Log in and go to Valuable Info > Personal Information
2. Change employment status from self-employed to employed
3. Change target retirement age
4. Save changes
5. Refresh the page and verify changes persisted

---

## SOC 2020 Occupation Autocomplete

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Deployed to production

### Description

Implemented occupation autocomplete using ONS Standard Occupational Classification (SOC) 2020 codes. When users type 3+ characters in the occupation field, they see matching job titles from the official UK classification system.

### Source

[ONS SOC 2020](https://www.ons.gov.uk/methodology/classificationsandstandards/standardoccupationalclassificationsoc/soc2020)

### Database Changes

New table: `occupation_codes`
- `id` - Primary key
- `soc_code` - SOC 2020 4-digit unit group code
- `title` - Job title/occupation name
- `unit_group` - SOC 2020 unit group description
- `minor_group` - SOC 2020 minor group (3-digit)
- `sub_major_group` - SOC 2020 sub-major group (2-digit)
- `major_group` - SOC 2020 major group (1-digit)
- `is_primary` - Is this the primary title for the SOC code

### Files Changed

**Backend:**
```text
database/migrations/2026_01_28_000001_create_occupation_codes_table.php
database/seeders/OccupationCodeSeeder.php
app/Models/OccupationCode.php
app/Http/Controllers/Api/OccupationController.php
routes/api.php
```

**Frontend (Included in Build):**
```text
resources/js/services/occupationService.js
resources/js/components/Shared/OccupationAutocomplete.vue
resources/js/components/Onboarding/steps/IncomeStep.vue
resources/js/components/UserProfile/PersonalInformation.vue
```

### Server Commands Required

```bash
# Run migration
php artisan migrate

# Seed occupation codes
php artisan db:seed --class=OccupationCodeSeeder
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Start onboarding and go to Employment & Income step
2. In the Occupation field, type "soft" (3 characters)
3. Verify autocomplete dropdown appears with matching occupations
4. Select "Software Developer" from the list
5. Verify field is populated
6. Test in Personal Information tab as well

---

## Employment Status Change - Income Update Notification

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Deployed to production

### Description

When users change their employment status (e.g., from self-employed to employed, or to retired), the system now:
1. Resets the relevant income field to £0
2. Shows a notification banner in the Income tab
3. Displays a warning in the "What Powers This View" panel
4. Stores the previous employment status for reference

### Behaviour

| Status Change | Action |
|---------------|--------|
| Employed → Self-Employed | Reset employment income to £0 |
| Self-Employed → Employed | Reset self-employment income to £0 |
| Employed/Self-Employed → Retired | Reset both employment and self-employment income to £0 |
| Retired → Employed/Self-Employed | No automatic reset (user starts fresh) |

### Notification Banner

An amber notification banner appears in the Income tab when `income_needs_update` is true:
- Shows previous employment status
- Links directly to edit mode
- Clears automatically when user saves income changes

### Info Guide Integration

The "What Powers This View" panel now includes "Income needs updating" as a requirement that shows as "missing" when employment status has changed and income hasn't been updated.

### Database Changes

New columns in `users` table:
- `income_needs_update` (boolean, default: false) - Flag indicating income needs updating
- `previous_employment_status` (string, nullable) - Previous status before change

### Files Changed

**Backend:**
```text
database/migrations/2026_01_28_100000_add_income_needs_update_to_users_table.php
app/Http/Requests/UpdateIncomeOccupationRequest.php
app/Services/UserProfile/ModuleDataRequirementsService.php
```

**Frontend (Included in Build):**
```text
resources/js/components/UserProfile/PersonalInformation.vue
resources/js/components/UserProfile/IncomeOccupation.vue
resources/js/store/modules/userProfile.js
```

### Server Commands Required

```bash
# Run migration
php artisan migrate
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Go to User Profile > Personal Information
2. Note current employment status (e.g., "Self-Employed")
3. Change to "Employed" and save
4. Verify success message mentions updating income
5. Navigate to Valuable Info > Income tab
6. Verify amber notification banner appears
7. Verify previous status shows in banner
8. Click "Update income now" link
9. Enter new income value and save
10. Verify notification banner disappears
11. Click the info guide (?) button
12. Verify "Income needs updating" no longer appears as missing

---

## Personal Information - Layout Alignment Fix

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Deployed to production

### Description

Fixed layout alignment in Personal Information view mode so that Occupation and Domicile Status headings are aligned on the same row.

### Layout Change

**Before:**
- Left column: Personal Details, Occupation (stacked)
- Right column: Address, Domicile Status (stacked)
- Domicile Status appeared higher than Occupation due to Address having fewer fields

**After:**
- Row 1: Personal Details | Address
- Row 2: Occupation | Domicile Status (aligned)

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/UserProfile/PersonalInformation.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

---

## Income Tab - Tax Calculation Refresh Fix

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Deployed to production

### Description

Fixed bug where the Tax & NI calculation card would disappear after saving income changes and only reappear on page reload.

### Root Cause

The `updateIncomeOccupation` store action only returned basic user data, not the recalculated `detailed_tax_breakdown`. The tax breakdown is computed server-side and only included when the full profile is fetched.

### Fix

Added a call to `fetchProfile` after saving income changes to retrieve the complete profile data including the recalculated tax breakdown.

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/UserProfile/IncomeOccupation.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Navigate to Valuable Info → Income tab
2. Click Edit and change an income value
3. Click Save Changes
4. Verify Tax & NI card remains visible with updated calculations

---

## Employment Status "Retired" - Remove Retirement Date Field

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Deployed to production

### Description

Removed the Retirement Date field that appeared when selecting "Retired" as employment status. Now when "Retired" is selected:
- Target Retirement Age field is hidden (existing behaviour)
- Retirement Date field no longer appears (removed)

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/UserProfile/PersonalInformation.vue
resources/js/components/Onboarding/steps/IncomeStep.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

---

## Net Worth Wealth Summary - UI Redesign

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Ready for deployment

### Description

Redesigned the Net Worth Wealth Summary view with cleaner layout, personalised column headers, and inline asset allocation charts.

### Changes Made

| Change | Description |
|--------|-------------|
| Removed top summary cards | Total Assets, Total Liabilities, Net Worth cards removed from top |
| Added Total column | Wealth Summary table now shows Total column when linked account exists |
| Personalised headers | Column headers now show user's name and linked account name instead of generic "Spouse" |
| Removed trend chart | Net Worth Trend chart removed from bottom of view |
| Added allocation charts | Three inline Asset Allocation donut charts: User's, Partner's, Combined |
| Fixed tooltip clipping | Tooltips now appear above card edges instead of being hidden |

### Asset Allocation Charts

Three donut charts now display inline:

1. **User's Asset Allocation** - Individual breakdown for primary user
2. **Partner's Asset Allocation** - Individual breakdown for linked account (if exists)
3. **Combined Wealth Allocation** - Total of both users' assets

Charts include:
- Personalised titles with user names
- Total value displayed in centre
- Percentage shown on hover tooltip
- Hover z-index ensures tooltips appear above adjacent cards

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/NetWorth/NetWorthWealthSummary.vue
resources/js/components/NetWorth/WealthSummary.vue
resources/js/components/NetWorth/AssetAllocationDonut.vue
resources/js/views/NetWorth/NetWorthDashboard.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Navigate to Net Worth → Wealth Summary
2. Verify top summary cards are no longer present
3. Verify Wealth Summary table shows user name and partner name as column headers
4. Verify Total column appears when linked account exists
5. Verify Net Worth Trend chart is no longer present
6. Verify three Asset Allocation cards appear inline:
   - Left: User's Asset Allocation
   - Centre: Partner's Asset Allocation
   - Right: Combined Wealth Allocation
7. Hover over chart segments and verify tooltips appear fully (not clipped at card edges)
8. For single users, verify only one allocation card appears

---

## Investment Account Form - Remove Tax Year

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Ready for deployment

### Description

Removed unnecessary Tax Year dropdown from Investment account add/edit forms.

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/Investment/AccountForm.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Navigate to Net Worth → Investments tab
2. Click "Add Account" - verify no Tax Year dropdown appears
3. Edit an existing account - verify no Tax Year dropdown appears

---

## Investment Account Contributions - Save Fix

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Ready for deployment

### Description

Fixed bug where monthly contributions and planned lump sums were not saving when adding or editing investment accounts. The controller validation was missing these fields, causing them to be filtered out before saving.

### Fields Added to Validation

- `monthly_contribution_amount` - Regular contribution amount
- `contribution_frequency` - monthly/quarterly/annually
- `planned_lump_sum_amount` - One-off planned contribution
- `planned_lump_sum_date` - Date for lump sum
- `country` - Account country
- `risk_preference` - Account risk level

Also changed `tax_year` from required to nullable.

### Files Changed

**Backend (Manual Upload Required):**
```text
app/Http/Controllers/Api/InvestmentController.php
```

### Rebuild Required: NO (PHP only)

### Verification

1. Navigate to Net Worth → Investments tab
2. Edit an existing account
3. Add a monthly contribution (e.g., £500 monthly)
4. Add a planned lump sum (e.g., £5,000 on a future date)
5. Save the account
6. Re-open the account and verify contributions are saved
7. Check Valuable Info → Expenditure → Financial Commitments to verify investment contributions appear

---

## Investment Detail View - Monthly Contribution Display

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Ready for deployment

### Description

Fixed the Monthly Contribution card in the investment detail view to show the actual contribution amount instead of estimating from YTD contributions.

### Changes

- Now uses `monthly_contribution_amount` if set
- Converts to monthly based on frequency (quarterly ÷ 3, annually ÷ 12)
- Falls back to YTD estimate only if no contribution is configured

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/NetWorth/InvestmentDetailInline.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Edit an investment account and set monthly contribution to £500
2. Save and view the account detail
3. Verify "Monthly Contribution" card shows £500

---

## Investment Projections - Total Fees Label

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Ready for deployment

### Description

Updated the fees card in Investment Projections view:
- Removed "TER" badge from fees display
- Changed heading from "Fees" to "Total Fees"

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/NetWorth/InvestmentProjections.vue
```

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Navigate to Net Worth → Investments → click on Portfolio tab
2. Verify fees card shows "Total Fees" heading (not "Fees")
3. Verify no "TER" badge appears

---

## Tax Efficiency Calculation Fix

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Ready for deployment

### Description

Fixed the Tax Efficiency score calculation in Investment Projections. Previously, the score was showing 90%+ even when users had 50%+ of their portfolio in taxable accounts. The calculation now properly penalises high taxable proportions.

### Root Cause

The `TaxEfficiencyCalculator.php` only checked ISA percentage and gave a bonus for >50% ISA usage. It didn't properly reflect having significant assets in taxable accounts (GIA).

### Changes

Simplified the scoring to be intuitive and meaningful:
- **Tax Efficiency = Percentage of assets in tax-sheltered accounts**
- Tax-sheltered types: ISA, Stocks & Shares ISA, LISA, SIPP, Pension
- 100% in ISA/SIPP = 100% tax efficiency
- 0% in tax-sheltered = 0% tax efficiency

### Example Scores

| Tax-Sheltered | Taxable (GIA) | Score |
|---------------|---------------|-------|
| £150,000 (45%) | £185,000 (55%) | 45% |
| £300,000 (90%) | £35,000 (10%) | 90% |
| £0 (0%) | £100,000 (100%) | 0% |

### Files Changed

**Backend (Manual Upload Required):**
```text
app/Services/Investment/TaxEfficiencyCalculator.php
```

### Rebuild Required: NO (PHP only)

### Verification

1. Navigate to Net Worth → Investments → Portfolio tab
2. View Tax Efficiency card
3. Score should equal the percentage of assets in tax-sheltered accounts
4. With £150k ISA and £185k GIA (total £335k), score should be 45% (150k/335k)

---

## Cash Account Detail View - UI Improvements

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Ready for deployment

### Description

Streamlined the Cash account detail view by consolidating information and removing unnecessary tabs.

### Changes Made

| Change | Description |
|--------|-------------|
| Added owner names | Joint accounts now show both owner names in the Overview tab |
| Added interest rate to Overview | Interest rate and annual interest now shown in Overview tab |
| Removed Balance & Interest tab | Consolidated into Overview tab |
| Conditional Access & Terms tab | Tab only appears for notice/fixed accounts, hidden for immediate access |
| Compact balance display | Replaced 3-card metrics grid with compact inline balance display |
| Removed header metrics | Interest rate and annual interest cards removed from header (now in Overview only) |

### Files Changed

**Backend (Manual Upload Required):**
```text
app/Http/Controllers/Api/SavingsController.php
app/Models/SavingsAccount.php
```

**Frontend (Included in Build):**
```text
resources/js/views/Savings/SavingsAccountDetailInline.vue
```

### Backend Changes

1. Added `jointOwner` relationship to SavingsAccount model
2. Controller now loads owner and joint_owner relationships when fetching account
3. Response includes `owner_name` and `joint_owner_name` fields

### Rebuild Required: YES

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Navigate to Net Worth → Cash tab
2. Click on any account to view details
3. Verify Overview tab shows:
   - Interest Rate (with blue styling)
   - Annual Interest (with green styling)
   - For joint accounts: "Owners: [Name] & [Partner Name]"
4. Verify Balance & Interest tab no longer exists
5. For immediate access accounts, verify Access & Terms tab is hidden
6. For notice/fixed term accounts, verify Access & Terms tab is visible

---

## Monte Carlo Projection Caching (24 Hours)

**Date:** 28 January 2026

**Branch:** main

**Status:** ✅ Ready for deployment

### Description

Added 24-hour caching to all Monte Carlo projection calculations for investments and pensions. Previously, these calculations ran on every page load, causing slow loading times. Now projections are cached per user/account and only recalculate after 24 hours.

### What's Cached

| Data Type | Cache Key Pattern | TTL |
|-----------|-------------------|-----|
| Investment Portfolio Projections | `investment_portfolio_projections_{userId}` | 24 hours |
| Investment Account Projections | `investment_account_projections_{accountId}` | 24 hours |
| Retirement Projections | `retirement_projections_{userId}` | 24 hours |
| DC Pension Projections | `dc_pension_projection_{pensionId}` | 24 hours |

### Behaviour

- First visit: Monte Carlo runs and caches results
- Subsequent visits within 24 hours: Cached results returned instantly
- After 24 hours: Cache expires, fresh calculation on next visit
- What-if scenarios (risk overrides): Always bypass cache

### Cache Invalidation Methods

Added helper methods to invalidate cache when data changes:
- `InvestmentProjectionService::invalidateUserProjections($userId)`
- `InvestmentProjectionService::invalidateAccountProjections($accountId)`
- `RetirementProjectionService::invalidateRetirementProjections($userId)`
- `RetirementProjectionService::invalidateDCPensionProjection($pensionId)`

### Files Changed

**Backend (Manual Upload Required):**
```text
app/Services/Investment/InvestmentProjectionService.php
app/Services/Retirement/RetirementProjectionService.php
```

### Rebuild Required: NO (PHP only)

### Verification

1. Navigate to Net Worth → Investments → Portfolio tab
2. Note the projection values displayed
3. Refresh the page - projections should load instantly (cached)
4. Values should remain identical for 24 hours
5. Test same behaviour for:
   - Individual investment account detail views
   - Retirement module projections
   - Individual DC pension detail views

---

## Rollback

If issues occur, restore previous `public/build/` directory from backup.

---
