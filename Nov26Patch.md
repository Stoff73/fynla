# Fynla v0.2.15 Patch - November 26, 2025

## Overview

This patch focuses on documentation updates and codebase consistency fixes.

---

## Changes Made

### 1. CLAUDE.md Comprehensive Update

**Description**: Complete overhaul of CLAUDE.md to reflect current application state.

**Key Updates**:
- Updated version to v0.2.14 (from v0.2.13)
- Updated architecture statistics:
  - Components: 174 (from 150+)
  - API Routes: 378 (from 80+)
  - Services: 63 (from 40+)
  - Database Tables: 50+ (from 45+)

**Canonical Types Added/Updated**:
- Ownership Types: Added `tenants_in_common` (now 4 types)
- Liability Types: Expanded documentation to 9 types with critical field name notes
- Investment Account Types: Added `other` type (now 8 types)
- Holding Asset Types: Complete list of 10 types
- Disability Policy Coverage Types: Added (2 types)
- Premium/Benefit Frequency Types: Complete reference with usage context
- DC Pension Types: Documented both `scheme_type` and `pension_type` fields
- DB Pension Types: Added with inflation protection options
- Mortgage Types: Corrected to use `mixed` instead of `part_and_part`
- Trust Types: Complete list of 9 types
- Gift Types: 5 types with 7-year rule notes
- Bequest Types: 4 types
- User Profile Enums: Gender, marital status, health status, smoking status, employment status, education level
- Family Member Relationships: 5 types

**File**: `CLAUDE.md`

---

### 2. Mortgage Type Consistency Fix

**Description**: Replaced deprecated `part_and_part` mortgage type with `mixed` across the codebase.

**Background**: The `part_and_part` mortgage type was replaced by `mixed` in migration `2025_11_15_162349_remove_part_and_part_from_mortgage_type_enum.php`, but some files still referenced the old value.

**Files Changed**:

#### 2.1 MortgageController.php
**File**: `app/Http/Controllers/Api/MortgageController.php`
**Line**: 362
**Change**: Added `mixed` to `calculatePayment()` validation

```php
// Before
'mortgage_type' => 'required|in:repayment,interest_only',

// After
'mortgage_type' => 'required|in:repayment,interest_only,mixed',
```

#### 2.2 StorePropertyRequest.php
**File**: `app/Http/Requests/StorePropertyRequest.php`
**Line**: 59
**Change**: Removed `part_and_part`, kept `mixed`

```php
// Before
'mortgage_type' => ['nullable', Rule::in(['repayment', 'interest_only', 'part_and_part', 'mixed'])],

// After
'mortgage_type' => ['nullable', Rule::in(['repayment', 'interest_only', 'mixed'])],
```

#### 2.3 MortgageFactory.php
**File**: `database/factories/MortgageFactory.php`
**Line**: 19
**Change**: Added `mixed` to factory random elements

```php
// Before
$mortgageType = fake()->randomElement(['repayment', 'interest_only']);

// After
$mortgageType = fake()->randomElement(['repayment', 'interest_only', 'mixed']);
```

---

## Files Modified Summary

| File | Type | Change Description |
|------|------|-------------------|
| `CLAUDE.md` | Documentation | Comprehensive update with all canonical types |
| `app/Http/Controllers/Api/MortgageController.php` | Backend | Added `mixed` to calculatePayment validation |
| `app/Http/Requests/StorePropertyRequest.php` | Backend | Removed `part_and_part`, uses `mixed` |
| `database/factories/MortgageFactory.php` | Testing | Added `mixed` to factory |
| `resources/js/components/Savings/SaveAccountModal.vue` | Frontend | Date formatting |
| `resources/js/components/Investment/GoalForm.vue` | Frontend | Date & currency formatting |
| `resources/js/components/Investment/HoldingForm.vue` | Frontend | Date formatting |
| `resources/js/components/Savings/SaveGoalModal.vue` | Frontend | Date formatting |
| `resources/js/components/Onboarding/steps/ProtectionPoliciesStep.vue` | Frontend | Currency formatting |
| `resources/js/components/Onboarding/steps/AssetsStep.vue` | Frontend | Currency formatting |
| `resources/js/components/Onboarding/steps/IncomeStep.vue` | Frontend | Currency formatting |
| `resources/js/components/Onboarding/steps/LiabilitiesStep.vue` | Frontend | Currency formatting |
| `resources/js/components/Retirement/PensionCard.vue` | Frontend | Currency formatting |
| `resources/js/components/Retirement/AnnualAllowanceTracker.vue` | Frontend | Currency formatting |
| `resources/js/components/Retirement/DCPensionForm.vue` | Frontend | Currency formatting |
| `resources/js/components/Retirement/RetirementOverviewCard.vue` | Frontend | Currency formatting |
| `resources/js/views/Retirement/RetirementReadiness.vue` | Frontend | Currency formatting |
| `resources/js/views/Retirement/PensionInventory.vue` | Frontend | Currency formatting |
| `resources/js/views/Retirement/PensionDetail.vue` | Frontend | Currency formatting |
| `resources/js/views/Retirement/DecumulationPlanning.vue` | Frontend | Currency formatting |
| `resources/js/views/Retirement/Projections.vue` | Frontend | Currency formatting |
| `resources/js/views/Retirement/ContributionsAllowances.vue` | Frontend | Currency formatting |
| `resources/js/views/Retirement/WhatIfScenarios.vue` | Frontend | Currency formatting |
| `resources/js/components/Investment/AccountForm.vue` | Frontend | Currency formatting |
| `resources/js/components/Protection/ProtectionOverviewCard.vue` | Frontend | Currency formatting (0 decimals) |
| `resources/js/components/Protection/PolicyCard.vue` | Frontend | Currency formatting (0 decimals) |
| `resources/js/views/Retirement/ContributionsAllowances.vue` | Frontend | Tax year update (2025/26) |
| `resources/js/views/UKTaxes/UKTaxesDashboard.vue` | Frontend | Tax year update (2025/26) |
| `resources/js/components/Shared/ISAAllowanceSummary.vue` | Frontend | Tax year update (2025/26) |
| `resources/js/components/Retirement/AnnualAllowanceTracker.vue` | Frontend | Tax year update (2025/26) |
| `resources/js/components/Retirement/StatePensionForm.vue` | Frontend | Tax year update (2025/26), State Pension rate |
| `resources/js/components/NetWorth/Property/PropertyTaxCalculator.vue` | Frontend | Tax year update (2025/26) |
| `resources/js/components/Investment/TaxFees.vue` | Frontend | Tax year update (2025/26) |
| `resources/js/components/Investment/ISAOptimizationStrategy.vue` | Frontend | Tax year update (2025/26) |
| `resources/js/components/Investment/RebalancingCalculator.vue` | Frontend | Tax year update (2025/26), CGT allowance |
| `resources/js/components/Dashboard/UKTaxesOverviewCard.vue` | Frontend | Tax year update (2025/26) |
| `app/Services/Protection/ComprehensiveProtectionPlanService.php` | Backend | British spelling (Personalised) |
| `app/Services/Estate/ComprehensiveEstatePlanService.php` | Backend | British spelling (Personalised), added children/step-children to profile |
| `resources/js/views/Estate/ComprehensiveEstatePlan.vue` | Frontend | Removed gender, added children/step-children display |
| `resources/js/components/UserProfile/BalanceSheetView.vue` | Frontend | Currency formatting (0 decimals) |
| `resources/js/components/UserProfile/CashflowView.vue` | Frontend | Coming Soon watermark |
| `resources/js/components/UserProfile/ProfitAndLossView.vue` | Frontend | Coming Soon watermark |
| `resources/js/components/UserProfile/PersonalAccounts.vue` | Frontend | Tab reordering, default to Balance Sheet |
| `resources/js/views/Retirement/RetirementDashboard.vue` | Frontend | Removed Pensions tab |
| `resources/js/views/Retirement/RetirementReadiness.vue` | Frontend | Pension card improvements, type badges, field fixes |
| `resources/js/views/Retirement/PortfolioAnalysis.vue` | Frontend | Coming Soon watermark |
| `resources/js/views/Retirement/Recommendations.vue` | Frontend | Coming Soon watermark, British spelling |

---

## Already Correct (No Changes Needed)

These files already had the correct `mixed` mortgage type:
- `app/Http/Requests/StoreMortgageRequest.php`
- `app/Http/Requests/UpdateMortgageRequest.php`
- `resources/js/components/NetWorth/Property/PropertyForm.vue`

---

### 3. Date Formatting Standardisation

**Description**: Added standardised `formatDateForInput()` helper to components with date fields to ensure HTML5 date inputs receive correctly formatted dates (yyyy-MM-dd).

**Files Changed**:

| File | Component Name | Change |
|------|----------------|--------|
| `resources/js/components/Savings/SaveAccountModal.vue` | SaveAccountModal | Added formatDateForInput method |
| `resources/js/components/Investment/GoalForm.vue` | GoalForm | Added formatDateForInput method |
| `resources/js/components/Investment/HoldingForm.vue` | HoldingForm | Added formatDateForInput in watch handler |
| `resources/js/components/Savings/SaveGoalModal.vue` | SaveGoalModal | Added formatDateForInput method |

**Helper Method Added**:
```javascript
formatDateForInput(date) {
  if (!date) return '';
  if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
    return date;
  }
  const dateObj = new Date(date);
  if (isNaN(dateObj.getTime())) return '';
  const year = dateObj.getFullYear();
  const month = String(dateObj.getMonth() + 1).padStart(2, '0');
  const day = String(dateObj.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}
```

---

### 4. Currency Formatting Standardisation

**Description**: Replaced all instances of inline `toLocaleString()` currency formatting with standardised `formatCurrency()` method across Vue components.

**Pattern Applied**:
```javascript
formatCurrency(value) {
  if (value === null || value === undefined) return '£0';
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value);
}
```

**Files Changed (18 files)**:

#### Onboarding Components
| File | Changes |
|------|---------|
| `resources/js/components/Onboarding/steps/ProtectionPoliciesStep.vue` | Added formatCurrency, updated template |
| `resources/js/components/Onboarding/steps/AssetsStep.vue` | Added formatCurrency, 4 template updates |
| `resources/js/components/Onboarding/steps/IncomeStep.vue` | Added formatCurrency, updated totalIncome display |
| `resources/js/components/Onboarding/steps/LiabilitiesStep.vue` | Added formatCurrency, updated balance display |

#### Retirement Components
| File | Changes |
|------|---------|
| `resources/js/components/Retirement/PensionCard.vue` | Added formatCurrency, 6 template updates |
| `resources/js/components/Retirement/AnnualAllowanceTracker.vue` | Added formatCurrency, 7 template updates |
| `resources/js/components/Retirement/DCPensionForm.vue` | Added formatCurrency, 2 calculated contribution displays |
| `resources/js/components/Retirement/RetirementOverviewCard.vue` | Added formatCurrency, 2 template updates |

#### Retirement Views
| File | Changes |
|------|---------|
| `resources/js/views/Retirement/RetirementReadiness.vue` | Updated 3 pension wealth summary displays |
| `resources/js/views/Retirement/PensionInventory.vue` | Added formatCurrency, state pension annual display |
| `resources/js/views/Retirement/PensionDetail.vue` | Updated state pension annual display |
| `resources/js/views/Retirement/DecumulationPlanning.vue` | Added formatCurrency, 6 PCLS displays |
| `resources/js/views/Retirement/Projections.vue` | Added formatCurrency, 2 metric displays |
| `resources/js/views/Retirement/ContributionsAllowances.vue` | Added formatCurrency, 4 contribution displays |
| `resources/js/views/Retirement/WhatIfScenarios.vue` | Added formatCurrency, 7 scenario displays |

#### Investment Components
| File | Changes |
|------|---------|
| `resources/js/components/Investment/AccountForm.vue` | Updated ISA allowance error message |

#### Protection Components
| File | Changes |
|------|---------|
| `resources/js/components/Protection/ProtectionOverviewCard.vue` | Updated formatCurrency (0 decimals), updated formattedPremiumTotal computed |
| `resources/js/components/Protection/PolicyCard.vue` | Updated formatCurrency (0 decimals) |

---

### 5. Tax Year Update (2024/25 → 2025/26)

**Description**: Updated all hardcoded tax year references from 2024/25 to 2025/26 across the application. Historical dropdown options for past tax years were intentionally retained for carry forward calculations and past ISA subscriptions.

**Files Changed (10 files)**:

| File | Change |
|------|--------|
| `resources/js/views/Retirement/ContributionsAllowances.vue` | Updated "Key Thresholds (2024/25)" heading to 2025/26 |
| `resources/js/views/UKTaxes/UKTaxesDashboard.vue` | Updated LTA note, tax_year config, and effective dates |
| `resources/js/components/Shared/ISAAllowanceSummary.vue` | Updated heading and ISA_ALLOWANCE comment |
| `resources/js/components/Retirement/AnnualAllowanceTracker.vue` | Updated default tax year, dropdown options, carry forward years, and API call |
| `resources/js/components/Retirement/StatePensionForm.vue` | Updated full State Pension rate (£221.20/week for 2025/26) and NI gap cost note |
| `resources/js/components/NetWorth/Property/PropertyTaxCalculator.vue` | Updated SDLT rates note |
| `resources/js/components/Investment/TaxFees.vue` | Updated ISA Allowance and CGT headings, and CGT allowance comment |
| `resources/js/components/Investment/ISAOptimizationStrategy.vue` | Updated default tax year fallback |
| `resources/js/components/Investment/RebalancingCalculator.vue` | Updated CGT allowance note (£3,000 for 2025/26) |
| `resources/js/components/Dashboard/UKTaxesOverviewCard.vue` | Updated tax year display |

**Key Tax Value Updates**:
- State Pension: £203.85/week (2024/25) → £221.20/week (2025/26)
- CGT Annual Allowance: £12,300 (old reference) → £3,000 (2025/26)
- Tax year defaults and configs updated throughout

**Note**: The following chart components retain `toLocaleString()` in ApexCharts formatter callbacks, which is the appropriate pattern for chart library configuration:
- `AccumulationChart.vue` (chart axis/tooltip formatters)
- `DrawdownSimulator.vue` (chart axis/tooltip formatters)
- `IncomeProjectionChart.vue` (chart axis/tooltip formatters)

---

### 6. British Spelling Fix (Personalized → Personalised)

**Description**: Updated plan type labels to use British spelling as per CLAUDE.md conventions. User-facing text should use British English spelling.

**Files Changed (2 PHP services)**:

| File | Line(s) | Change |
|------|---------|--------|
| `app/Services/Protection/ComprehensiveProtectionPlanService.php` | 61 | `'Personalized'` → `'Personalised'` |
| `app/Services/Protection/ComprehensiveProtectionPlanService.php` | 440-441 | `'Personalized'` → `'Personalised'`, `'Mostly Personalized'` → `'Mostly Personalised'` |
| `app/Services/Estate/ComprehensiveEstatePlanService.php` | 130 | `'Personalized'` → `'Personalised'` |

---

### 7. Estate Plan Profile Section Enhancement

**Description**: Improved the "Your Profile" section in the Comprehensive Estate Plan by removing gender field and adding children/step-children display.

**Changes**:

#### 7.1 Frontend - ComprehensiveEstatePlan.vue
**File**: `resources/js/views/Estate/ComprehensiveEstatePlan.vue`
**Lines**: 209-230

**Changes Made**:
- Removed Gender field display
- Added Children display (conditional, shows comma-separated names if children exist)
- Added Step-Children display (conditional, shows comma-separated names if step-children exist)

```vue
<!-- Before -->
<div class="flex justify-between border-b pb-2">
  <span class="text-gray-600">Gender:</span>
  <span class="font-semibold text-gray-900">{{ plan.user_profile.gender }}</span>
</div>

<!-- After (Gender removed, Children/Step-Children added) -->
<div v-if="plan.user_profile.children && plan.user_profile.children.length > 0" class="flex justify-between border-b pb-2">
  <span class="text-gray-600">Children:</span>
  <span class="font-semibold text-gray-900">
    {{ plan.user_profile.children.map(c => c.name).join(', ') }}
  </span>
</div>
<div v-if="plan.user_profile.step_children && plan.user_profile.step_children.length > 0" class="flex justify-between border-b pb-2">
  <span class="text-gray-600">Step-Children:</span>
  <span class="font-semibold text-gray-900">
    {{ plan.user_profile.step_children.map(c => c.name).join(', ') }}
  </span>
</div>
```

#### 7.2 Backend - ComprehensiveEstatePlanService.php
**File**: `app/Services/Estate/ComprehensiveEstatePlanService.php`
**Method**: `buildUserProfile()`
**Lines**: 241-268

**Changes Made**:
- Added query for children (family members with `relationship = 'child'`)
- Added query for step-children (family members with `relationship = 'step_child'`)
- Added `children` and `step_children` arrays to returned profile data

```php
// New code added
$children = FamilyMember::where('user_id', $user->id)
    ->where('relationship', 'child')
    ->get()
    ->map(fn($child) => ['name' => $child->name, 'relationship' => 'Child'])
    ->values()
    ->toArray();

$stepChildren = FamilyMember::where('user_id', $user->id)
    ->where('relationship', 'step_child')
    ->get()
    ->map(fn($child) => ['name' => $child->name, 'relationship' => 'Step-Child'])
    ->values()
    ->toArray();

// Added to return array
'children' => $children,
'step_children' => $stepChildren,
```

---

### 8. Estate Plan Detailed Liabilities Display

**Description**: Added detailed liabilities breakdown to the Comprehensive Estate Plan. Previously, only the total liabilities amount was shown. Now individual liabilities (mortgages, credit cards, loans, etc.) are displayed with their names, types, and balances.

**Changes**:

#### 8.1 Backend - ComprehensiveEstatePlanService.php
**File**: `app/Services/Estate/ComprehensiveEstatePlanService.php`

**New Method Added** - `getDetailedLiabilities()`:
```php
private function getDetailedLiabilities(int $userId): array
{
    $liabilities = [];

    // Get mortgages with property addresses
    $mortgages = \App\Models\Mortgage::where('user_id', $userId)
        ->with('property:id,address_line_1')
        ->get();

    foreach ($mortgages as $mortgage) {
        $liabilities[] = [
            'type' => 'Mortgage',
            'name' => $mortgage->property?->address_line_1
                ? "Mortgage - {$mortgage->property->address_line_1}"
                : ($mortgage->lender_name ? "Mortgage - {$mortgage->lender_name}" : 'Mortgage'),
            'balance' => (float) $mortgage->outstanding_balance,
        ];
    }

    // Get other liabilities (credit cards, loans, etc.)
    $otherLiabilities = \App\Models\Estate\Liability::where('user_id', $userId)->get();

    foreach ($otherLiabilities as $liability) {
        $liabilities[] = [
            'type' => ucfirst(str_replace('_', ' ', $liability->liability_type)),
            'name' => $liability->liability_name,
            'balance' => (float) $liability->current_balance,
        ];
    }

    return $liabilities;
}
```

**Updated `buildEstateBreakdown()` method**:
- Now calls `getDetailedLiabilities()` for user, spouse, and combined sections
- Added `detailed_liabilities` array to each breakdown section

#### 8.2 Frontend - ComprehensiveEstatePlan.vue
**File**: `resources/js/views/Estate/ComprehensiveEstatePlan.vue`

**Added Liabilities Breakdown Sections** for:
- User's Estate (after detailed assets)
- Spouse's Estate (after detailed assets, if applicable)
- Combined Estate (after detailed assets, if applicable)

**UI Pattern**:
```vue
<!-- Detailed Liabilities Breakdown -->
<div v-if="plan.estate_breakdown.user.detailed_liabilities && plan.estate_breakdown.user.detailed_liabilities.length > 0" class="mt-6">
  <div class="bg-amber-100 p-3 rounded-t font-semibold text-amber-900">
    Liabilities
  </div>
  <div class="border border-amber-200 rounded-b overflow-hidden">
    <div v-for="(liability, index) in plan.estate_breakdown.user.detailed_liabilities" :key="index"
         class="flex justify-between items-center p-3 border-b last:border-b-0 hover:bg-amber-50">
      <div class="flex items-center gap-3">
        <span class="text-gray-900">{{ liability.name }}</span>
        <span class="px-2 py-0.5 bg-amber-200 text-amber-800 text-xs rounded-full">
          {{ liability.type }}
        </span>
      </div>
      <span class="font-semibold text-amber-900">{{ formatCurrency(liability.balance) }}</span>
    </div>
  </div>
</div>
```

**Liability Types Displayed**:
- Mortgage (with property address)
- Credit card
- Hire purchase
- Loan
- Personal loan
- Car loan
- Overdraft
- Other liability types

#### 8.3 Bug Fix - Spouse Data Retrieval
**File**: `app/Services/Estate/ComprehensiveEstatePlanService.php`
**Method**: `buildUserProfile()`

**Fixed**: Spouse name not displaying in Estate Plan profile section.

**Root Cause**: Code was looking for spouse in `FamilyMember` model, but spouse is stored as a `User` reference via `spouse_id`.

**Before**:
```php
$spouse = null;
if ($user->spouse_id) {
    $spouse = FamilyMember::find($user->spouse_id);
}
```

**After**:
```php
// Spouse is a User, not a FamilyMember
$spouse = $user->spouse;
```

---

### 9. User Profile Financial Statements UI Improvements

**Description**: Reorganised the Personal Accounts (Financial Statements) tab in User Profile with improved tab order and "Coming Soon" watermarks for incomplete features.

**Changes**:

#### 9.1 PersonalAccounts.vue - Tab Reordering
**File**: `resources/js/components/UserProfile/PersonalAccounts.vue`

**Changes Made**:
- Reordered tabs: Balance Sheet → Cashflow → Profit & Loss (was: Profit & Loss → Cashflow → Balance Sheet)
- Changed default active tab from `profit_loss` to `balance_sheet`
- Reordered tab content sections to match new tab order

```javascript
// Before
const activeTab = ref('profit_loss');

// After
const activeTab = ref('balance_sheet');
```

#### 9.2 BalanceSheetView.vue - Removed Joint Column
**File**: `resources/js/components/UserProfile/BalanceSheetView.vue`

**Changes Made**:
- Removed "Joint" column from Assets table header and rows
- Removed "Joint" column from Liabilities table header and rows
- Removed "Joint" column from Net Worth (Equity) table header and rows

**Reason**: Joint assets are not currently calculated/tracked separately; the column was showing £0 for all rows.

#### 9.3 ProfitAndLossView.vue - Coming Soon Watermark
**File**: `resources/js/components/UserProfile/ProfitAndLossView.vue`

**Changes Made**:
- Added "Coming Soon" watermark overlay (amber badge, rotated)
- Added `opacity-50` to content to indicate disabled/placeholder state
- Wrapped content in `relative` positioned container

```vue
<!-- Coming Soon Watermark -->
<div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
  <div class="bg-amber-100 border-2 border-amber-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
    <p class="text-2xl font-bold text-amber-700">Coming Soon</p>
  </div>
</div>
```

#### 9.4 CashflowView.vue - Coming Soon Watermark
**File**: `resources/js/components/UserProfile/CashflowView.vue`

**Changes Made**:
- Added "Coming Soon" watermark overlay (same style as Profit & Loss)
- Added `opacity-50` to content to indicate disabled/placeholder state
- Wrapped content in `relative` positioned container

#### 9.5 BalanceSheetView.vue - Currency Formatting Fix
**File**: `resources/js/components/UserProfile/BalanceSheetView.vue`

**Changes Made**:
- Updated `formatCurrency()` method to use 0 decimal places (was showing `.00`)
- Now consistent with CLAUDE.md currency formatting standards

**Before**:
```javascript
const formatCurrency = (amount) => {
  if (amount === null || amount === undefined) return '£0.00';
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
  }).format(amount);
};
```

**After**:
```javascript
const formatCurrency = (amount) => {
  if (amount === null || amount === undefined) return '£0';
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
};
```

---

### 10. Retirement Overview Improvements

**Description**: Removed duplicate "Pensions" tab and improved pension card layout in the Retirement module for better consistency and alignment.

**Files Changed**:

#### 10.1 RetirementDashboard.vue - Removed Pensions Tab
**File**: `resources/js/views/Retirement/RetirementDashboard.vue`

**Changes Made**:
- Removed "Pensions" tab (id: `inventory`) from tabs array
- Removed `PensionInventory` import and component registration
- Removed `inventory: 'PensionInventory'` from componentMap
- "Overview" tab now serves as the only pension summary view

**Remaining Tabs**: Overview, Contributions, Projections, Portfolio Analysis, Strategies, Decumulation

#### 10.2 RetirementReadiness.vue - Pension Card Improvements
**File**: `resources/js/views/Retirement/RetirementReadiness.vue`

**Changes Made**:

1. **Pension Type Badges** - Now show specific pension types instead of generic labels:
   - DC Pensions: Shows `Occupational`, `SIPP`, `Personal`, `Stakeholder`, or `Workplace`
   - DB Pensions: Shows `Final Salary`, `Career Average`, or `Public Sector`
   - State Pension: Remains as `State Pension`

2. **Removed Ownership Badges** - Removed Individual/Joint/Trust badges from card headers

3. **Standardized Card Layout**:
   - Header: Pension type badge only
   - Scheme Name (bold title)
   - Provider (gray text, blank if not set)
   - Gray divider line
   - Detail rows below divider

4. **Fixed Field Names** for Lump Sum:
   - DC Pensions: Changed `lump_sum_amount` → `lump_sum_contribution`
   - DB Pensions: Changed `lump_sum` → `lump_sum_entitlement`

5. **Default Values**:
   - Monthly Contribution: Shows `£0` instead of blank when not set
   - Lump Sum: Shows `£0` instead of blank when not set
   - Retirement Age (DC): Falls back to user's `target_retirement_age` from profile
   - Payment Start Age (DB): Falls back to user's `target_retirement_age` from profile

**New Formatting Methods Added**:
```javascript
formatDCPensionType(type) {
  const types = {
    occupational: 'Occupational',
    sipp: 'SIPP',
    personal: 'Personal',
    stakeholder: 'Stakeholder',
    workplace: 'Workplace',
  };
  return types[type] || 'DC Pension';
},

formatDBPensionType(type) {
  const types = {
    final_salary: 'Final Salary',
    career_average: 'Career Average',
    public_sector: 'Public Sector',
  };
  return types[type] || 'DB Pension';
},
```

**CSS Updates**:
- Renamed `.pension-provider` to `.pension-scheme` for scheme name styling
- Added `.pension-provider-text` with `min-height: 20px` for consistent spacing

#### 10.3 PortfolioAnalysis.vue - Coming Soon Watermark
**File**: `resources/js/views/Retirement/PortfolioAnalysis.vue`

**Changes Made**:
- Added "Coming Soon" watermark overlay (amber badge, rotated)
- Added `opacity-50` to all content sections (header, loading state, no data state, main content)
- Added `relative` class to root div for watermark positioning

#### 10.4 Recommendations.vue - Coming Soon Watermark
**File**: `resources/js/views/Retirement/Recommendations.vue`

**Changes Made**:
- Added "Coming Soon" watermark overlay (same style as Portfolio Analysis)
- Added `opacity-50` to all content sections (header, filter, recommendations list, empty state)
- Added `relative` class to root div for watermark positioning
- Changed title from "Retirement Recommendations" to "Retirement Strategies" (matches tab name)
- Fixed British spelling: "Personalized" → "Personalised"

---

## Deployment Instructions

### For Development
No database migrations required. Changes are code-only.

```bash
# Pull latest changes
git pull

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

### For Production
1. Upload changed files via SFTP/SCP
2. Clear Laravel caches on server
3. No database changes required

---

## Testing Checklist

### Mortgage Type Fixes
- [ ] Create new property with mixed mortgage type
- [ ] Edit existing property, change mortgage to mixed type
- [ ] Verify mortgage payment calculator accepts mixed type
- [ ] Run factory tests with mortgage creation

### Date Formatting
- [ ] Open SaveAccountModal - verify date fields display correctly
- [ ] Edit investment goal - verify target_date formats properly
- [ ] Edit holding - verify purchase_date formats properly
- [ ] Create/edit savings goal - verify target_date works

### Currency Formatting
- [ ] Check onboarding protection policies step - coverage amounts display with £
- [ ] Check onboarding assets step - values display consistently
- [ ] Check onboarding income step - total income displays correctly
- [ ] Check pension inventory - all currency values use consistent formatting
- [ ] Check retirement readiness - pension values display correctly
- [ ] Check what-if scenarios - baseline and scenario values format properly
- [ ] Check protection dashboard card - sum assured and premiums display without decimals
- [ ] Check protection policy cards - coverage and premium amounts display without decimals

### Tax Year Updates
- [ ] Check ContributionsAllowances page - Key Thresholds shows 2025/26
- [ ] Check AnnualAllowanceTracker - defaults to 2025/26, dropdown includes 2025/26
- [ ] Check StatePensionForm - shows £221.20/week for 2025/26
- [ ] Check ISAAllowanceSummary - heading shows 2025/26
- [ ] Check TaxFees component - ISA and CGT headings show 2025/26
- [ ] Check UKTaxesOverviewCard - displays 2025/26
- [ ] Check RebalancingCalculator - CGT allowance shows £3,000 for 2025/26

### British Spelling & Estate Profile
- [ ] Check Protection Plan - shows "Personalised Plan" (not "Personalized")
- [ ] Check Estate Plan - shows "Personalised Plan" (not "Personalized")
- [ ] Check Estate Plan "Your Profile" - Gender field removed
- [ ] Check Estate Plan "Your Profile" - Spouse name displayed if married
- [ ] Check Estate Plan "Your Profile" - Children displayed if entered (singular/plural label)
- [ ] Check Estate Plan "Your Profile" - Step-Children displayed if entered (singular/plural label)

### Estate Plan Liabilities
- [ ] Check Estate Plan - User's estate shows detailed liabilities breakdown
- [ ] Check Estate Plan - Each liability shows name, type badge, and balance
- [ ] Check Estate Plan - Mortgages show property addresses
- [ ] Check Estate Plan - Other liabilities (credit cards, loans, etc.) display correctly
- [ ] Check Estate Plan - Spouse's estate shows liabilities (if applicable)
- [ ] Check Estate Plan - Combined estate shows all liabilities (if applicable)

### Financial Statements Currency Formatting
- [ ] Check Balance Sheet - All currency values display without decimals (£1,234 not £1,234.00)
- [ ] Check Cashflow - All currency values display without decimals
- [ ] Check Profit & Loss - All currency values display without decimals

### Retirement Overview Improvements
- [ ] Check Net Worth → Retirement tab - "Pensions" sub-tab removed, only "Overview" remains
- [ ] Check pension cards - DC pensions show specific type (Occupational, SIPP, Personal, Stakeholder)
- [ ] Check pension cards - DB pensions show specific type (Final Salary, Career Average, Public Sector)
- [ ] Check pension cards - All cards have consistent layout (Scheme Name, Provider, then details below line)
- [ ] Check pension cards - Monthly Contribution shows £0 when not set (not blank)
- [ ] Check pension cards - Lump Sum shows £0 when not set (not blank)
- [ ] Check pension cards - Retirement Age falls back to user profile target_retirement_age when not set
- [ ] Check Portfolio Analysis tab - Shows "Coming Soon" watermark with opacity
- [ ] Check Strategies tab - Shows "Coming Soon" watermark with opacity
- [ ] Check Strategies tab - Title shows "Retirement Strategies" and uses "Personalised" spelling

---

### 11. Pension Detail Navigation System

**Description**: Implemented a comprehensive pension detail navigation system using component state management. When a user clicks on a pension card in the Overview tab, they now stay within the retirement module context (maintaining the top-level Net Worth navigation) and can navigate through pension-specific detail tabs.

**Architecture Approach**: Component State Navigation
- URL remains constant at `/net-worth/retirement`
- No router configuration changes required
- Instant transitions with Vue state management
- Preserves full navigation context

**Files Created (6 new components)**:

| File | Description |
|------|-------------|
| `resources/js/views/Retirement/PensionDetailView.vue` | Container component that renders pension detail panels |
| `resources/js/views/Retirement/PensionSummaryPanel.vue` | Summary view with key metrics cards |
| `resources/js/views/Retirement/PensionDetailsPanel.vue` | Full pension details (all fields, holdings, notes) |
| `resources/js/views/Retirement/PensionContributionsPanel.vue` | Contribution tracking (allowance tracker, NI credits) |
| `resources/js/views/Retirement/PensionProjectionsPanel.vue` | Growth projections, drawdown/annuity comparison |
| `resources/js/views/Retirement/PensionAnalysisPanel.vue` | Analysis with Coming Soon watermark |

**Files Modified (2 existing files)**:

#### 11.1 RetirementDashboard.vue - State Management
**File**: `resources/js/views/Retirement/RetirementDashboard.vue`

**Changes Made**:
- Added state properties: `selectedPension`, `selectedPensionType`, for tracking selected pension
- Created two tab configurations: `overviewTabs` (original tabs) and `detailTabs` (pension-specific tabs)
- Added `isDetailMode` computed property to determine current mode
- Added `currentTabs` computed property to switch between tab sets
- Added `selectPension(pension, type)` method - called when card clicked
- Added `clearSelection()` method - returns to overview mode
- Added `handleTabClick(tabId)` method - handles "All Pensions" tab click
- Updated template to conditionally render `PensionDetailView` when pension selected
- Imported and registered `PensionDetailView` component

**Detail Mode Tabs**:
- All Pensions (returns to overview)
- Summary (pension key metrics)
- Details (full pension information)
- Contributions (contribution tracking)
- Projections (growth projections)
- Analysis (with Coming Soon watermark)

#### 11.2 RetirementReadiness.vue - Event Emission
**File**: `resources/js/views/Retirement/RetirementReadiness.vue`

**Changes Made**:
- Added `emits: ['select-pension']` to component definition
- Updated `viewPension(type, id)` method to emit event instead of using `router.push()`
- Method now finds the pension object and emits it with type to parent

**Before**:
```javascript
viewPension(type, id) {
  this.$router.push(`/pension/${type}/${id}`);
}
```

**After**:
```javascript
viewPension(type, id) {
  let pension = null;
  if (type === 'dc') {
    pension = this.dcPensions.find(p => p.id === id);
  } else if (type === 'db') {
    pension = this.dbPensions.find(p => p.id === id);
  } else if (type === 'state') {
    pension = this.statePension;
  }
  if (pension) {
    this.$emit('select-pension', pension, type);
  }
}
```

**Component Features**:

#### PensionSummaryPanel.vue
- DC Pension: Current Value, Monthly Contribution, Retirement Age, Scheme Type, Provider, Lump Sum
- DB Pension: Annual Income, Payment Start Age, Lump Sum Entitlement, Scheme Type, Revaluation Rate, Employer
- State Pension: Annual Forecast, NI Qualifying Years, State Pension Age, Full Pension Rate, Percentage of Full, Deferral Option

#### PensionDetailsPanel.vue
- Full breakdown of all pension fields
- Holdings table for DC pensions with fund allocations
- Spouse benefits section for DB pensions
- National Insurance record details for State Pension
- Notes section display

#### PensionContributionsPanel.vue
- DC: Annual allowance tracker with progress bar, employee/employer contributions
- DB: Accrual rate formula display, employee contribution rates
- State: NI record progress bar (0-35 years), voluntary contribution value analysis

#### PensionProjectionsPanel.vue
- DC: Conservative/Moderate/Optimistic growth scenarios, Drawdown vs Annuity comparison, Tax-free lump sum calculations
- DB: Early retirement reduction scenarios, Commutation options, Inflation impact over time
- State: Deferral analysis, Gap-filling value analysis, Triple Lock information

#### PensionAnalysisPanel.vue (Coming Soon)
- DC: Risk meter, Fee analysis, Diversification score, Optimisation suggestions
- DB: Scheme security assessment, Transfer value analysis, DB vs DC comparison
- State: Voluntary NI value analysis, Deferral analysis, Integration tips
- All sections have "Coming Soon" watermark with opacity

**Benefits**:
1. Top-level navigation (Overview, Retirement, Property, Investments) always visible
2. URL stays constant - no route changes needed
3. Instant transitions - smooth Vue state management
4. Easy back navigation - "All Pensions" tab returns instantly
5. All logic contained within Retirement module

---

## Deployment Instructions

### For Development
No database migrations required. Changes are code-only.

```bash
# Pull latest changes
git pull

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

### For Production
1. Upload changed files via SFTP/SCP
2. Clear Laravel caches on server
3. No database changes required

---

## Testing Checklist

### Mortgage Type Fixes
- [ ] Create new property with mixed mortgage type
- [ ] Edit existing property, change mortgage to mixed type
- [ ] Verify mortgage payment calculator accepts mixed type
- [ ] Run factory tests with mortgage creation

### Date Formatting
- [ ] Open SaveAccountModal - verify date fields display correctly
- [ ] Edit investment goal - verify target_date formats properly
- [ ] Edit holding - verify purchase_date formats properly
- [ ] Create/edit savings goal - verify target_date works

### Currency Formatting
- [ ] Check onboarding protection policies step - coverage amounts display with £
- [ ] Check onboarding assets step - values display consistently
- [ ] Check onboarding income step - total income displays correctly
- [ ] Check pension inventory - all currency values use consistent formatting
- [ ] Check retirement readiness - pension values display correctly
- [ ] Check what-if scenarios - baseline and scenario values format properly
- [ ] Check protection dashboard card - sum assured and premiums display without decimals
- [ ] Check protection policy cards - coverage and premium amounts display without decimals

### Tax Year Updates
- [ ] Check ContributionsAllowances page - Key Thresholds shows 2025/26
- [ ] Check AnnualAllowanceTracker - defaults to 2025/26, dropdown includes 2025/26
- [ ] Check StatePensionForm - shows £221.20/week for 2025/26
- [ ] Check ISAAllowanceSummary - heading shows 2025/26
- [ ] Check TaxFees component - ISA and CGT headings show 2025/26
- [ ] Check UKTaxesOverviewCard - displays 2025/26
- [ ] Check RebalancingCalculator - CGT allowance shows £3,000 for 2025/26

### British Spelling & Estate Profile
- [ ] Check Protection Plan - shows "Personalised Plan" (not "Personalized")
- [ ] Check Estate Plan - shows "Personalised Plan" (not "Personalized")
- [ ] Check Estate Plan "Your Profile" - Gender field removed
- [ ] Check Estate Plan "Your Profile" - Spouse name displayed if married
- [ ] Check Estate Plan "Your Profile" - Children displayed if entered (singular/plural label)
- [ ] Check Estate Plan "Your Profile" - Step-Children displayed if entered (singular/plural label)

### Estate Plan Liabilities
- [ ] Check Estate Plan - User's estate shows detailed liabilities breakdown
- [ ] Check Estate Plan - Each liability shows name, type badge, and balance
- [ ] Check Estate Plan - Mortgages show property addresses
- [ ] Check Estate Plan - Other liabilities (credit cards, loans, etc.) display correctly
- [ ] Check Estate Plan - Spouse's estate shows liabilities (if applicable)
- [ ] Check Estate Plan - Combined estate shows all liabilities (if applicable)

### Financial Statements Currency Formatting
- [ ] Check Balance Sheet - All currency values display without decimals (£1,234 not £1,234.00)
- [ ] Check Cashflow - All currency values display without decimals
- [ ] Check Profit & Loss - All currency values display without decimals

### Retirement Overview Improvements
- [ ] Check Net Worth → Retirement tab - "Pensions" sub-tab removed, only "Overview" remains
- [ ] Check pension cards - DC pensions show specific type (Occupational, SIPP, Personal, Stakeholder)
- [ ] Check pension cards - DB pensions show specific type (Final Salary, Career Average, Public Sector)
- [ ] Check pension cards - All cards have consistent layout (Scheme Name, Provider, then details below line)
- [ ] Check pension cards - Monthly Contribution shows £0 when not set (not blank)
- [ ] Check pension cards - Lump Sum shows £0 when not set (not blank)
- [ ] Check pension cards - Retirement Age falls back to user profile target_retirement_age when not set
- [ ] Check Portfolio Analysis tab - Shows "Coming Soon" watermark with opacity
- [ ] Check Strategies tab - Shows "Coming Soon" watermark with opacity
- [ ] Check Strategies tab - Title shows "Retirement Strategies" and uses "Personalised" spelling

### Pension Detail Navigation (NEW)
- [ ] Click DC pension card - tabs switch to detail mode (All Pensions, Summary, Details, Contributions, Projections, Analysis)
- [ ] Click DB pension card - tabs switch to detail mode
- [ ] Click State pension card - tabs switch to detail mode
- [ ] Click "All Pensions" tab - returns to Overview mode with original tabs
- [ ] Top-level Net Worth navigation remains visible in detail mode
- [ ] Summary tab displays correct pension metrics
- [ ] Details tab shows all pension fields
- [ ] Contributions tab shows contribution tracking (DC), accrual info (DB), or NI record (State)
- [ ] Projections tab shows growth scenarios (DC), early retirement options (DB), or deferral analysis (State)
- [ ] Analysis tab shows "Coming Soon" watermark

---

### 12. Breadcrumb and Refresh Button Removal

**Description**: Removed redundant breadcrumb navigation and refresh buttons from all views for cleaner UI.

**Rationale**: The main sidebar navigation provides all necessary navigation. Breadcrumbs were redundant and cluttered the UI.

**Files Changed**:

| File | Change |
|------|--------|
| `resources/js/views/Dashboard.vue` | Removed refresh button |
| `resources/js/views/Retirement/RetirementDashboard.vue` | Removed "Dashboard / Retirement Planning" breadcrumb |
| `resources/js/views/UKTaxes/UKTaxesDashboard.vue` | Removed "Dashboard / UK Taxes" breadcrumb |
| `resources/js/views/Retirement/PensionDetail.vue` | Removed "Dashboard / Retirement / [Type]" breadcrumb |
| `resources/js/components/NetWorth/Property/PropertyDetail.vue` | Removed "Dashboard / Net Worth / Property" breadcrumb |
| `resources/js/views/NetWorth/NetWorthDashboard.vue` | Removed breadcrumb + refresh button + unused CSS/methods |
| `resources/js/views/Savings/SavingsAccountDetail.vue` | Removed "Dashboard / Savings / Account" breadcrumb |
| `resources/js/components/Protection/PolicyDetail.vue` | Removed "Dashboard / Protection / Policy" breadcrumb |
| `resources/js/views/Trusts/TrustsDashboard.vue` | Removed "Dashboard / Trusts" breadcrumb |

---

### 13. Retirement Overview Cards Repositioned

**Description**: Moved "Years to Retirement" and "Projected Income" cards from top to bottom of page.

**File**: `resources/js/views/Retirement/RetirementReadiness.vue`

**New Order**:
1. Your Pensions (pension cards grid)
2. Pension Wealth Summary (DC/DB/State totals)
3. Years to Retirement & Projected Income (overview cards)

---

### 14. Property Tab Simplified

**Description**: Removed filter and sort dropdowns from Property tab, keeping only the Add Property button.

**File**: `resources/js/components/NetWorth/PropertyList.vue`

**Changes**:
- Removed "All Properties / Main Residence / Secondary Residence / Buy to Let" filter dropdown
- Removed "Value (High to Low) / Value (Low to High) / Property Type" sort dropdown
- Removed `filterType` and `sortBy` data properties
- Simplified `filteredProperties` computed to sort by value (high to low) by default
- Cleaned up unused CSS styles

---

### 15. Investment Account Detail Navigation System

**Description**: Implemented investment account detail navigation using component state management (matching Retirement module pattern). Clicking an account card now enters a detail view with account-specific tabs.

#### Overview Mode Changes

**Tabs Removed**:
- Accounts tab (merged into Portfolio Overview - cards are now clickable)
- Contributions tab (removed entirely)

**Overview Mode Tabs (9 tabs)**:
1. Portfolio Overview - Summary with clickable account cards
2. Holdings - All holdings across all accounts
3. Performance - Coming Soon watermark
4. Portfolio Optimisation - Coming Soon watermark
5. Rebalancing - Coming Soon watermark
6. Goals - Coming Soon watermark
7. Tax Efficiency - Coming Soon watermark
8. Fees - Coming Soon watermark
9. Strategy - Coming Soon watermark

#### Detail Mode Tabs (5 tabs)
When user clicks an account card:
1. Portfolio Overview - Returns to overview mode
2. Overview - Account summary with value, ISA info, asset allocation
3. Holdings - Holdings for this specific account
4. Performance - Coming Soon watermark
5. Fees - Coming Soon watermark

#### Files Modified

**InvestmentDashboard.vue**:
- Removed breadcrumb navigation
- Added `selectedAccount` state property
- Renamed `tabs` to `overviewTabs` (9 tabs)
- Added `detailTabs` array (5 tabs)
- Added `currentTabs` computed property
- Added `isDetailMode` computed property
- Added `selectAccount()`, `clearSelection()`, `handleTabClick()` methods
- Added Coming Soon watermarks to 7 overview tabs inline
- Removed Accounts and ContributionPlanner imports

**PortfolioOverview.vue**:
- Added `emits: ['open-add-account-modal', 'select-account']`
- Updated `viewAccount()` to emit full account object instead of navigating to Accounts tab

#### New Files Created (5 files)

1. **AccountDetailView.vue** (`resources/js/views/Investment/`)
   - Container component for account detail mode
   - Props: `account`, `activeTab`
   - Shows account header with badges and value
   - Renders appropriate panel based on activeTab

2. **AccountSummaryPanel.vue** (`resources/js/views/Investment/`)
   - Summary cards: Current value, Provider, Holdings count, Platform fee
   - ISA section: Contributions YTD, Allowance remaining, Tax year
   - Joint account section: Full value, Your share, Joint owner
   - Asset allocation breakdown with visual bars

3. **AccountHoldingsPanel.vue** (`resources/js/views/Investment/`)
   - Holdings table with responsive mobile cards
   - Columns: Name, Type, Units, Unit Cost, Value, Allocation %
   - Add Holding button
   - Asset allocation summary by type
   - Empty state with Add First Holding button

4. **AccountPerformancePanel.vue** (`resources/js/views/Investment/`)
   - Coming Soon watermark
   - Placeholder: YTD Return card, Performance chart, Benchmark comparison

5. **AccountFeesPanel.vue** (`resources/js/views/Investment/`)
   - Coming Soon watermark
   - Placeholder: Platform fee, Fund fees, Total cost, Fee comparison

#### Coming Soon Watermark Style

All Coming Soon watermarks use the consistent amber box pattern (matching PensionAnalysisPanel.vue):

```vue
<div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
  <div class="bg-amber-100 border-2 border-amber-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
    <p class="text-2xl font-bold text-amber-700">Coming Soon</p>
  </div>
</div>
```

This pattern is documented in CLAUDE.md Section 7 (Critical Vue.js Patterns).

---

### 16. Investment/Retirement Formatting Consistency

**Description**: Updated the Investment module to match the Retirement module's formatting patterns for visual consistency across the application.

#### 16.1 InvestmentDashboard.vue - Tab Navigation & Spacing

**File**: `resources/js/views/Investment/InvestmentDashboard.vue`

**Changes Made**:
- Changed root padding from `py-6` to `p-6` (matching Retirement)
- Removed `max-w-7xl mx-auto px-4` wrapper
- Updated tab navigation with optimised spacing for 9 tabs:
  - Changed `px-6 py-4` to `px-4 py-3` to fit all tabs without scrollbar
  - Changed border color from `border-blue-600` to `border-indigo-600` (matching Retirement)
  - Changed text color from `text-blue-600` to `text-indigo-600`
  - Added `bg-transparent` to prevent background on hover
- Added `<transition name="fade" mode="out-in">` for smooth tab content transitions
- Updated loading spinner from `border-blue-500` to `border-indigo-600`

**Tab Navigation Code**:
```vue
<div class="mb-6 border-b border-gray-200">
  <nav class="flex">
    <button
      v-for="tab in currentTabs"
      :key="tab.id"
      @click="handleTabClick(tab.id)"
      :class="[
        'px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors duration-200 bg-transparent',
        activeTab === tab.id
          ? 'text-indigo-600 border-b-2 border-indigo-600'
          : 'text-gray-600 hover:text-gray-900 border-b-2 border-transparent'
      ]"
    >
      {{ tab.label }}
    </button>
  </nav>
</div>
```

#### 16.2 PortfolioOverview.vue - Portfolio Summary Section

**File**: `resources/js/components/Investment/PortfolioOverview.vue`

**Changes Made**:
- Removed top summary cards (Total Value, YTD Return, Diversification Score)
- Added Portfolio Summary section at bottom (after Investment Accounts)
- Used `border-l-4` pattern matching Pension Wealth Summary in RetirementReadiness.vue

**New Portfolio Summary Structure**:
```vue
<!-- Portfolio Summary -->
<div class="bg-white rounded-lg shadow p-6">
  <h3 class="text-lg font-semibold text-gray-900 mb-6">Portfolio Summary</h3>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Total Portfolio Value -->
    <div class="border-l-4 border-blue-500 pl-4">
      <p class="text-sm text-gray-600 mb-1">Total Portfolio Value</p>
      <p class="text-2xl font-bold text-gray-900">{{ formattedTotalValue }}</p>
      <p class="text-sm text-gray-500 mt-1">{{ accountsCount }} account{{ accountsCount !== 1 ? 's' : '' }}</p>
    </div>
    <!-- YTD Return -->
    <div class="border-l-4 pl-4" :class="ytdReturn >= 0 ? 'border-green-500' : 'border-red-500'">
      <p class="text-sm text-gray-600 mb-1">YTD Return</p>
      <p class="text-2xl font-bold" :class="ytdReturn >= 0 ? 'text-green-600' : 'text-red-600'">
        {{ formattedYtdReturn }}
      </p>
      <p class="text-sm text-gray-500 mt-1">{{ holdingsCount }} holding{{ holdingsCount !== 1 ? 's' : '' }}</p>
    </div>
    <!-- Diversification Score -->
    <div class="border-l-4 border-purple-500 pl-4">
      <p class="text-sm text-gray-600 mb-1">Diversification Score</p>
      <p class="text-2xl font-bold text-gray-900">{{ diversificationScore }}/100</p>
      <p class="text-sm text-gray-500 mt-1">{{ diversificationLabel }}</p>
    </div>
  </div>
</div>
```

**Removed CSS**: Old `.summary-card` styles were removed as no longer needed.

#### 16.3 AccountSummaryPanel.vue - Complete Rewrite

**File**: `resources/js/views/Investment/AccountSummaryPanel.vue`

**Description**: Completely rewrote to match PensionDetailsPanel.vue formatting pattern.

**New Sections**:
1. **Basic Information** - Account Name, Provider, Account Type, Ownership, Platform Fee, Holdings
2. **Value Information** - Current Value (highlighted), Your Share (joint), YTD Return, Valuation Date
3. **ISA Allowance** (conditional for ISA accounts) - Contributions YTD, Allowance Remaining, Annual Allowance, Tax Year
4. **Joint Ownership** (conditional for joint accounts) - Full Account Value, Your Share, Joint Owner
5. **Asset Allocation** - Primary Asset Class, breakdown by type
6. **Notes** (conditional if notes exist)

**CSS Pattern Used**:
```css
.account-details-panel {
  animation: fadeIn 0.3s ease-out;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.details-section {
  background: white;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  padding: 24px;
}

.section-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 1px solid #e5e7eb;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 20px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.detail-label {
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
}

.detail-value {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.detail-value.highlight {
  font-size: 20px;
  color: #059669;
}
```

#### 16.4 AccountDetailView.vue - Header Card Update

**File**: `resources/js/views/Investment/AccountDetailView.vue`

**Changes Made**:
- Updated header card to match PensionDetailView.vue format
- Added blue left border accent: `border-left: 4px solid #3b82f6`
- Restructured layout: Badge | Name & Provider | Value (right-aligned)
- Added fadeIn animation with slide-up effect
- Updated badge styling with scoped CSS classes

**New Header Structure**:
```vue
<div class="account-header bg-white rounded-lg shadow p-6 mb-6">
  <div class="flex items-center justify-between">
    <div class="flex items-center">
      <div :class="['account-type-badge', accountTypeBadgeClass(account.account_type)]">
        {{ formatAccountType(account.account_type) }}
      </div>
      <div class="ml-4">
        <h2 class="text-2xl font-bold text-gray-900">{{ account.provider }}</h2>
        <p class="text-gray-600">{{ account.account_name }}</p>
      </div>
    </div>
    <div class="text-right">
      <p class="text-sm text-gray-600">Current Value</p>
      <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(displayValue) }}</p>
      <p v-if="account.ownership_type === 'joint'" class="text-sm text-purple-600">
        Your 50% share: {{ formatCurrency(account.current_value) }}
      </p>
    </div>
  </div>
</div>
```

**CSS Additions**:
```css
.account-header {
  border-left: 4px solid #3b82f6;
}

.account-type-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 16px;
  font-size: 14px;
  font-weight: 600;
  border-radius: 8px;
}

/* Account type badge colors */
.badge-isa { background: #d1fae5; color: #065f46; }
.badge-gia { background: #dbeafe; color: #1e40af; }
.badge-sipp, .badge-pension { background: #e9d5ff; color: #6b21a8; }
.badge-nsi { background: #e0e7ff; color: #3730a3; }
.badge-onshore_bond, .badge-offshore_bond { background: #ffedd5; color: #9a3412; }
.badge-vct, .badge-eis { background: #fce7f3; color: #9d174d; }
.badge-other { background: #f3f4f6; color: #374151; }

.account-detail-view {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
```

---

### Testing Checklist - Investment Formatting

- [ ] Investment Dashboard - Tab navigation uses indigo colors and fits all 9 tabs without scrollbar
- [ ] Portfolio Overview - Portfolio Summary at bottom with border-l-4 cards (blue, green/red, purple)
- [ ] Account cards clickable - Clicking card enters detail mode with correct tabs
- [ ] Account Detail View - Header card has blue left border, badge, provider name, value
- [ ] Account Overview tab - Uses details-section pattern with sections and grids
- [ ] Account Overview tab - Current Value highlighted in green
- [ ] ISA accounts - Show ISA Allowance section
- [ ] Joint accounts - Show Joint Ownership section
- [ ] Navigation consistency - Matches Retirement module formatting exactly

---

### 17. Property Tab Inline Navigation

**Description**: Updated the Property tab in Net Worth to show property details inline (within the same tab context) rather than navigating to a separate page, ensuring the top Net Worth navigation remains visible.

#### 17.1 PropertyCard.vue - Event Emission

**File**: `resources/js/components/NetWorth/PropertyCard.vue`

**Changes Made**:
- Added `emits: ['select-property']` to component definition
- Changed `viewDetails()` from `this.$router.push()` to `this.$emit('select-property', this.property)`

**Before**:
```javascript
viewDetails() {
  this.$router.push(`/property/${this.property.id}`);
}
```

**After**:
```javascript
emits: ['select-property'],
// ...
viewDetails() {
  this.$emit('select-property', this.property);
}
```

#### 17.2 PropertyList.vue - State Management

**File**: `resources/js/components/NetWorth/PropertyList.vue`

**Changes Made**:
- Added `PropertyDetailInline` component import
- Added `selectedProperty` state for tracking selected property
- Added `selectProperty(property)` method to enter detail mode
- Added `clearSelection()` method to return to list view
- Added `handlePropertyDeleted()` method to handle property deletion
- Template now conditionally shows either `PropertyDetailInline` (when property selected) or property grid (list view)
- Updated `PropertyCard` to pass `@select-property` event handler

**New State Flow**:
1. User sees property grid (list view)
2. User clicks property card → `selectProperty(property)` called
3. `selectedProperty` set → template renders `PropertyDetailInline`
4. User clicks "Back to Properties" → `clearSelection()` called
5. `selectedProperty` cleared → template renders property grid

#### 17.3 PropertyDetailInline.vue - New Component

**File**: `resources/js/components/NetWorth/Property/PropertyDetailInline.vue` (NEW)

**Description**: A modified version of `PropertyDetail.vue` designed to be rendered inline within `PropertyList.vue` without the `AppLayout` wrapper.

**Key Differences from PropertyDetail.vue**:
- No `AppLayout` wrapper - renders directly in parent context
- Receives `propertyId` as prop instead of from `$route.params.id`
- Added "Back to Properties" button with arrow icon
- Emits `@back` event when back button clicked
- Emits `@deleted` event when property is deleted (so parent can refresh list)
- Added fadeIn animation for smooth transitions

**Props**:
- `propertyId: Number` (required) - ID of property to display

**Emits**:
- `back` - User wants to return to property list
- `deleted` - Property was successfully deleted

**Template Structure**:
```vue
<template>
  <div class="property-detail-inline">
    <!-- Back Button -->
    <button @click="$emit('back')" class="back-button mb-4">
      <svg><!-- arrow icon --></svg>
      Back to Properties
    </button>

    <!-- Same content as PropertyDetail.vue -->
    <!-- Loading, Error, Header, Metrics, Tabs, Modals -->
  </div>
</template>
```

**Benefits**:
1. Net Worth top navigation (Overview, Retirement, Property, Investments, etc.) remains visible
2. Consistent navigation experience with Retirement and Investment modules
3. No URL change required - state managed within component
4. Smooth transitions with animation

---

### Testing Checklist - Property Inline Navigation

- [ ] Property tab shows list of properties with cards
- [ ] Clicking property card shows property detail inline (not separate page)
- [ ] Net Worth top navigation tabs remain visible in detail view
- [ ] "Back to Properties" button returns to property list
- [ ] Editing property from detail view works correctly
- [ ] Deleting property returns to property list with success message
- [ ] All property detail tabs work (Overview, Mortgage, Financials, Taxes)

---

### 18. Cash Tab Inline Navigation

**Description**: Updated the Cash tab in Net Worth to show savings account details inline (within the same tab context) rather than navigating to a separate page, ensuring the top Net Worth navigation remains visible.

#### 18.1 CurrentSituation.vue - Event Emission

**File**: `resources/js/components/Savings/CurrentSituation.vue`

**Changes Made**:
- Added `emits: ['select-account']` to component definition
- Changed `viewAccountDetail(accountId)` from `this.$router.push()` to `this.$emit('select-account', account)`

**Before**:
```javascript
viewAccountDetail(accountId) {
  this.$router.push({ name: 'SavingsAccountDetail', params: { id: accountId } });
}
```

**After**:
```javascript
emits: ['select-account'],
// ...
viewAccountDetail(accountId) {
  const account = this.accounts.find(a => a.id === accountId);
  if (account) {
    this.$emit('select-account', account);
  }
}
```

#### 18.2 SavingsDashboard.vue - State Management

**File**: `resources/js/views/Savings/SavingsDashboard.vue`

**Changes Made**:
- Added `SavingsAccountDetailInline` component import
- Added `selectedAccount` state for tracking selected account
- Added `selectAccount(account)` method - uses inline view when embedded, router when standalone
- Added `clearSelection()` method to return to list view
- Added `handleAccountDeleted()` method to handle account deletion
- Template conditionally shows `SavingsAccountDetailInline` (when embedded and account selected) or normal tabs

**New State Flow (when embedded in Net Worth)**:
1. User sees Cash Overview with account cards
2. User clicks account card → `selectAccount(account)` called
3. `selectedAccount` set → template renders `SavingsAccountDetailInline`
4. User clicks "Back to Cash Overview" → `clearSelection()` called
5. `selectedAccount` cleared → template renders normal tabs

**Note**: When accessing Savings Dashboard directly (not embedded), clicking an account still uses router navigation to the existing `SavingsAccountDetail.vue` page.

#### 18.3 SavingsAccountDetailInline.vue - New Component

**File**: `resources/js/views/Savings/SavingsAccountDetailInline.vue` (NEW)

**Description**: A modified version of `SavingsAccountDetail.vue` designed to be rendered inline within `SavingsDashboard.vue` without the `AppLayout` wrapper.

**Key Differences from SavingsAccountDetail.vue**:
- No `AppLayout` wrapper - renders directly in parent context
- Receives `accountId` as prop instead of from `$route.params.id`
- Added "Back to Cash Overview" button with arrow icon
- Emits `@back` event when back button clicked
- Emits `@deleted` event when account is deleted (so parent can refresh list)
- Added fadeIn animation for smooth transitions

**Props**:
- `accountId: Number` (required) - ID of savings account to display

**Emits**:
- `back` - User wants to return to cash overview
- `deleted` - Account was successfully deleted

**Template Structure**:
```vue
<template>
  <div class="savings-account-detail-inline">
    <!-- Back Button -->
    <button @click="$emit('back')" class="back-button mb-4">
      <svg><!-- arrow icon --></svg>
      Back to Cash Overview
    </button>

    <!-- Same content as SavingsAccountDetail.vue -->
    <!-- Loading, Error, Header, Key Metrics, Account Details, Modals -->
  </div>
</template>
```

**Benefits**:
1. Net Worth top navigation (Overview, Retirement, Property, Investments, Cash, etc.) remains visible
2. Consistent navigation experience with Property and Investment modules
3. No URL change required - state managed within component
4. Smooth transitions with animation

---

### Testing Checklist - Cash Inline Navigation

- [ ] Cash tab shows Account Overview with account cards
- [ ] Clicking account card shows account detail inline (not separate page)
- [ ] Net Worth top navigation tabs remain visible in detail view
- [ ] "Back to Cash Overview" button returns to account list
- [ ] Editing account from detail view works correctly
- [ ] Deleting account returns to cash overview with refresh
- [ ] Account details show: Institution, Type, Balance, Interest Rate, Access Terms, ISA info

---

### 20. Additional Breadcrumb Navigation Removal

**Description**: Removed remaining breadcrumb navigation from Estate Planning, Protection, and Plans modules for cleaner, consistent UI.

**Rationale**: Building on Section 12's breadcrumb removal, these additional modules had breadcrumb navigation that was redundant with the main sidebar navigation.

**Files Changed**:

| File | Change |
|------|--------|
| `resources/js/views/Estate/EstateDashboard.vue` | Removed "Dashboard / Estate Planning" breadcrumb |
| `resources/js/views/Protection/ProtectionDashboard.vue` | Removed "Dashboard / Protection Planning" breadcrumb |
| `resources/js/views/Plans/PlansDashboard.vue` | Removed "Dashboard / Financial Plans" breadcrumb |
| `resources/js/views/Plans/InvestmentSavingsPlan.vue` | Removed "Dashboard / Plans / Investment & Savings" breadcrumb |

**Note**: The `router/index.js` file contains breadcrumb metadata in route definitions (e.g., `meta: { breadcrumb: [...] }`). This metadata was retained as it may be used for other purposes (e.g., page titles, analytics). Only the visible UI breadcrumb components were removed.

---

### Testing Checklist - Additional Breadcrumb Removal

- [ ] Estate Planning page - No breadcrumb visible at top
- [ ] Protection Planning page - No breadcrumb visible at top
- [ ] Financial Plans page - No breadcrumb visible at top
- [ ] Investment & Savings Plan page - No breadcrumb visible at top
- [ ] All navigation works via sidebar menu

---

### 19. Joint Expenditure Reciprocal Records Fix

**Description**: Fixed a critical bug where joint/50/50 expense sharing during onboarding only saved expenses to the primary user's account, leaving the spouse's account with no expenditure data.

**Root Cause**: In `OnboardingService.php`, the `processExpenditureInfo()` method handled **separate mode** correctly (saving different expenses to each spouse), but in **joint mode** (the default 50/50 split), it only updated the current user's record without creating a reciprocal record for the spouse.

**File Changed**: `app/Services/Onboarding/OnboardingService.php`

**Changes**:

1. Added missing expenditure fields to joint mode data array:
   - `school_lunches`
   - `school_extras`
   - `university_fees`
   - `gifts_charity`
   - `regular_savings`

2. Set `expenditure_sharing_mode = 'joint'` explicitly

3. Added reciprocal spouse update - when user has a spouse, the same expense data is now copied to both accounts

**Code Change** (lines 517-556):

```php
} else {
    // Joint mode or single user: Update user with flat data
    $expenditureData = [
        'food_groceries' => $data['food_groceries'] ?? 0,
        'transport_fuel' => $data['transport_fuel'] ?? 0,
        // ... all expenditure fields ...
        'expenditure_entry_mode' => $data['expenditure_entry_mode'] ?? 'category',
        'expenditure_sharing_mode' => 'joint',
    ];

    $user->update($expenditureData);

    // CRITICAL: For joint/50/50 mode, also update spouse with the same expenses
    // Both accounts share the same household expenses (each represents 50%)
    if ($user->spouse_id) {
        $spouse = User::find($user->spouse_id);
        if ($spouse) {
            $spouse->update($expenditureData);
        }
    }
}
```

**Behaviour Now**:
- **Joint mode (default)**: Same expenses saved to BOTH user accounts with `expenditure_sharing_mode = 'joint'`
- **Separate mode**: Different expenses saved to each user with `expenditure_sharing_mode = 'separate'`

---

### Testing Checklist - Joint Expenditure Fix

- [ ] Create new user with spouse during onboarding
- [ ] Enter expenditure with default 50/50 split (don't check "separate expenditure" box)
- [ ] Complete onboarding
- [ ] Log out and log into spouse account
- [ ] Navigate to User Profile > Expenditure tab
- [ ] Verify spouse account shows the same expenditure values as primary account
- [ ] Verify `expenditure_sharing_mode` is set to 'joint' on both accounts

---

## Files to Upload for Deployment

### Complete File List (78 files)

#### Backend Files (7 files)
```
app/Http/Controllers/Api/MortgageController.php
app/Http/Requests/StorePropertyRequest.php
app/Services/Protection/ComprehensiveProtectionPlanService.php
app/Services/Estate/ComprehensiveEstatePlanService.php
app/Services/Onboarding/OnboardingService.php
database/factories/MortgageFactory.php
CLAUDE.md
```

#### Frontend - Onboarding Components (4 files)
```
resources/js/components/Onboarding/steps/ProtectionPoliciesStep.vue
resources/js/components/Onboarding/steps/AssetsStep.vue
resources/js/components/Onboarding/steps/IncomeStep.vue
resources/js/components/Onboarding/steps/LiabilitiesStep.vue
```

#### Frontend - Savings Components (2 files)
```
resources/js/components/Savings/SaveAccountModal.vue
resources/js/components/Savings/SaveGoalModal.vue
```

#### Frontend - Investment Components (8 files)
```
resources/js/components/Investment/GoalForm.vue
resources/js/components/Investment/HoldingForm.vue
resources/js/components/Investment/AccountForm.vue
resources/js/components/Investment/TaxFees.vue
resources/js/components/Investment/ISAOptimizationStrategy.vue
resources/js/components/Investment/RebalancingCalculator.vue
resources/js/components/Investment/PortfolioOverview.vue
```

#### Frontend - Investment Views (6 files)
```
resources/js/views/Investment/InvestmentDashboard.vue
resources/js/views/Investment/AccountDetailView.vue (NEW)
resources/js/views/Investment/AccountSummaryPanel.vue (NEW)
resources/js/views/Investment/AccountHoldingsPanel.vue (NEW)
resources/js/views/Investment/AccountPerformancePanel.vue (NEW)
resources/js/views/Investment/AccountFeesPanel.vue (NEW)
```

#### Frontend - Protection Components (2 files)
```
resources/js/components/Protection/ProtectionOverviewCard.vue
resources/js/components/Protection/PolicyCard.vue
```

#### Frontend - Retirement Components (4 files)
```
resources/js/components/Retirement/PensionCard.vue
resources/js/components/Retirement/AnnualAllowanceTracker.vue
resources/js/components/Retirement/DCPensionForm.vue
resources/js/components/Retirement/RetirementOverviewCard.vue
resources/js/components/Retirement/StatePensionForm.vue
```

#### Frontend - Retirement Views (14 files)
```
resources/js/views/Retirement/RetirementDashboard.vue
resources/js/views/Retirement/RetirementReadiness.vue
resources/js/views/Retirement/PensionInventory.vue
resources/js/views/Retirement/PensionDetail.vue
resources/js/views/Retirement/DecumulationPlanning.vue
resources/js/views/Retirement/Projections.vue
resources/js/views/Retirement/ContributionsAllowances.vue
resources/js/views/Retirement/WhatIfScenarios.vue
resources/js/views/Retirement/PortfolioAnalysis.vue
resources/js/views/Retirement/Recommendations.vue
resources/js/views/Retirement/PensionDetailView.vue (NEW)
resources/js/views/Retirement/PensionSummaryPanel.vue (NEW)
resources/js/views/Retirement/PensionDetailsPanel.vue (NEW)
resources/js/views/Retirement/PensionContributionsPanel.vue (NEW)
resources/js/views/Retirement/PensionProjectionsPanel.vue (NEW)
resources/js/views/Retirement/PensionAnalysisPanel.vue (NEW)
```

#### Frontend - Estate Views (1 file)
```
resources/js/views/Estate/ComprehensiveEstatePlan.vue
```

#### Frontend - User Profile Components (4 files)
```
resources/js/components/UserProfile/BalanceSheetView.vue
resources/js/components/UserProfile/CashflowView.vue
resources/js/components/UserProfile/ProfitAndLossView.vue
resources/js/components/UserProfile/PersonalAccounts.vue
```

#### Frontend - Property Components (1 file)
```
resources/js/components/NetWorth/Property/PropertyTaxCalculator.vue
```

#### Frontend - Dashboard Components (1 file)
```
resources/js/components/Dashboard/UKTaxesOverviewCard.vue
```

#### Frontend - Shared Components (1 file)
```
resources/js/components/Shared/ISAAllowanceSummary.vue
```

#### Frontend - UK Taxes Views (1 file)
```
resources/js/views/UKTaxes/UKTaxesDashboard.vue
```

#### Frontend - Dashboard View (1 file)
```
resources/js/views/Dashboard.vue
```

#### Frontend - Net Worth Views (1 file)
```
resources/js/views/NetWorth/NetWorthDashboard.vue
```

#### Frontend - Savings Views (2 files)
```
resources/js/views/Savings/SavingsAccountDetail.vue
resources/js/views/Savings/SavingsAccountDetailInline.vue (NEW)
```

#### Frontend - Savings Components (2 files)
```
resources/js/components/Savings/CurrentSituation.vue
resources/js/views/Savings/SavingsDashboard.vue
```

#### Frontend - Protection Components - Detail (1 file)
```
resources/js/components/Protection/PolicyDetail.vue
```

#### Frontend - Property Components (4 files)
```
resources/js/components/NetWorth/Property/PropertyDetail.vue
resources/js/components/NetWorth/Property/PropertyDetailInline.vue (NEW)
resources/js/components/NetWorth/PropertyCard.vue
resources/js/components/NetWorth/PropertyList.vue
```

#### Frontend - Trusts Views (1 file)
```
resources/js/views/Trusts/TrustsDashboard.vue
```

#### Frontend - Estate Views (1 file - Section 20)
```
resources/js/views/Estate/EstateDashboard.vue
```

#### Frontend - Protection Views (1 file - Section 20)
```
resources/js/views/Protection/ProtectionDashboard.vue
```

#### Frontend - Plans Views (2 files - Section 20)
```
resources/js/views/Plans/PlansDashboard.vue
resources/js/views/Plans/InvestmentSavingsPlan.vue
```

---

### Deployment Command (Local Build Required)

```bash
# 1. Build frontend locally
NODE_ENV=production npm run build

# 2. Create tarball of source files only (NO public/build)
tar -cvf nov26patch.tar \
  CLAUDE.md \
  app/Http/Controllers/Api/MortgageController.php \
  app/Http/Requests/StorePropertyRequest.php \
  app/Services/Protection/ComprehensiveProtectionPlanService.php \
  app/Services/Estate/ComprehensiveEstatePlanService.php \
  app/Services/Onboarding/OnboardingService.php \
  database/factories/MortgageFactory.php \
  resources/js/components/Onboarding/steps/ProtectionPoliciesStep.vue \
  resources/js/components/Onboarding/steps/AssetsStep.vue \
  resources/js/components/Onboarding/steps/IncomeStep.vue \
  resources/js/components/Onboarding/steps/LiabilitiesStep.vue \
  resources/js/components/Savings/SaveAccountModal.vue \
  resources/js/components/Savings/SaveGoalModal.vue \
  resources/js/components/Investment/GoalForm.vue \
  resources/js/components/Investment/HoldingForm.vue \
  resources/js/components/Investment/AccountForm.vue \
  resources/js/components/Investment/TaxFees.vue \
  resources/js/components/Investment/ISAOptimizationStrategy.vue \
  resources/js/components/Investment/RebalancingCalculator.vue \
  resources/js/components/Investment/PortfolioOverview.vue \
  resources/js/views/Investment/InvestmentDashboard.vue \
  resources/js/views/Investment/AccountDetailView.vue \
  resources/js/views/Investment/AccountSummaryPanel.vue \
  resources/js/views/Investment/AccountHoldingsPanel.vue \
  resources/js/views/Investment/AccountPerformancePanel.vue \
  resources/js/views/Investment/AccountFeesPanel.vue \
  resources/js/components/Protection/ProtectionOverviewCard.vue \
  resources/js/components/Protection/PolicyCard.vue \
  resources/js/components/Retirement/PensionCard.vue \
  resources/js/components/Retirement/AnnualAllowanceTracker.vue \
  resources/js/components/Retirement/DCPensionForm.vue \
  resources/js/components/Retirement/RetirementOverviewCard.vue \
  resources/js/components/Retirement/StatePensionForm.vue \
  resources/js/views/Retirement/RetirementDashboard.vue \
  resources/js/views/Retirement/RetirementReadiness.vue \
  resources/js/views/Retirement/PensionInventory.vue \
  resources/js/views/Retirement/PensionDetail.vue \
  resources/js/views/Retirement/DecumulationPlanning.vue \
  resources/js/views/Retirement/Projections.vue \
  resources/js/views/Retirement/ContributionsAllowances.vue \
  resources/js/views/Retirement/WhatIfScenarios.vue \
  resources/js/views/Retirement/PortfolioAnalysis.vue \
  resources/js/views/Retirement/Recommendations.vue \
  resources/js/views/Retirement/PensionDetailView.vue \
  resources/js/views/Retirement/PensionSummaryPanel.vue \
  resources/js/views/Retirement/PensionDetailsPanel.vue \
  resources/js/views/Retirement/PensionContributionsPanel.vue \
  resources/js/views/Retirement/PensionProjectionsPanel.vue \
  resources/js/views/Retirement/PensionAnalysisPanel.vue \
  resources/js/views/Estate/ComprehensiveEstatePlan.vue \
  resources/js/components/UserProfile/BalanceSheetView.vue \
  resources/js/components/UserProfile/CashflowView.vue \
  resources/js/components/UserProfile/ProfitAndLossView.vue \
  resources/js/components/UserProfile/PersonalAccounts.vue \
  resources/js/components/NetWorth/Property/PropertyTaxCalculator.vue \
  resources/js/components/Dashboard/UKTaxesOverviewCard.vue \
  resources/js/components/Shared/ISAAllowanceSummary.vue \
  resources/js/views/UKTaxes/UKTaxesDashboard.vue \
  resources/js/views/Dashboard.vue \
  resources/js/views/NetWorth/NetWorthDashboard.vue \
  resources/js/views/Savings/SavingsAccountDetail.vue \
  resources/js/views/Savings/SavingsAccountDetailInline.vue \
  resources/js/views/Savings/SavingsDashboard.vue \
  resources/js/components/Savings/CurrentSituation.vue \
  resources/js/components/Protection/PolicyDetail.vue \
  resources/js/components/NetWorth/Property/PropertyDetail.vue \
  resources/js/components/NetWorth/Property/PropertyDetailInline.vue \
  resources/js/components/NetWorth/PropertyCard.vue \
  resources/js/components/NetWorth/PropertyList.vue \
  resources/js/views/Trusts/TrustsDashboard.vue \
  resources/js/views/Estate/EstateDashboard.vue \
  resources/js/views/Protection/ProtectionDashboard.vue \
  resources/js/views/Plans/PlansDashboard.vue \
  resources/js/views/Plans/InvestmentSavingsPlan.vue

# 3. Upload tarball to server
scp nov26patch.tar user@server:/path/to/tengo/

# 4. On server: Extract and clear caches
ssh user@server
cd /path/to/tengo
tar -xvf nov26patch.tar
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# 5. Upload build folder separately (after local build)
scp -r public/build user@server:/path/to/tengo/public/
```

---

## Version Information

**Patch Version**: v0.2.15
**Date**: November 26, 2025
**Status**: Successfully Deployed and Tested
**Total Files Changed**: 82 (67 modified + 15 new)

---

Built with [Claude Code](https://claude.com/claude-code)
