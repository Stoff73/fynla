# Deployment Notes - January 30, 2026

**Deployment Status:** NOT DEPLOYED - All items pending

---

## Remove Individual Retirement Age Fields from Pension Forms

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Removed individual retirement age input fields from all three pension form types. These fields are no longer needed as retirement age will be managed centrally rather than per-pension.

### Changes Made

| Form | Field Removed | Notes |
|------|---------------|-------|
| DCPensionForm.vue | "Planned Retirement Age" | Also removed validation method, formData property, watcher logic |
| DBPensionForm.vue | "Normal Retirement Age" | Also removed formData property, watcher logic, apiData mapping |
| StatePensionForm.vue | "Your State Pension Age" | Also removed formData property, populateForm logic, validation, dataToSend mapping |

### DCPensionForm.vue Changes

| Change | Description |
|--------|-------------|
| Template | Removed retirement_age input field from "Expected Return and Retirement Age" grid (now just "Expected Return") |
| formData | Removed `retirement_age` property |
| validationErrors | Removed `retirement_age` property |
| watch | Removed code that populated retirement_age from `currentUser.target_retirement_age` |
| methods | Removed `validateRetirementAge()` method |
| handleSubmit | Removed retirement age validation call and check |

### DBPensionForm.vue Changes

| Change | Description |
|--------|-------------|
| Template | Removed normal_retirement_age input field from "Accrual Rate and Normal Retirement Age" grid (now just "Accrual Rate") |
| formData | Removed `normal_retirement_age` property |
| watch | Removed code that populated normal_retirement_age from `currentUser.target_retirement_age` |
| handleSubmit | Removed `normal_retirement_age` from apiData mapping |

### StatePensionForm.vue Changes

| Change | Description |
|--------|-------------|
| Template | Removed "Your State Pension Age" input field and helper text (link to gov.uk/state-pension-age) |
| formData | Removed `state_pension_age` property (was defaulting to 67) |
| populateForm | Removed state_pension_age mapping from statePension prop |
| handleSubmit | Removed state_pension_age validation check |
| handleSubmit | Removed `state_pension_age` from dataToSend mapping |

### Files Changed (3 files - Included in Build)

**Retirement Module:**
```text
resources/js/components/Retirement/DCPensionForm.vue
resources/js/components/Retirement/DBPensionForm.vue
resources/js/components/Retirement/StatePensionForm.vue
```

---

## Retirement Age Label Updates - Onboarding & User Profile

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Updated the retirement age field label and helper text in both onboarding and user profile to emphasise that this is the central retirement age used for all pension calculations.

### Changes Made

| Location | Before | After |
|----------|--------|-------|
| Onboarding (IncomeStep.vue) - Label | "What age do you want to retire?" | "Retirement Age" |
| Onboarding (IncomeStep.vue) - Helper text | "Your planned retirement age. This may be different to the age entered for your DC Pension Plans." | "Planned retirement age, used for all pension forecast calculations." |
| User Profile View Mode - Label | "Target Retirement Age:" | "Retirement Age:" |
| User Profile Edit Mode - Label | "Target Retirement Age" | "Retirement Age" |
| User Profile Edit Mode - Helper text | (none) | "Planned retirement age, used for all pension forecast calculations." |

### Files Changed (2 files - Included in Build)

**Onboarding:**
```text
resources/js/components/Onboarding/steps/IncomeStep.vue
```

**User Profile:**
```text
resources/js/components/UserProfile/PersonalInformation.vue
```

---

## Onboarding Completion - Remove Icon

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Removed the completion icon from the onboarding completion screen entirely. Previously there was a green checkmark for full completion and an orange warning triangle for partial completion.

### Before

- Full completion: Green checkmark icon
- Partial completion: Orange warning triangle icon

### After

- No icon displayed

### Files Changed (1 file - Included in Build)

**Onboarding:**
```text
resources/js/components/Onboarding/steps/CompletionStep.vue
```

---

## Onboarding Progress Bar - Color Updates

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Updated the onboarding progress bar step indicator colors for better visual distinction.

### Color Changes

| State | Before | After |
|-------|--------|-------|
| Current step (circle) | Blue (`bg-blue-600`) | Teal (`bg-teal-600`) |
| Current step (label) | Blue (`text-blue-600`) | Teal (`text-teal-600`) |
| Skipped step (circle) | Orange (`bg-orange-500`) | Blue (`bg-blue-500`) |
| Skipped step (label) | Orange (`text-orange-600`) | Blue (`text-blue-600`) |

### Files Changed (1 file - Included in Build)

**Onboarding:**
```text
resources/js/components/Onboarding/OnboardingWizard.vue
```

---

## Pension Pot Projection - Retirement Age Display

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Added prominent display of retirement age and years to retirement at the top of the Pension Pot Projection chart in the Retirement dashboard's Future Value tab. This uses the retirement age from the user's profile (set in Employment & Income / Personal Information).

### Changes Made

| Change | Description |
|--------|-------------|
| Retirement age info box | New teal-styled info box showing Retirement Age and Years to Retirement |
| Chart subtitle | Simplified subtitle (removed "to age X" as it's now shown above) |
| New CSS styles | Added `.retirement-age-info`, `.retirement-age-item`, `.retirement-age-label`, `.retirement-age-value`, `.retirement-age-divider` |

### Visual Design

The retirement age info displays inline with the summary cards in a 3-column layout:

```
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────────┐
│ PENSION POT     │  │ PROJECTED VALUE │  │ RETIREMENT  │  YEARS TO │
│ VALUE           │  │ (80%)           │  │ AGE         │  GO       │
│ £125,000        │  │ £450,000        │  │ 67          │  22       │
└─────────────────┘  └─────────────────┘  └─────────────────────────┘
     (blue)               (purple)               (teal)
```

### Files Changed (2 files - Included in Build)

**Net Worth Module:**

```text
resources/js/components/NetWorth/PensionList.vue
resources/js/components/NetWorth/PensionDetailInline.vue
```

**Retirement Module:**

```text
resources/js/components/Retirement/FutureValueTab.vue
```

### PensionDetailInline.vue Changes

| Change | Description |
|--------|-------------|
| Key Metrics Cards | Removed the 3 metric cards from the header (Current Fund Value, Monthly Contribution, Retirement Age) |
| DC Overview Section | Changed "Target Retirement Age" label to "Retirement Age", now uses `userRetirementAge` from auth store |

### PensionList.vue Changes

| Change | Description |
|--------|-------------|
| Risk Badge | Removed RiskBadge component from DC pension account cards |

---

## DC Pension Beneficiary Field

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Added beneficiary field to DC pension forms and detail view. Users can select their spouse (linked account) or enter a custom beneficiary name.

### Database Migration

New migration: `2026_01_30_100000_add_beneficiary_to_dc_pensions_table.php`

| Field | Type | Description |
|-------|------|-------------|
| beneficiary_id | bigint unsigned nullable | Foreign key to users table (for linked spouse) |
| beneficiary_name | varchar nullable | Custom beneficiary name |

### DCPension Model Changes

| Change | Description |
|--------|-------------|
| fillable | Added `beneficiary_id`, `beneficiary_name` |
| relationship | Added `beneficiary()` BelongsTo relationship |

### DCPensionForm.vue Changes

| Change | Description |
|--------|-------------|
| Template | Added beneficiary selection dropdown and custom name input |
| formData | Added `beneficiary_id`, `beneficiary_name` fields |
| data | Added `beneficiarySelection` state |
| computed | Added `spouseOption` to get spouse from userProfile store |
| methods | Added `handleBeneficiarySelection()`, `initializeBeneficiarySelection()` |
| watch | Updated to initialize beneficiary when editing |

### PensionDetailInline.vue Changes

| Change | Description |
|--------|-------------|
| DC Overview | Added Beneficiary row showing `pension.beneficiary_name` |

### Files Changed

**Database (Migration Required):**
```text
database/migrations/2026_01_30_100000_add_beneficiary_to_dc_pensions_table.php
```

**Backend:**
```text
app/Models/DCPension.php
```

**Frontend (Included in Build):**
```text
resources/js/components/Retirement/DCPensionForm.vue
resources/js/components/NetWorth/PensionDetailInline.vue
```

---

## Planning Assumptions Settings Page

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Added a new Planning Assumptions page in Settings allowing users to configure and override the default assumptions used in pension and investment projections. Users can customise inflation rate, expected return rate, and compound periods for both Pensions and Investments sections independently.

### Features

| Feature | Description |
|---------|-------------|
| Two sections | Pensions and Investments with independent settings |
| Editable fields | Inflation Rate (%), Expected Return (%), Compound Periods |
| Read-only fields | Weighted Average Fees, Years to Retirement, Total Value |
| Reset functionality | Reset button to clear overrides and use system defaults |
| Change detection | Unsaved changes warning before navigation |
| Status badges | Shows "Custom" or "Defaults" per section |

### Database Migration

New migration: `2026_01_30_120000_create_user_assumptions_table.php`

| Field | Type | Description |
|-------|------|-------------|
| user_id | bigint unsigned | Foreign key to users table |
| assumption_type | enum | 'pensions' or 'investments' |
| inflation_rate | decimal(5,2) nullable | User's custom inflation rate |
| return_rate | decimal(5,2) nullable | User's custom return rate |
| compound_periods | integer nullable | User's custom compound periods |

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/settings/assumptions` | Get all assumptions with defaults |
| PUT | `/api/settings/assumptions/{type}` | Update pensions or investments assumptions |

### Files Changed

**Database (Migration Required):**
```text
database/migrations/2026_01_30_120000_create_user_assumptions_table.php
```

**Backend:**
```text
app/Models/UserAssumption.php
app/Models/User.php (added assumptions() relationship)
app/Services/Settings/AssumptionsService.php
app/Http/Controllers/Api/Settings/AssumptionsController.php
routes/api.php
```

**Frontend (Included in Build):**
```text
resources/js/services/assumptionsService.js
resources/js/views/Settings/AssumptionsSettings.vue
resources/js/views/Settings.vue
resources/js/router/index.js
```

---

## Required Capital Calculations with Present Value

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Added detailed "Required Capital" calculations to the Retirement module. Users can access this by clicking the pension pot projection chart in the Retirement dashboard. The breakdown includes:

- 5 summary cards, "Assets included" section, "Other assets" section with toggles
- Dual progress bars showing current and forecasted progress
- Calculation assumptions inline with monthly contributions
- Year-by-year projection table including contributions
- Formula explanations

### Summary Cards

| Card | Description | Color |
|------|-------------|-------|
| Target Retirement Income | 75% of gross income (less pension contributions) or from profile | Blue |
| Required Capital at Retirement | Target Income / 4.7% withdrawal rate | Purple |
| Projected Pension Pot | Monte Carlo 80% confidence projection at retirement | Teal |
| Other Assets Added | Sum of investments + cash toggled on | Indigo |
| Gap to Target / Surplus | Required Capital - (Projected Pot + Other Assets) | Red/Green |

Card labels have fixed height (36px) with bottom-aligned text to ensure all values align horizontally across the row.

### Progress Bars

| Bar | Calculation | Purpose |
|-----|-------------|---------|
| Current | Total Included Assets / Required Capital Today | Shows current progress in today's money |
| Forecasted at Retirement | (Projected Pot + Other Assets) / Required Capital at Retirement | Shows projected progress at retirement |

### Asset Toggles

- "Assets included in calculation" section showing DC pensions plus any toggled investments/cash with total value
- "Other assets" section with toggle switches to include investments and cash in retirement capital calculation
- Toggle updates total included assets and recalculates progress bars in real-time
- Toggle label shows "Exclude" when included, "Include" when excluded

### Year-by-Year Table Columns

| Column | Description |
|--------|-------------|
| Year | Calendar year |
| Age | User's age |
| Projected Pot Value | FV of pension pot with contributions |
| Pot in Today's Money | Projected pot discounted by inflation |
| Target in Today's Money | Required capital discounted by inflation |

### Contributions Consistency

Both projections include monthly pension contributions:

| Projection | Source | Contributions |
|------------|--------|---------------|
| Monte Carlo (80% confidence) | `MonteCarloSimulator.simulate()` | ✅ Included monthly |
| Year-by-Year Table | `RequiredCapitalCalculator` | ✅ Included per period |

Contributions are calculated from `monthly_contribution_amount` on DC pensions, or derived from `employee_contribution_percent` + `employer_contribution_percent` × `annual_salary`.

### Investment Account Types

Only liquid investment accounts are shown in the retirement capital calculation. The following illiquid and employee share scheme types are excluded:

| Excluded Type | Reason |
|--------------|--------|
| VCT | Illiquid - 5 year minimum holding |
| EIS | Illiquid - 3 year minimum holding |
| Private Company | Illiquid - no market |
| Crowdfunding | Illiquid - no market |
| SAYE/Sharesave | Employee scheme - restricted |
| CSOP | Employee scheme - restricted |
| EMI | Employee scheme - restricted |
| Unapproved Options | Employee scheme - restricted |
| RSUs | Employee scheme - restricted |
| Other | Unknown liquidity |

Only ISA, GIA, SIPP, LISA, and JISA accounts are included as these are liquid and accessible for retirement funding.

### Formulas Used

| Formula | Description |
|---------|-------------|
| **Target Income (default)** | (Gross Income - Pension Contributions) × 75% |
| **Required Capital** | Target Income / 4.7% withdrawal rate |
| **Future Value with Contributions** | FV = PV × (1 + r/m)^(m×n) + PMT × [((1 + r/m)^(m×n) - 1) / (r/m)] |
| **Present Value** | PV = FV / (1 + inflation)^n - Discounting to today's money |

Where:
- PV = Current pension pot value
- PMT = Contribution per compounding period (monthly contribution × months per period)
- r = Net return rate (return - fees)
- m = Compounding periods per year (4 for quarterly)
- n = Years to retirement

The 75% multiplier accounts for the lower tax burden in retirement. Pension contributions are excluded from target income since they won't be made in retirement.

### API Endpoint

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/retirement/required-capital` | Returns full required capital breakdown |

### API Response Structure

```json
{
  "success": true,
  "data": {
    "required_income": 45000.00,
    "required_capital_at_retirement": 957446.81,
    "required_capital_today": 632456.23,
    "current_pot_value": 321456.23,
    "income_source": "profile|calculated",
    "assumptions": {
      "return_rate": 6.00,
      "net_return_rate": 5.00,
      "inflation_rate": 2.50,
      "compound_periods": 4,
      "fees_total": 1.00,
      "withdrawal_rate": 4.70,
      "monthly_contributions": 500.00
    },
    "retirement_info": {
      "current_age": 45,
      "retirement_age": 67,
      "years_to_retirement": 22
    },
    "year_by_year": [
      {
        "year_number": 0,
        "calendar_year": 2026,
        "age": 45,
        "accumulated_value": 321456.23,
        "present_value_today": 321456.23,
        "target_in_today_money": 557842.15,
        "is_retirement_year": false
      }
    ]
  }
}
```

### Files Changed

**Backend (New Service):**
```text
app/Services/Retirement/RequiredCapitalCalculator.php (NEW)
```

**Backend (Modified):**
```text
app/Http/Controllers/Api/RetirementController.php
app/Models/User.php (added retirementProfile() relationship)
routes/api.php
```

**Frontend (Included in Build):**
```text
resources/js/services/retirementService.js
resources/js/components/Retirement/RequiredCapitalDetail.vue (NEW)
resources/js/components/Retirement/FutureValueTab.vue
resources/js/components/NetWorth/PensionList.vue
```

---

## Centralised Required Capital Data Flow

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Centralised the required capital data (target income, required capital at retirement, included assets) in the Vuex retirement store so that the dashboard cards and Retirement Income Planner use the same values as the RequiredCapitalDetail view.

### Problem Solved

| Component | Before | After |
|-----------|--------|-------|
| PensionList.vue (dashboard) | Used `projections.income_drawdown.target_income` and simple `targetIncome / 0.047` | Uses `requiredCapital.required_income` and `requiredCapital.required_capital_at_retirement` from store |
| RetirementIncomeTab.vue | Used `retirementIncome.target_income` | Uses `requiredCapital.required_income` (with fallback) |
| RequiredCapitalDetail.vue | Stored data in local component state | Uses Vuex store state for data and asset toggles |

### Vuex Store Changes (retirement.js)

**New State:**
- `requiredCapital` - Full required capital data from API
- `requiredCapitalLoading` - Loading state
- `includedInvestmentIds` - Investment account IDs included in calculation
- `includedCashIds` - Cash account IDs included in calculation

**New Actions:**
- `fetchRequiredCapital()` - Fetches data from `/api/retirement/required-capital`
- `toggleIncludedInvestment(id)` - Toggle investment in/out of calculation
- `toggleIncludedCash(id)` - Toggle cash account in/out of calculation

**New Getters:**
- `requiredCapitalData`, `requiredCapitalLoading`
- `includedInvestmentIds`, `includedCashIds`
- `targetRetirementIncome`, `requiredCapitalAtRetirement`, `requiredCapitalToday`

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/store/modules/retirement.js
resources/js/components/NetWorth/PensionList.vue
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/components/Retirement/RequiredCapitalDetail.vue
```

---

## Retirement Income Planner Updates

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Updated the Retirement Income Planner with bond account support, improved ISA withdrawal rates, and investment account retirement inclusion filtering. Also hidden the spouse assets toggle pending future release.

### Changes Overview

| Change | Description |
|--------|-------------|
| Bond accounts | Added onshore and offshore bonds to available accounts with 5% tax-deferred withdrawal |
| ISA withdrawal rate | Changed from 4% to 4.7% sustainable withdrawal rate |
| Tax priority order | Updated to: PCLS → Bonds (5%) → ISA (4.7%) → DC Pension → GIA |
| Investment filtering | Only accounts with `include_in_retirement = true` appear in planner |
| Spouse toggle | Hidden in UI (backend functionality remains) |

### Database Migration

New migration: `2026_01_30_150000_add_include_in_retirement_to_investment_accounts.php`

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| include_in_retirement | boolean | false | Whether account is included in retirement planning |

### Tax Optimisation Priority Order

```text
1. PCLS (25% tax-free lump sum)
   └── Always use first if available

2. State Pension / DB Pension
   └── Guaranteed, unavoidable - uses PA first

3. Bond Withdrawals (5% tax-deferred)   ← NEW
   └── Uses cumulative 5% allowance - no immediate tax
   └── Preserves tax-deferred growth in remaining bond value

4. ISA Withdrawals (4.7% sustainable)   ← Rate changed from 4%
   └── 100% tax-free, preserves tax-advantaged growth
   └── After bonds as ISA has no cumulative limit

5. DC Pension Drawdown
   └── Use remaining PA, then basic rate

6. GIA / Savings
   └── Last resort - highest marginal rates
```

### Bond Account Types

| Type | Tax Treatment | 5% Rule |
|------|---------------|---------|
| Onshore Bond | Tax-deferred | 5% of original investment tax-free annually (cumulative) |
| Offshore Bond | Tax-deferred | 5% of original investment tax-free annually (cumulative, gross roll-up) |

### Investment Account Filtering

Only investment accounts marked with `include_in_retirement = true` appear in the Retirement Income Planner. This applies to:

- Stocks & Shares ISA
- GIA (General Investment Account)
- Onshore Bonds
- Offshore Bonds
- LISA (Lifetime ISA)

Users can toggle this setting per account via the investment account edit form.

### InvestmentAccount Model Changes

| Change | Description |
|--------|-------------|
| $fillable | Added `include_in_retirement` |
| $casts | Added `'include_in_retirement' => 'boolean'` |
| $attributes | Added default `'include_in_retirement' => false` |

### RetirementIncomeService Changes

| Change | Description |
|--------|-------------|
| Constants | Added `ISA_WITHDRAWAL_RATE` (0.047), `BOND_TAX_FREE_RATE` (0.05), `GIA_WITHDRAWAL_RATE` (0.04) |
| getAvailableAccounts() | Added onshore/offshore bond queries, filtered ISA/GIA by `include_in_retirement = true` |
| calculateDefaultAllocations() | Added Step 3 for bond withdrawals, updated ISA rate to 4.7% |
| sortByTaxEfficiency() | Added `tax_deferred` priority (2) between PCLS and tax_free |
| initializeFundBalances() | Added onshore/offshore bond balance queries |
| calculateAnnualWithdrawals() | Added `onshore_bond`, `offshore_bond` source type mappings |
| getFundTypeFromKey() | Added `bond` category mapping for chart |
| getGrowthRateForFund() | Added bond growth rate (4% same as other investments) |
| projectFundDepletion() | Added `bond` to aggregation arrays and yearData |

### Frontend Changes

| File | Change |
|------|--------|
| RetirementIncomeTab.vue | Hidden spouse assets toggle; changed income sources to 3-column grid layout |
| IncomeSourceSlider.vue | Updated styling for card-based 3-column grid (min-height, truncated names) |
| FundDepletionChart.vue | Already supports `bond` category in chart series |

### Income Sources Layout

Changed from single-column stacked list to responsive 3-column grid:

```text
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│ PCLS            │  │ Pension         │  │ ISA             │
│ Workplace DC    │  │ Workplace DC    │  │ Vanguard        │
│ Available: £25k │  │ Available: £50k │  │ Available: £45k │
│ ────────●───    │  │ ─────●────────  │  │ ────●─────────  │
│ £10,000/yr      │  │ £15,000/yr      │  │ £12,000/yr      │
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

Responsive breakpoints:
- Desktop (>1024px): 3 columns
- Tablet (768-1024px): 2 columns
- Mobile (<768px): 1 column

### Files Changed

**Database (Migration Required):**
```text
database/migrations/2026_01_30_150000_add_include_in_retirement_to_investment_accounts.php (NEW)
```

**Backend:**
```text
app/Models/Investment/InvestmentAccount.php
app/Services/Retirement/RetirementIncomeService.php
```

**Frontend (Included in Build):**
```text
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/components/Retirement/IncomeSourceSlider.vue
```

---

## Investment Retirement Inclusion Toggle Persistence

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Made the investment account "include in retirement" toggle persist to the database. When users toggle an investment account in the Required Capital detail view, the `include_in_retirement` flag is now saved to the database. Additionally, investment cards in the Investments dashboard now show a "Retirement" badge when the account is marked for retirement planning.

### Changes Overview

| Change | Description |
|--------|-------------|
| API endpoint | New PATCH endpoint to toggle `include_in_retirement` flag |
| Vuex action | Updated `toggleIncludedInvestment` to call API and persist |
| Initialization | `includedInvestmentIds` now initialized from accounts with `include_in_retirement = true` |
| Investment badge | New "Retirement" badge on investment cards when included in retirement planning |

### API Endpoint

| Method | Endpoint | Description |
|--------|----------|-------------|
| PATCH | `/api/investment/accounts/{id}/toggle-retirement` | Toggles `include_in_retirement` flag |

### API Response

```json
{
  "success": true,
  "data": {
    "id": 123,
    "include_in_retirement": true
  }
}
```

### Vuex Store Changes (retirement.js)

| Change | Description |
|--------|-------------|
| Import | Added `investmentService` import |
| `toggleIncludedInvestment()` | Now async, calls API to persist toggle |
| Side effect | Updates account in investment store state after successful toggle |

### Component Changes

| File | Change |
|------|--------|
| RequiredCapitalDetail.vue | Added `setIncludedInvestmentIds` action, initializes IDs from accounts with `include_in_retirement = true` on load |
| InvestmentList.vue | Added "Retirement" badge (teal) on investment cards when `include_in_retirement = true` |

### Badge Display Logic

```text
┌────────────────────────────────┐
│                    Joint       │  ← Purple badge (if joint ownership)
│                 Retirement     │  ← Teal badge (if include_in_retirement = true)
│  ┌─────┐                       │
│  │ ISA │  Vanguard             │
│  └─────┘                       │
│  Current Value  £25,000        │
└────────────────────────────────┘
```

### Files Changed

**Backend:**
```text
app/Http/Controllers/Api/InvestmentController.php
routes/api.php
```

**Frontend (Included in Build):**
```text
resources/js/services/investmentService.js
resources/js/store/modules/retirement.js
resources/js/components/Retirement/RequiredCapitalDetail.vue
resources/js/components/NetWorth/InvestmentList.vue
```

---

## Retirement Income Planner - Centralised Target Income Fix

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Fixed the Retirement Income Planner to use the correct centralised target income from RequiredCapitalCalculator instead of calculating its own incorrect default value.

### Problem

RetirementIncomeService had its own `calculateDefaultTargetIncome()` method that calculated 75% of net income (after tax), which produced different values than the centralised RequiredCapitalCalculator that uses 75% of (gross income - pension contributions).

### Solution

- Injected `RequiredCapitalCalculator` into `RetirementIncomeService`
- Both `getRetirementIncomeConfig()` and `calculateIncomeScenario()` now get target income from `RequiredCapitalCalculator->calculate()`
- Removed the local `calculateDefaultTargetIncome()` method

### Changes

| Method | Change |
|--------|--------|
| Constructor | Added `RequiredCapitalCalculator` dependency injection |
| `getRetirementIncomeConfig()` | Gets target income from `$this->requiredCapitalCalculator->calculate()` |
| `calculateIncomeScenario()` | Gets target income from `$this->requiredCapitalCalculator->calculate()` (unless custom target provided) |
| `calculateDefaultTargetIncome()` | **REMOVED** - no longer needed |

### Files Changed

**Backend:**
```text
app/Services/Retirement/RetirementIncomeService.php
```

---

## Retirement Income Planner - Combined Pension Pot & Sustainability

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Refactored the Retirement Income Planner to use the combined Pension Pot with projected values (80% Monte Carlo confidence) instead of individual DC pensions. The system now:

1. Uses **combined Pension Pot** (not individual pensions) - always labelled "Pension Pot"
2. Uses **80% Monte Carlo projected value** at retirement (from RetirementProjectionService)
3. Calculates PCLS (25% of projected pot) and drawdown (75%) from that projected value
4. Checks if target income will deplete funds before age 100
5. Adjusts income to sustainable level if funds would deplete early
6. Shows message with gov.uk link if no state pension data entered
7. Accounts for state pension timing in drawdown calculations

### Key Change: Projected Pension Pot

Previously, the Retirement Income Planner used individual DC pension current values and created separate entries for each pension. Now it:

| Before | After |
|--------|-------|
| Individual DC pensions (Workplace, SIPP, etc.) | Combined "Pension Pot" |
| Current fund values | 80% Monte Carlo projected value at retirement |
| Multiple PCLS entries (one per pension) | Single "Pension Pot - PCLS" entry |
| Multiple Drawdown entries | Single "Pension Pot - Drawdown" entry |

### Backend Changes (RetirementIncomeService.php)

| Change | Description |
|--------|-------------|
| Constructor | Added `RetirementProjectionService` dependency |
| `getRetirementIncomeConfig()` | Gets projected pot from `$this->projectionService->projectPensionPot()` |
| `getAvailableAccounts()` | Now accepts `$projectedPensionPot` parameter; creates single "Pension Pot" with sub-accounts |
| Source types | Changed from `dc_pension_pcls/drawdown` to `pension_pot_pcls/drawdown` |
| Fund tracking | Uses `pension_pot` key instead of individual `dc_pension_{id}` keys |
| `calculateTotalFunds()` | New method (replaces `calculateTotalPensionPot`) |
| `getFundTypeFromKey()` | Returns `pension_pot` for combined pension pot |
| `projectFundDepletion()` | Uses `pension_pot` category in projections |

### Frontend Changes

| File | Change |
|------|--------|
| IncomeSourceSlider.vue | Added `pension_pot_pcls` and `pension_pot_drawdown` labels |
| FundDepletionChart.vue | Changed `dc_pension` to `pension_pot` in series and type detection |

### Features

| Feature | Description |
|---------|-------------|
| Fund depletion check | Checks if target annual income will exhaust funds before age 100 |
| Sustainable income calculation | Uses binary search to find maximum income that lasts to age 100 |
| State pension status | Shows info banner with gov.uk link if no state pension forecast entered |
| Income adjusted notice | Shows warning banner if income was reduced to ensure sustainability |
| State pension timing | Accounts for reduced fund drawdown after state pension starts |

### State Pension Message

When no state pension data is entered, an info banner appears:

```text
┌─────────────────────────────────────────────────────────────────┐
│ ℹ No State Pension forecast entered. Your projections do not   │
│   include State Pension income.                                 │
│   Check your State Pension forecast on GOV.UK →                 │
└─────────────────────────────────────────────────────────────────┘
```

Link opens: https://www.gov.uk/check-state-pension

### Income Adjusted Notice

When target income would deplete funds before age 100, an orange warning banner appears:

```text
┌─────────────────────────────────────────────────────────────────┐
│ ⚠ Income adjusted from £45,000 to £38,500/year to ensure       │
│   funds last to age 100.                                        │
└─────────────────────────────────────────────────────────────────┘
```

### Summary Card Updates

| Card Label | When |
|------------|------|
| "Target Annual Income" | When income not adjusted |
| "Optimised Income" | When income was adjusted for sustainability |

Subtitle changes:
- "From retirement profile" or "Custom target" when not adjusted
- "Adjusted to last until age 100" when adjusted

### API Response Changes

New fields in retirement income API response:

```json
{
  "target_income": 45000.00,
  "optimised_income": 38500.00,
  "income_was_adjusted": true,
  "projected_pension_pot": 650000.00,
  "total_funds": 720000.00,
  "depletion_check": {
    "is_sustainable": false,
    "depletion_year": 28,
    "depletion_age": 95,
    "funds_at_100": 0
  },
  "state_pension_status": {
    "has_data": false,
    "annual_amount": 0,
    "state_pension_age": 67,
    "already_receiving": false,
    "starts_at_retirement": false,
    "years_until_state_pension": 0,
    "message": "No State Pension forecast entered...",
    "link": "https://www.gov.uk/check-state-pension",
    "link_text": "Check your State Pension forecast on GOV.UK"
  },
  "available_accounts": [
    {
      "id": "pension_pot",
      "type": "pension_pot",
      "name": "Pension Pot",
      "value": 650000.00,
      "pcls_available": 162500.00,
      "is_projected": true,
      "sub_accounts": [
        {"source_type": "pension_pot_pcls", "name": "Pension Pot - Tax-Free Cash (PCLS)", "max_amount": 162500.00},
        {"source_type": "pension_pot_drawdown", "name": "Pension Pot - Drawdown", "max_amount": 487500.00}
      ]
    }
  ]
}
```

### Backend Methods Added/Modified

| Method | Description |
|--------|-------------|
| `getStatePensionStatus()` | Returns state pension info with gov.uk link if not entered |
| `calculateTotalFunds()` | Sums all drawable capital (replaces `calculateTotalPensionPot`) |
| `checkFundDepletion()` | Checks if income depletes funds before age 100 |
| `calculateSustainableIncome()` | Binary search for max sustainable withdrawal rate |
| `initializeFundBalances()` | Now accepts `$projectedPensionPot` parameter |
| `projectFundDepletion()` | Now accepts `$projectedPensionPot` parameter |

### Calculation Logic

```text
1. Get projected pension pot from RetirementProjectionService (80% Monte Carlo)
2. Get target income from RequiredCapitalCalculator (centralised)
3. Create single "Pension Pot" account with PCLS (25%) and drawdown (75%)
4. Get state pension status (check if entered, get timing)
5. Calculate total funds = projected pot + other assets (ISAs, bonds, etc.)
6. Check if target income is sustainable:
   - Simulate year-by-year withdrawals to age 100
   - Account for state pension reducing withdrawals when it starts
   - Apply 4% annual growth on remaining funds
7. If not sustainable, calculate sustainable income:
   - Binary search between £0 and 15% of pot
   - Find maximum income where funds last to age 100
8. Use optimised income for allocations and tax calculations
```

### Controller Changes (RetirementController.php)

| Change | Description |
|--------|-------------|
| Validation | `source_id` now accepts string values (for 'pension_pot') |

### Files Changed

**Backend:**
```text
app/Services/Retirement/RetirementIncomeService.php
app/Http/Controllers/Api/RetirementController.php
```

**Frontend (Included in Build):**
```text
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/components/Retirement/IncomeSourceSlider.vue
resources/js/components/Retirement/TaxBreakdownCard.vue
resources/js/components/Retirement/FundDepletionChart.vue
```

---

## Retirement Income Planner - Monte Carlo Projected Asset Values

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Updated the Retirement Income Planner to project ALL investment asset values using Monte Carlo 80% confidence simulations, matching the Investment module projections. Previously, ISAs, bonds, and GIAs used simple 4% compound growth. Now all investment assets use the same Monte Carlo simulation as shown in their Investment detail pages.

### Problem

The Income Planner was using simple compound growth (e.g., 4% annual) which gave different values than the Monte Carlo projections shown in the Investment module. For example, a £150,000 ISA projected to retirement would show:
- Old method (4% compound): £280,947
- Monte Carlo 80%: £590,862

This inconsistency confused users who saw different numbers in different parts of the app.

### Solution

Investment accounts (ISAs, bonds, GIAs) now use `InvestmentProjectionService::getAccountProjectedValue80()` to get the Monte Carlo 80% confidence projected value - the same value shown in the Investment module's projection charts.

### New Method Added

**InvestmentProjectionService.php:**
```php
public function getAccountProjectedValue80(InvestmentAccount $account, User $user, int $years): float
```
Returns the 80% probability (percentile_20) projected value for a single account using Monte Carlo simulation.

### Assets Using Monte Carlo Projections

| Asset Type | Projection Method |
|------------|-------------------|
| Investment ISA | Monte Carlo 80% |
| Onshore Bond | Monte Carlo 80% |
| Offshore Bond | Monte Carlo 80% |
| GIA | Monte Carlo 80% |
| Cash ISA | Simple 2% growth (no Monte Carlo for cash) |
| Savings | Simple 2% growth (no Monte Carlo for cash) |

### API Response Changes

Each available account now includes projection details:

```json
{
  "id": 123,
  "type": "isa_investment",
  "name": "Vanguard ISA",
  "current_value": 150000.00,
  "value": 590861.82,
  "is_projected": true,
  "years_projected": 16,
  "projection_type": "monte_carlo_80"
}
```

| Field | Description |
|-------|-------------|
| `current_value` | Today's value |
| `value` | Monte Carlo 80% projected value at retirement |
| `is_projected` | Always `true` for non-income assets |
| `years_projected` | Years until retirement |
| `projection_type` | `monte_carlo_80` for investments, omitted for cash |

### Backend Changes

**InvestmentProjectionService.php:**
| Method | Description |
|--------|-------------|
| `getAccountProjectedValue80()` | **NEW** - Returns 80% Monte Carlo projected value for single account |

**RetirementIncomeService.php:**
| Method | Change |
|--------|--------|
| Constructor | Added `InvestmentProjectionService` dependency |
| `getAvailableAccounts()` | Uses Monte Carlo 80% for investment accounts |
| `initializeFundBalances()` | Uses Monte Carlo 80% for investment accounts |

### Example Projection

For a 52-year-old retiring at 68 (16 years), with a £150,000 ISA:

| Asset | Current Value | Monte Carlo 80% at 68 |
|-------|---------------|-----------------------|
| Investment ISA | £150,000 | £590,862 |

Compare to old simple compound growth method:
| Asset | Current Value | Old 4% Compound |
|-------|---------------|-----------------|
| Investment ISA | £150,000 | £280,947 |

### Files Changed

**Backend:**
```text
app/Services/Investment/InvestmentProjectionService.php
app/Services/Retirement/RetirementIncomeService.php
```

---

## Frontend Fix: ISA Account Matching in Income Planner

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Fixed the Retirement Income Planner to correctly match ISA accounts with their allocations. Previously, the `getAccountForAllocation()` method failed to match ISA accounts because:

1. Allocation `source_type` was `'isa'`
2. Account `type` was `'isa_investment'` or `'isa_cash'`

These didn't match, causing the component to use a hardcoded fallback of £50,000.

### Problem

```javascript
// OLD CODE - Failed to match
a => a.type === allocation.source_type  // 'isa_investment' !== 'isa' ❌
```

### Solution

Updated `getAccountForAllocation()` to handle ISA type variants:

```javascript
// NEW CODE - Handles all ISA types
if (sourceType === 'isa') {
  return a.type === 'isa' || a.type === 'isa_investment' || a.type === 'isa_cash';
}
```

Also removed the hardcoded £50,000 fallback in `IncomeSourceSlider.vue`:

```javascript
// OLD: return this.account?.value || this.allocation.max_amount || 50000;
// NEW: return this.account?.value || this.allocation.max_amount || 0;
```

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/components/Retirement/IncomeSourceSlider.vue
```

---

## Investment Monte Carlo - "To Retirement" Option & Color Fix

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Enhanced the Investment account Monte Carlo projection chart with:
1. Added "To Retirement" option in the years dropdown when the account is marked `include_in_retirement = true`
2. Fixed chart colors to use proper blue gradient for probability bands
3. Automatically defaults to "To Retirement" view when account is included in retirement planning

### "To Retirement" Dropdown Option

When an investment account has `include_in_retirement = true`:
- A new "To Retirement (X yrs)" option appears at the top of the dropdown
- Uses the user's years to retirement (retirement age - current age)
- Automatically selected by default when viewing such accounts
- Shows "at retirement (X years)" instead of "in X years" in the projected value card

### Retirement Account Projection Behavior

For accounts marked with `include_in_retirement = true`:
- **No dropdown shown** - replaced with a teal "To Retirement (X yrs)" badge
- **Projection automatically shows years to retirement** - uses user's retirement age from profile
- **Chart sliced to retirement date** - shows projection only up to retirement year

For accounts NOT marked for retirement:
- Standard dropdown with 5, 10, 20, 30 year options
- User can select any projection period

### Chart Colors and Series

Uses the standard Monte Carlo color scheme from design system:

| Series | Color | Source |
|--------|-------|--------|
| 90% Probability | `PRIMARY_COLORS[900]` | Dark navy |
| 85% Probability | `PRIMARY_COLORS[600]` | Trust blue |
| 80% Probability | `SUCCESS_COLORS[500]` | Green |
| 75% Probability | `SUCCESS_COLORS[400]` | Light green |

Uses gradient fill matching other Monte Carlo charts in the application.

### Component Changes (AccountPerformancePanel.vue)

| Change | Description |
|--------|-------------|
| Import | Added `mapState` from Vuex |
| Computed | Added `isIncludedInRetirement`, `yearsToRetirement`, `projectionYearOptions` |
| Template | Updated dropdown to use dynamic options, updated projected value text |
| Methods | Updated `updateProjectionData()` to handle "retirement" option |
| Watch | Defaults to "retirement" when account is included in retirement |
| Mounted | Fetches retirement data if profile not loaded (via `retirement/fetchRetirementData`) |

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/views/Investment/AccountPerformancePanel.vue
```

---

## Retirement Income Planner UI Simplification

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Simplified the Retirement Income Planner UI by removing colour backgrounds from cards and tax breakdowns, and removing the Tax Band Usage section from the TaxBreakdownCard component.

### Changes Made

| Component | Change |
|-----------|--------|
| RetirementIncomeTab.vue | Removed gradient backgrounds from summary cards (Target Income, Net Income, Tax Rate) |
| TaxBreakdownCard.vue | Removed Tax Band Usage section entirely |
| TaxBreakdownCard.vue | Changed format to match TaxIncomeCard.vue (simpler two-column layout) |
| IncomeSourceSlider.vue | Simplified badge and tax info styling |

### TaxBreakdownCard Format

Changed from detailed tax band usage breakdown to simplified format matching TaxIncomeCard:

**Before:**
- Tax Band Usage section showing Personal Allowance used/remaining, Basic Rate used/remaining, Higher Rate used/remaining
- Complex scoped CSS

**After:**
- Simple Income Sources section
- Tax Calculation section with tax-free income and band calculations
- Summary section with Gross Income, Total Tax, Net Income
- Optimisation Tips section
- Tailwind utility classes instead of scoped CSS

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/components/Retirement/TaxBreakdownCard.vue
resources/js/components/Retirement/IncomeSourceSlider.vue
```

---

## Fund Depletion Calculation Fix

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Fixed critical issues with the Fund Depletion Chart that was showing incorrect values:
1. Random accounts not included in the retirement plan were appearing
2. ISA balance showed £240k at age 100 instead of £0 (funds were not being depleted)

### Root Causes

| Issue | Cause | Fix |
|-------|-------|-----|
| Random accounts appearing | `initializeFundBalances()` was querying ALL accounts from database | Only include accounts that are in the income allocations |
| ISA not depleting | Key mismatch: withdrawals used `isa` but balances used `isa_savings_X` or `isa_investment_X` | Match ISA type in `calculateAnnualWithdrawals()` to use correct key |

### New Depletion Logic

The fund depletion now:
1. Uses target income from pension pot projection detail view as starting value
2. Calculates sustainable withdrawal using PMT formula to deplete funds to £0 at age 100
3. Distributes withdrawals tax-efficiently across accounts (ISA → Pension → Bond → GIA → Savings)
4. Only includes accounts that are actually in the income allocations

### PMT Formula for Sustainable Withdrawal

```
PMT = PV × (r(1+r)^n) / ((1+r)^n - 1)

Where:
- PV = Total starting funds (sum of all included accounts)
- r = Weighted average growth rate across fund types
- n = Years in retirement (100 - retirement age)
```

If the target income from the user is higher than the sustainable withdrawal, the actual withdrawal is capped at the sustainable rate to ensure funds last to age 100.

### Weighted Average Growth Rate

Growth rates are weighted by fund balance:

| Fund Type | Growth Rate |
|-----------|-------------|
| Pension Pot | 4% |
| ISA | 4% |
| Bond | 4% |
| GIA | 4% |
| Savings | 2% |

### Tax-Efficient Withdrawal Order

```text
1. ISA (tax-free)
2. Pension Pot
3. Bond (tax-deferred)
4. GIA
5. Savings
```

### Backend Changes (RetirementIncomeService.php)

| Method | Change |
|--------|--------|
| `projectFundDepletion()` | Complete rewrite with PMT-based sustainable withdrawal calculation |
| `initializeFundBalances()` | Only include accounts that are in the income allocations |
| `calculateAnnualWithdrawals()` | Fixed ISA key matching to check both `isa_savings` and `isa_investment` types |
| `calculateWeightedGrowthRate()` | **NEW** - Calculate weighted average growth rate across fund types |
| `calculateSustainableWithdrawalRate()` | **NEW** - PMT formula implementation for sustainable withdrawal |

### Files Changed

**Backend:**
```text
app/Services/Retirement/RetirementIncomeService.php
```

---

## Fund Depletion Year-by-Year Table

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Added a year-by-year breakdown table below the Fund Depletion Chart showing detailed projections for each year of retirement.

### Table Columns

| Column | Description |
|--------|-------------|
| Age | User's age for that year |
| Withdrawal | Total withdrawal for the year (shown in red with minus sign) |
| [Fund Type] | Balance for each active fund (Pension Pot, ISA, Bond, GIA, Savings) |
| Growth | Total growth across all funds for the year (shown in green with plus sign) |
| Total Balance | Combined balance of all funds |

### Features

| Feature | Description |
|---------|-------------|
| Summary view | Shows every 5 years plus first and last year |
| Full view | Toggle button to show all years |
| Per-fund withdrawals | Sub-row showing withdrawal amount per fund type (in red) |
| Depletion highlighting | Orange background when total funds reach £0 |
| Responsive columns | Only shows columns for fund types with non-zero starting values |

### Visual Design

```text
┌─────┬────────────┬────────────┬────────────┬──────────┬──────────────┐
│ Age │ Withdrawal │ Pension    │ ISA        │ Growth   │ Total Balance│
├─────┼────────────┼────────────┼────────────┼──────────┼──────────────┤
│ 68  │ -£38,500   │ £650,000   │ £150,000   │ +£32,000 │ £793,500     │
│     │            │ -£25,000   │ -£13,500   │          │              │
├─────┼────────────┼────────────┼────────────┼──────────┼──────────────┤
│ 73  │ -£38,500   │ £520,000   │ £85,000    │ +£24,200 │ £590,700     │
│     │            │ -£30,000   │ -£8,500    │          │              │
└─────┴────────────┴────────────┴────────────┴──────────┴──────────────┘
```

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/Retirement/FundDepletionChart.vue
```

---

## Migration Fix: Column Existence Checks

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Fixed database migration `2026_01_29_130208_add_missing_contribution_fields_to_investment_accounts.php` that was failing with "Duplicate column name" error during tests.

### Problem

The migration was attempting to add columns that already existed on some environments, causing `SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'monthly_contribution_amount'` error.

### Solution

Added `Schema::hasColumn()` checks before each column addition:

```php
Schema::table('investment_accounts', function (Blueprint $table) {
    if (! Schema::hasColumn('investment_accounts', 'monthly_contribution_amount')) {
        $table->decimal('monthly_contribution_amount', 12, 2)->nullable()...
    }
    if (! Schema::hasColumn('investment_accounts', 'contribution_frequency')) {
        $table->enum('contribution_frequency', ['monthly', 'quarterly', 'annually'])...
    }
    // ... same pattern for planned_lump_sum_amount and planned_lump_sum_date
});
```

### Files Changed

**Database:**
```text
database/migrations/2026_01_30_160000_add_contribution_fields_to_investment_accounts.php
```

---

## Rebuild Required: YES

Frontend Vue components changed. Full rebuild required:

```bash
./deploy/fynla-org/build.sh
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

### Step 3: Upload PHP Files

Upload the following PHP files to their corresponding paths on the server:

**Models:**
```text
app/Models/UserAssumption.php → ~/www/fynla.org/public_html/app/Models/
app/Models/User.php → ~/www/fynla.org/public_html/app/Models/
app/Models/DCPension.php → ~/www/fynla.org/public_html/app/Models/
app/Models/Investment/InvestmentAccount.php → ~/www/fynla.org/public_html/app/Models/Investment/
```

**Services:**
```text
app/Services/Settings/AssumptionsService.php → ~/www/fynla.org/public_html/app/Services/Settings/
app/Services/Retirement/RequiredCapitalCalculator.php → ~/www/fynla.org/public_html/app/Services/Retirement/
app/Services/Retirement/RetirementIncomeService.php → ~/www/fynla.org/public_html/app/Services/Retirement/
app/Services/Investment/InvestmentProjectionService.php → ~/www/fynla.org/public_html/app/Services/Investment/
```
(Create the `Settings` directory if it doesn't exist)

**Controllers:**
```text
app/Http/Controllers/Api/Settings/AssumptionsController.php → ~/www/fynla.org/public_html/app/Http/Controllers/Api/Settings/
app/Http/Controllers/Api/RetirementController.php → ~/www/fynla.org/public_html/app/Http/Controllers/Api/
app/Http/Controllers/Api/InvestmentController.php → ~/www/fynla.org/public_html/app/Http/Controllers/Api/
```
(Create the `Settings` directory if it doesn't exist)

**Routes:**
```text
routes/api.php → ~/www/fynla.org/public_html/routes/
```

**Migrations:**
```text
database/migrations/2026_01_30_160000_add_contribution_fields_to_investment_accounts.php → ~/www/fynla.org/public_html/database/migrations/
database/migrations/2026_01_30_100000_add_beneficiary_to_dc_pensions_table.php → ~/www/fynla.org/public_html/database/migrations/
database/migrations/2026_01_30_120000_create_user_assumptions_table.php → ~/www/fynla.org/public_html/database/migrations/
database/migrations/2026_01_30_150000_add_include_in_retirement_to_investment_accounts.php → ~/www/fynla.org/public_html/database/migrations/
```

### Step 4: Run Migrations (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
```

### Step 5: Clear Cache (SSH)

```bash
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear
```

---

## Fund Depletion - Fix Incorrect Income Reduction

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Fixed critical bugs in the Retirement Income Planner:
1. Income was being reduced even when users had £1M+ in funds
2. Tax-free sources (PCLS, ISA, Bonds) were not being fully utilised before taxable sources
3. PCLS was arbitrarily capped at 25% of target income instead of spread over retirement years

### Root Causes

1. **Incorrect depletion check**: `checkFundDepletion()` was using FULL target income (including state pension and DB pensions) when these don't withdraw from funds

2. **PCLS not spread correctly**: PCLS was calculated as `min(max, target × 0.25)` instead of `PCLS Total ÷ Years in Retirement`

3. **Tax-free not maximised**: Code would move to taxable sources before fully exhausting tax-free sources

### Solution

#### 1. PCLS Spread Evenly Over Retirement

```php
// OLD (wrong)
$pclsAmount = min($subAccount['max_amount'], $remainingTarget * 0.25);

// NEW (correct)
$pclsAnnualAvailable = $pclsTotal / $yearsInRetirement;
$pclsAmount = min($pclsAnnualAvailable, $remainingTarget);
```

#### 2. Maximise Tax-Free Before Any Taxable

New allocation order in `calculateDefaultAllocations()`:
1. Calculate ALL available tax-free income per year (PCLS + Bond 5% + ISA 4.7%)
2. Allocate from PCLS first (spread evenly)
3. Allocate from Bond 5% next
4. Allocate from ISA next
5. ONLY if tax-free doesn't cover target, use taxable (Pension Drawdown → GIA → Savings)

#### 3. Only Reduce Income If Funds Actually Deplete

```php
// OLD (wrong) - reduced income based on arbitrary tolerance
if ($testResult['depletes_early'] && $testResult['depletion_age'] < 95) {
    // Reduce income
}

// NEW (correct) - only reduce if funds actually hit zero
if ($testResult['depletes_early'] && $testResult['final_balance'] <= 0) {
    // Reduce income
}
```

### Example: Correct Tax-Free Maximisation

```
User has:
  Pension Pot: £1,000,000 (PCLS = £250,000)
  ISA: £400,000
  Bonds: £200,000

Years in Retirement: 32 (age 68 to 100)
Target Income: £80,000

Tax-Free Available Per Year:
  PCLS: £250,000 ÷ 32 = £7,812
  Bond 5%: £200,000 × 5% = £10,000
  ISA 4.7%: £400,000 × 4.7% = £18,800
  TOTAL TAX-FREE: £36,612

Allocation (NEW):
  PCLS: £7,812 (tax-free)
  Bond: £10,000 (tax-deferred)
  ISA: £18,800 (tax-free)
  Pension Drawdown: £43,388 (taxable - only the gap)

Tax: Only on £43,388 (uses PA first)
```

### Changes Made

| Method | Change |
|--------|--------|
| `calculateDefaultAllocations()` | Complete rewrite - PCLS spread over years, maximise tax-free first |
| `projectFundDepletion()` | Only adjust if `final_balance <= 0`, not based on arbitrary age tolerance |
| `getRetirementIncomeConfig()` | Removed redundant `checkFundDepletion()` pre-check |
| `calculateIncomeScenario()` | Same fix - removed redundant depletion check |

### Files Changed

**Backend:**
```text
app/Services/Retirement/RetirementIncomeService.php
```

---

## Fund Depletion Chart - Tooltip Age Fix

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Fixed the Fund Depletion Chart tooltip showing incorrect ages (e.g., "Age 35") when hovering over the chart. The tooltip now correctly shows the actual age from the projections (e.g., "Age 67", "Age 75").

### Root Cause

ApexCharts was passing the data point index to the tooltip x formatter instead of the actual category value (age). The old code assumed `val` was the age, but it was receiving 0, 1, 2, etc.

### Solution

Updated the tooltip formatter to use `dataPointIndex` to look up the actual age from the projections array:

```javascript
// OLD (wrong)
x: {
  formatter: (val) => `Age ${val}`,
}

// NEW (correct)
x: {
  formatter: (val, { dataPointIndex }) => {
    const age = this.projections[dataPointIndex]?.age;
    return age ? `Age ${age}` : `Age ${val}`;
  },
}
```

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/Retirement/FundDepletionChart.vue
```

---

## Tax-Free Priority Withdrawal - Zero Tax Optimisation

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Completely rewrote the retirement income allocation and projection logic to maximize tax-free income. The system now uses a strict priority-based withdrawal order that ensures **ZERO TAX is paid while tax-free sources exist**.

### The Problem (Before)

The previous logic used PMT (Payment) calculations for ALL sources independently:
- Bond 5% PMT: £17,293
- PCLS PMT: £3,471
- ISA PMT: £27,953
- **Pension Drawdown PMT: £20,826** (TAXABLE!)
- Total: £69,543

Result: **£1,651 tax paid EVERY YEAR** even though user had £500K+ in ISA and £120K+ in PCLS!

### The Solution (After)

New priority-based withdrawal order:

| Priority | Source | Tax Treatment | Logic |
|----------|--------|---------------|-------|
| 1 | Bond 5% | Tax-deferred | PMT to deplete at age 100 (MANDATORY) |
| 2 | PCLS | Tax-free | Fill the gap after Bond |
| 3 | ISA | Tax-free | Fill the remaining gap |
| 4 | Pension Drawdown | TAXABLE | **ONLY when PCLS + ISA are DEPLETED** |

### Example Results

For £75,000 target annual income:

| Age Range | Tax Status | Source | Notes |
|-----------|------------|--------|-------|
| 65-78 | **£0 TAX** | Bond + PCLS + ISA | Tax-free sources cover full income |
| 79-92 | Tax paid (~£9K/yr) | Pension Drawdown | ISA depleted, drawdown needed |
| 93+ | **£0 TAX** | Bond only | Only tax-deferred source remains |

### Key Behaviour Changes

| Aspect | Before | After |
|--------|--------|-------|
| Allocation logic | Fixed PMT for each source | Fill gap with tax-free first |
| Pension Drawdown | Used from day 1 | Only used when tax-free exhausted |
| Tax in early years | £1,651/year | £0 |
| ISA withdrawal | PMT only (~£28K/yr) | Fill gap (~£54K/yr) |
| Drawdown balance | Shrinking | Growing (untouched) |

### Year-by-Year Table Updates

Added two new columns to track tax impact:

| Column | Description | Display |
|--------|-------------|---------|
| Taxable Drawdown | Portion of drawdown exceeding PA | Red when > £0, Green £0 when tax-free |
| Tax Paid | Actual tax paid that year | Red with minus sign, or green £0 |

When pension drawdown is used, shows:
- Main value: Taxable amount (e.g., "£43,024")
- Sub-text: PA coverage (e.g., "(£12,570 in PA)")

### Backend Changes (RetirementIncomeService.php)

**Allocation Logic (`buildFlexibleIncomeAllocations`):**

| Step | Change |
|------|--------|
| Bond | Keep PMT calculation (mandatory) |
| PCLS | Changed from PMT to "fill the gap" |
| ISA | Changed from PMT to "fill remaining gap" |
| Drawdown | Only allocated if gap remains after tax-free |

**Projection Logic (`projectFundDepletion`):**

| Change | Description |
|--------|-------------|
| Priority withdrawal | New helper functions for priority-based withdrawal each year |
| `$withdrawFromFundType()` | Closure to withdraw from specific fund type |
| `$getAvailableBalance()` | Closure to get remaining balance by fund type |
| Order enforced | Bond → PCLS → ISA → Drawdown → GIA → Savings |

### Frontend Changes (FundDepletionChart.vue)

| Change | Description |
|--------|-------------|
| Taxable Drawdown column | Shows taxable portion with PA annotation |
| Tax Paid column | Shows tax in red when paid, green £0 when tax-free |
| Conditional styling | Red/green based on whether tax is owed |

### Files Changed

**Backend:**
```text
app/Services/Retirement/RetirementIncomeService.php
```

**Frontend (Included in Build):**
```text
resources/js/components/Retirement/FundDepletionChart.vue
```

---

## Income Source Cards Simplification

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Simplified the Income Source cards in the Retirement Income Planner to show only essential information: account type, account name, and projected fund value. Removed interactive sliders and tax information.

### Changes Made

| Change | Description |
|--------|-------------|
| Removed sliders | Interactive sliders removed from all income source cards |
| Removed tax labels | Tax rate % and tax treatment labels removed |
| Removed annual withdrawal | "Annual withdrawal" display removed |
| Simplified layout | Account type badge → Account name → "Projected fund value" label → Value |
| Always show Pension Drawdown | Drawdown card now shows even when £0 is being withdrawn |

### Card Layout (Before)

```text
┌────────────────────────────────────────────────┐
│ PCLS    Pension Pot - PCLS    │ 0%  Tax-free  │
│ Available: £121,484                            │
│ Annual withdrawal            £3,471           │
│ ─────────────●────────────────                │
│ £0                        £121,484            │
└────────────────────────────────────────────────┘
```

### Card Layout (After)

```text
┌─────────────────────────┐
│ PCLS                    │
│ Pension Pot - PCLS      │
│ Projected fund value    │
│ £121,484                │
└─────────────────────────┘
```

### Backend Changes (RetirementIncomeService.php)

| Change | Description |
|--------|-------------|
| Always include drawdown | Pension Pot - Drawdown card now added unconditionally after PCLS |
| `annual_amount: 0` | When not being drawn from, drawdown shows £0 withdrawal |
| `max_amount` field | Added to drawdown allocation for projected balance display |

### Frontend Changes

**IncomeSourceSlider.vue:**

| Change | Description |
|--------|-------------|
| Template | Simplified to: badge → name → "Projected fund value" label → value |
| Removed | Slider input, tax badge container, annual withdrawal display |
| Removed | All slider-related computed properties (`sliderStep`, `sliderFillWidth`) |
| Removed | `emits`, `data`, `watch`, `methods` - component is now purely presentational |
| CSS | Simplified styles, removed all slider-related CSS |

**RetirementIncomeTab.vue:**

| Change | Description |
|--------|-------------|
| Removed | "Adjust sliders" subtitle under Income Sources heading |
| Removed | `@update` event handler on IncomeSourceSlider |
| Removed | `handleAllocationUpdate` method |
| Removed | `updateIncomeAllocation` Vuex action import |
| Removed | `incomeAllocations` watcher |

### Files Changed

**Backend:**
```text
app/Services/Retirement/RetirementIncomeService.php
```

**Frontend (Included in Build):**
```text
resources/js/components/Retirement/IncomeSourceSlider.vue
resources/js/components/Retirement/RetirementIncomeTab.vue
```

---

## Pension Dashboard - Retirement Income Planner Card

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Enhanced the Target Annual Income card on the Pension dashboard to show more information from the Retirement Income Planner, including a heading and the projected net income after tax.

### Card Layout (Before)

```text
┌─────────────────────────────┐
│ Target Annual Income        │
│ £75,000                     │
│ ─────────────────────────── │
│ Required Capital            │
│ £1,595,745                  │
│ Based on 4.7% withdrawal    │
└─────────────────────────────┘
```

### Card Layout (After)

```text
┌─────────────────────────────┐
│ Retirement Income Planner   │  ← NEW heading
│ ─────────────────────────── │
│ Target Annual Income        │
│ £75,000                     │
│ ─────────────────────────── │
│ Required Capital            │
│ £1,595,745                  │
│ Based on 4.7% withdrawal    │
│ ─────────────────────────── │
│ Projected Net Income        │  ← NEW
│ £75,000 (green)             │  ← From retirement income planner
│ After tax from all sources  │
└─────────────────────────────┘
```

### Changes Made

| Change | Description |
|--------|-------------|
| Card heading | Added "Retirement Income Planner" heading at top of card |
| Projected Net Income | Added new field showing net income after tax from retirement income planner |
| Data fetching | Added `fetchRetirementIncome` to load retirement income data on page load |
| Computed property | Added `projectedNetIncome` computed property |
| CSS | Added `.income-card-heading` and `.income-card-value-green` styles |

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/NetWorth/PensionList.vue
```

---

## Strategies Tab - Capital Position Summary

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Enhanced the Strategies tab to include comprehensive capital position data from the Retirement Income Planner. This gives users a complete view of their retirement readiness by showing:
- Projected pension pot at retirement (80% Monte Carlo confidence)
- Other assets included (ISAs, bonds, savings)
- Total projected capital vs required capital
- Gap/surplus analysis with progress bar
- Achievable net income from tax-optimised drawdown

### New Capital Position Section

The Strategies tab now shows a "Capital Position at Retirement" section with:

1. **Progress Bar** - Visual progress towards required capital with percentage
2. **Gap/Surplus Badge** - Shows shortfall or surplus amount
3. **Capital Breakdown Cards**:
   - Projected Pension Pot (80% Monte Carlo confidence)
   - Other Assets (ISAs, bonds, savings included)
   - Achievable Net Income (from Retirement Income Planner)

### Smart "On Track" Detection

The strategy logic now considers ALL assets when determining if the user needs strategies:

**Before:** Only checked if pension pot probability >= 95%
**After:** User is "on track" if:
- Pension pot probability >= 95% (traditional), OR
- Achievable net income from ALL sources meets target income

This means users with significant ISA/bond holdings won't see unnecessary strategy recommendations if their combined assets can already provide their target retirement income.

### Backend Changes

| File | Change |
|------|--------|
| RetirementStrategyService.php | Added RequiredCapitalCalculator and RetirementIncomeService dependencies |
| RetirementStrategyService.php | Added `calculateCapitalPosition()` method |
| RetirementStrategyService.php | Added `getIncludedOtherAssets()` method |
| RetirementStrategyService.php | Added `capital_position` to strategy response |
| RetirementStrategyService.php | Modified "on track" check to consider `income_meets_target` |

### Frontend Changes

| File | Change |
|------|--------|
| StrategiesTab.vue | Added Capital Position section with progress bar and cards |
| StrategiesTab.vue | Added capital position computed properties |
| StrategiesTab.vue | Enhanced On Track banner to show capital summary |
| StrategiesTab.vue | Added comprehensive CSS for new section |

### Files Changed

**Backend:**
```text
app/Services/Retirement/RetirementStrategyService.php
```

**Frontend (Included in Build):**
```text
resources/js/components/Retirement/StrategiesTab.vue
```

---

## Required Capital Detail - Projected Values for Other Assets

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Updated the Required Capital Detail view to display projected values at retirement for other assets (ISAs, bonds, cash) instead of current values. The projections now use the same Monte Carlo 80% confidence values as the Retirement Income Planner and Investment detail views, ensuring consistency across all views.

### Changes Made

| Change | Description |
|--------|-------------|
| Asset values | Now show Monte Carlo 80% projected value at retirement |
| Asset type label | Shows "(80% confidence)" suffix (e.g., "ISA - Projected (80% confidence)") |
| Section note | Shows "Values projected to age X (80% confidence)" |
| Summary cards | "Other Assets at Retirement" card now shows projected total |
| Gap calculation | Uses Monte Carlo projected values for accurate gap/surplus calculation |
| Data fetching | Now fetches retirement income data to get Monte Carlo projections |

### Projection Consistency

All three views now use the same Monte Carlo 80% confidence projections:

| View | Projection Method |
|------|-------------------|
| Required Capital Detail | Monte Carlo 80% (from Retirement Income API) |
| Retirement Income Planner | Monte Carlo 80% (from `getAccountProjectedValue80`) |
| Investment Account Detail | Monte Carlo 80% (from `MonteCarloSimulator`) |

### Visual Layout

**Before:**
```text
┌─────────────────────────┐
│ ISA                     │
│ Vanguard                │
│ £150,000                │  ← Current value
│ ISA - Investment        │
└─────────────────────────┘
```

**After:**
```text
┌─────────────────────────────────────────┐
│ ISA                                     │
│ Vanguard                                │
│ £590,862                                │  ← Monte Carlo 80% projected
│ ISA - Projected (80% confidence)        │
└─────────────────────────────────────────┘
```

### Data Flow

1. Component calls `fetchRetirementIncome()` on load
2. Retirement Income API returns `available_accounts` with Monte Carlo projections
3. `getProjectedValue()` looks up account by ID in `retirementIncomeAvailableAccounts`
4. Returns the `value` field which is the Monte Carlo 80% projected value

### Methods Updated

| Method | Description |
|--------|-------------|
| `getProjectedValue(account)` | Looks up Monte Carlo 80% value from retirement income data |
| `getProjectedCashValue(account)` | Looks up projected value for cash accounts |
| `totalProjectedInvestments` | Sum of Monte Carlo projected investment values |
| `totalProjectedCash` | Sum of projected cash values |

### Files Changed

**Frontend (Included in Build):**
```text
resources/js/components/Retirement/RequiredCapitalDetail.vue
```

---

## Complete Backend Upload Summary

All PHP files that need to be uploaded manually (not included in frontend build):

### Models (4 files)

```text
app/Models/User.php
app/Models/DCPension.php
app/Models/UserAssumption.php (NEW)
app/Models/Investment/InvestmentAccount.php
```

### Services (5 files)

```text
app/Services/Settings/AssumptionsService.php (NEW)
app/Services/Retirement/RequiredCapitalCalculator.php (NEW)
app/Services/Retirement/RetirementIncomeService.php
app/Services/Retirement/RetirementStrategyService.php
app/Services/Investment/InvestmentProjectionService.php
```

### Controllers (3 files)

```text
app/Http/Controllers/Api/Settings/AssumptionsController.php (NEW)
app/Http/Controllers/Api/RetirementController.php
app/Http/Controllers/Api/InvestmentController.php
```

### Routes (1 file)

```text
routes/api.php
```

### Migrations (4 files)

```text
database/migrations/2026_01_30_160000_add_contribution_fields_to_investment_accounts.php
database/migrations/2026_01_30_100000_add_beneficiary_to_dc_pensions_table.php
database/migrations/2026_01_30_120000_create_user_assumptions_table.php
database/migrations/2026_01_30_150000_add_include_in_retirement_to_investment_accounts.php
```

### New Directories to Create

```text
app/Http/Controllers/Api/Settings/
app/Services/Settings/
```

### Quick Upload Commands (SiteGround File Manager)

**Total: 17 PHP files + 1 routes file**

Upload to `~/www/fynla.org/public_html/` maintaining the same directory structure.

---

## Rollback

If issues occur:

1. Restore previous `public/build/` directory from backup
2. Clear cache:
   ```bash
   php artisan cache:clear && php artisan config:clear && php artisan view:clear
   ```

---
