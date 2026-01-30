# Deployment Notes - January 30, 2026

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

### Progress Bars

| Bar | Calculation | Purpose |
|-----|-------------|---------|
| Current | Total Included Assets / Required Capital Today | Shows current progress in today's money |
| Forecasted at Retirement | (Projected Pot + Other Assets) / Required Capital at Retirement | Shows projected progress at retirement |

### Asset Toggles

- "Assets included in calculation" section showing DC pensions plus any toggled investments/cash with total value
- "Other assets" section with toggle switches to include investments and cash in retirement capital calculation
- Toggle updates total included assets and recalculates progress bars in real-time
- Toggle label shows "Exclude from retirement capital" when included, "Include in retirement capital" when excluded

### Year-by-Year Table Columns

| Column | Description |
|--------|-------------|
| Year | Calendar year |
| Age | User's age |
| Projected Pot Value | FV of pension pot with contributions |
| Pot in Today's Money | Projected pot discounted by inflation |
| Target in Today's Money | Required capital discounted by inflation |

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
```

**Services:**
```text
app/Services/Settings/AssumptionsService.php → ~/www/fynla.org/public_html/app/Services/Settings/
app/Services/Retirement/RequiredCapitalCalculator.php → ~/www/fynla.org/public_html/app/Services/Retirement/
```
(Create the `Settings` directory if it doesn't exist)

**Controllers:**
```text
app/Http/Controllers/Api/Settings/AssumptionsController.php → ~/www/fynla.org/public_html/app/Http/Controllers/Api/Settings/
app/Http/Controllers/Api/RetirementController.php → ~/www/fynla.org/public_html/app/Http/Controllers/Api/
```
(Create the `Settings` directory if it doesn't exist)

**Routes:**
```text
routes/api.php → ~/www/fynla.org/public_html/routes/
```

**Migrations:**
```text
database/migrations/2026_01_30_100000_add_beneficiary_to_dc_pensions_table.php → ~/www/fynla.org/public_html/database/migrations/
database/migrations/2026_01_30_120000_create_user_assumptions_table.php → ~/www/fynla.org/public_html/database/migrations/
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

## Rollback

If issues occur:

1. Restore previous `public/build/` directory from backup
2. Clear cache:
   ```bash
   php artisan cache:clear && php artisan config:clear && php artisan view:clear
   ```

---
