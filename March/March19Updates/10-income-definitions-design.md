# Income Definitions & Adjusted Allowances — Design Spec

**Date:** 19 March 2026
**Branch:** `logicFix`
**Status:** Approved

## Overview

Add HMRC-aligned income definitions (Total Income, Net Income, Adjusted Net Income, Threshold Income, Adjusted Income) to the income/tax tab, with automatic computation of tapered Personal Allowance and Pension Annual Allowance. Requires new user input fields for charitable donations (with Gift Aid toggle) and registered blind status.

## Scope

1. New database fields: `is_registered_blind` (users table), `charitable_donations` + `is_gift_aid` (expenditure)
2. TaxConfig addition: Blind Person's Allowance (£2,870 for 2025/26)
3. New `IncomeDefinitionsService` computing all 5 HMRC definitions
4. API endpoint returning definitions + adjusted allowances
5. Frontend display: waterfall breakdown + adjusted allowances panel
6. Onboarding: blind status on income step, charitable donations on expenditure step
7. Update `AnnualAllowanceChecker` to use proper definitions

## Data Model

### New Migration

**`users` table:**
```php
$table->boolean('is_registered_blind')->default(false);
```

**`expenditure_items` or user profile (depending on where expenditure is stored):**
- `charitable_donations` (decimal 15,2, nullable) — annual amount
- `is_gift_aid` (boolean, default false) — whether donations are Gift Aided

### TaxConfigurationSeeder Addition

Add to the `income_tax` section for 2025/26:
```php
'blind_persons_allowance' => 2870,
```

### TaxConfigService Addition

```php
public function getBlindPersonsAllowance(): float
{
    return (float) ($this->get('income_tax.blind_persons_allowance') ?? 2870);
}
```

## IncomeDefinitionsService

**Location:** `app/Services/Tax/IncomeDefinitionsService.php`

**Dependencies:** `TaxConfigService`, `User`, `DCPension`

**Method:** `calculate(int $userId): array`

### Calculation Logic

```
1. Total Income
   = employment + self_employment + rental + dividend + interest + other + trust
   (All from User model income fields)

2. Net Income
   = Total Income
   - pension tax relief (sum of employee contribution amounts across all DC pensions)
   - Gift Aid gross-up (charitable_donations × 1.25, if is_gift_aid = true)

3. Adjusted Net Income
   = Net Income
   - Blind Person's Allowance (£2,870 if is_registered_blind = true)

4. Threshold Income
   = Adjusted Net Income
   - employee pension contributions (same as pension relief above)
   Note: In a net pay arrangement, contributions already reduce taxable pay.
   For relief-at-source, they don't reduce threshold income.
   Simplification: treat all as net pay for now.

5. Adjusted Income
   = Threshold Income
   + employer pension contributions (sum of employer contribution amounts across all DC pensions)
```

### Pension Contribution Calculation

From `DCPension` model, for each pension where `user_id = $userId`:
```php
$employeeAmount = ($pension->current_salary ?? $pension->annual_salary ?? 0)
    * ($pension->employee_contribution_percent / 100);
$employerAmount = ($pension->current_salary ?? $pension->annual_salary ?? 0)
    * ($pension->employer_contribution_percent / 100);
```

Sum across all DC pensions for total employee/employer contributions.

### Adjusted Allowances Calculation

**Personal Allowance taper** (Adjusted Net Income > £100,000):
```
reduction = floor((adjusted_net_income - 100000) / 2)
adjusted_pa = max(0, personal_allowance - reduction)
```
PA fully eliminated at £125,140.

**Pension Annual Allowance taper** (Threshold Income > £200,000 AND Adjusted Income > £260,000):
```
Both conditions must be met.
reduction = floor((adjusted_income - 260000) / 2)
adjusted_aa = max(10000, annual_allowance - reduction)
```
AA bottoms out at £10,000.

### Return Structure

```php
[
    'total_income' => float,
    'net_income' => float,
    'adjusted_net_income' => float,
    'threshold_income' => float,
    'adjusted_income' => float,

    'components' => [
        'employment' => float,
        'self_employment' => float,
        'rental' => float,
        'dividend' => float,
        'interest' => float,
        'other' => float,
        'trust' => float,
    ],

    'deductions' => [
        'pension_relief' => float,
        'gift_aid_gross' => float,
        'blind_persons_allowance' => float,
        'employee_pension_contributions' => float,
        'employer_pension_contributions' => float,
    ],

    'adjusted_allowances' => [
        'personal_allowance' => float,
        'personal_allowance_full' => float,
        'personal_allowance_tapered' => bool,
        'pension_annual_allowance' => float,
        'pension_annual_allowance_full' => float,
        'pension_aa_tapered' => bool,
    ],
]
```

## API Endpoint

**Route:** `GET /api/tax/income-definitions`
**Controller:** `TaxController` or new `IncomeDefinitionsController`
**Auth:** `auth:sanctum`
**Response:** The return structure above wrapped in standard `{ success, data }` format.

## Frontend

### New Component: `IncomeDefinitionsPanel.vue`

**Location:** `resources/js/components/UserProfile/IncomeDefinitionsPanel.vue`

Displays the 5 HMRC income definitions in a stepped waterfall layout:

```
Your Income Definitions

Total Income                              £85,000
  Employment £60,000 · Rental £15,000 · Dividends £5,000 · Interest £3,000 · Other £2,000

Less pension relief                       -£4,800
Less Gift Aid (grossed up)                -£1,700
                                          ───────
Net Income                                £78,500

Less Blind Person's Allowance             -£2,870
                                          ───────
Adjusted Net Income                       £75,630

Less employee pension contributions       -£3,500
                                          ───────
Threshold Income                          £72,130
  ✓ Below £200,000 — no pension taper triggered

Plus employer pension contributions       +£4,500
                                          ───────
Adjusted Income                           £76,630
  ✓ Below £260,000 — full Annual Allowance available
```

**Adjusted Allowances section** (always visible below definitions):

```
Your Allowances

Personal Allowance          £12,570    (full)
Pension Annual Allowance    £60,000    (full)
```

When tapered:
```
Personal Allowance          £8,570     (reduced from £12,570 — income over £100,000)
Pension Annual Allowance    £35,000    (reduced from £60,000 — income over £260,000)
```

Each definition has a `(i)` tooltip with the HMRC explanation.

**Styling:**
- Card with `bg-white rounded-lg border border-light-gray shadow-sm`
- Deduction lines in `text-neutral-500`
- Subtotals in `font-bold text-horizon-500`
- Threshold status: `text-spring-500` for under, `text-raspberry-500` for over
- Currency formatting via `currencyMixin`

### Placement

Added to the income/tax tab view, below or alongside the existing `TaxIncomeCard`.

### New Input Fields

**Income step (onboarding + IncomeOccupation.vue):**
```html
<div>
  <label class="label">Visual Impairment</label>
  <div class="flex items-center">
    <input type="checkbox" v-model="formData.is_registered_blind" />
    <span>I am registered blind or severely sight impaired</span>
  </div>
  <p class="mt-1 text-body-sm text-neutral-500">
    This qualifies you for the Blind Person's Allowance (£2,870 for 2025/26)
  </p>
</div>
```

**Expenditure step (onboarding + ExpenditureForm.vue):**
Split the existing "Gifts & Charity" line into two:
- "Gifts" (birthday gifts, etc.) — keeps existing behaviour
- "Charitable donations" — new field with annual amount
- "I Gift Aid my donations" — checkbox toggle

```html
<div>
  <label class="label">Annual Charitable Donations</label>
  <input type="number" v-model="formData.charitable_donations" placeholder="0" />
  <div class="flex items-center mt-2">
    <input type="checkbox" v-model="formData.is_gift_aid" />
    <span>I Gift Aid my donations</span>
  </div>
  <p class="mt-1 text-body-sm text-neutral-500">
    Gift Aid lets charities claim 25p for every £1 you donate and extends your basic rate band
  </p>
</div>
```

## Backend Updates

### AnnualAllowanceChecker

Replace simplified `getUserIncome()` with call to `IncomeDefinitionsService::calculate()`. Use `threshold_income` and `adjusted_income` from the result instead of raw income sum.

### UKTaxCalculator

Update the Personal Allowance taper logic to use Adjusted Net Income from the service (currently uses a simplified total).

## Files Changed

### New Files
- `database/migrations/YYYY_MM_DD_add_income_definition_fields.php`
- `app/Services/Tax/IncomeDefinitionsService.php`
- `resources/js/components/UserProfile/IncomeDefinitionsPanel.vue`

### Modified Files
- `database/seeders/TaxConfigurationSeeder.php` — add BPA
- `app/Services/TaxConfigService.php` — add `getBlindPersonsAllowance()`
- `app/Models/User.php` — add `is_registered_blind` to fillable/casts
- `app/Services/Retirement/AnnualAllowanceChecker.php` — use service
- `app/Services/UKTaxCalculator.php` — use Adjusted Net Income for PA taper
- `app/Http/Controllers/Api/` — new or updated controller + route
- `resources/js/components/UserProfile/IncomeOccupation.vue` — blind checkbox
- `resources/js/components/UserProfile/ExpenditureForm.vue` — charitable donations + Gift Aid
- `resources/js/components/Onboarding/steps/IncomeStep.vue` — blind checkbox
- `resources/js/components/Onboarding/steps/SimpleIncomeStep.vue` — blind checkbox
- `resources/js/components/Onboarding/steps/ExpenditureStep.vue` — charitable fields
- `resources/js/components/Onboarding/steps/SimpleExpenditureStep.vue` — charitable fields
- `resources/js/views/UserProfile.vue` or income tab — mount IncomeDefinitionsPanel
- `routes/api.php` — new route

### Test Files
- `tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php`
- Update `tests/Unit/Services/Retirement/AnnualAllowanceCheckerTest.php`

## Design Constraints

- All tax values from `TaxConfigService` — no hardcoding
- Currency formatting via `currencyMixin`
- British English in user-facing text
- `is_registered_blind` stored on User, not computed
- Charitable donations stored as annual amount
- Gift Aid gross-up is `amount × 1.25` (charity claims basic rate)
- Higher/additional rate taxpayers can claim extra relief but that's handled by the existing tax calculator extending their basic rate band — not part of this income definitions calculation
