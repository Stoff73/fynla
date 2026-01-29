# Employee Share Scheme Tracking - Implementation Plan

## Overview

Add comprehensive UK employee share scheme tracking to the FPS Investment module with support for 5 scheme types: SAYE, CSOP, EMI, Unapproved Options, and RSUs.

---

## New Account Types

| Type | Description | Tax Treatment |
|------|-------------|---------------|
| `saye` | SAYE / Sharesave | Tax-advantaged (no IT/NIC on exercise) |
| `csop` | Company Share Option Plan | Tax-advantaged (3-10 year window) |
| `emi` | Enterprise Management Incentives | Tax-advantaged (startup options) |
| `unapproved_options` | Non-tax-advantaged Options | IT/NIC on exercise gain |
| `rsu` | Restricted Stock Units | IT/NIC at vesting |

---

## Phase 1: Database Migration

### New Migration: `add_employee_share_scheme_fields_to_investment_accounts_table.php`

**Group 1: Employer Details (8 columns)**
```
employer_name (string 255, nullable)
employer_registration (string 50, nullable)
employer_ticker (string 20, nullable) - for listed companies
employer_is_listed (boolean, default false)
parent_company_name (string 255, nullable) - for US parent plans
parent_company_country (string 100, nullable)
ers_scheme_reference (string 50, nullable) - HMRC ERS registration
ers_registered (boolean, default false)
```

**Group 2: Grant Details (10 columns)**
```
grant_date (date, nullable)
grant_reference (string 100, nullable)
units_granted (integer, nullable)
exercise_price (decimal 12,4, nullable) - strike price for options
market_value_at_grant (decimal 12,4, nullable)
share_class_scheme (string 100, nullable)
grant_currency (string 3, default 'GBP')
option_price_paid (decimal 12,2, nullable)
scheme_start_date (date, nullable) - SAYE contract start
scheme_duration_months (integer, nullable) - 36 or 60 for SAYE
```

**Group 3: Vesting Schedule (12 columns)**
```
vesting_type (string 30, nullable) - cliff/monthly/quarterly/annual/performance
cliff_date (date, nullable)
cliff_percentage (integer, nullable)
vesting_period_months (integer, nullable)
vesting_frequency_months (integer, nullable)
has_performance_conditions (boolean, default false)
performance_conditions_description (text, nullable)
performance_period_end (date, nullable)
performance_vesting_min_percent (integer, nullable)
performance_vesting_max_percent (integer, nullable)
full_vest_date (date, nullable)
accelerated_vesting_allowed (boolean, default false)
```

**Group 4: Current Status (8 columns)**
```
units_vested (integer, default 0)
units_unvested (integer, default 0)
units_exercised (integer, default 0)
units_forfeited (integer, default 0)
units_expired (integer, default 0)
scheme_status (string 30, default 'active')
current_share_price (decimal 12,4, nullable)
share_price_date (date, nullable)
```

**Group 5: Exercise & Expiry (6 columns)**
```
exercise_window_start (date, nullable)
exercise_window_end (date, nullable)
last_exercise_date (date, nullable)
total_exercise_proceeds (decimal 15,2, nullable)
total_exercise_cost (decimal 15,2, nullable)
exercise_history_json (text, nullable)
```

**Group 6: Tax Treatment (8 columns)**
```
tax_treatment (string 30, nullable) - tax_advantaged/unapproved
is_readily_convertible_asset (boolean, nullable) - RCA flag
paye_via_payroll (boolean, default true)
income_tax_at_vest_exercise (decimal 15,2, nullable)
ni_at_vest_exercise (decimal 15,2, nullable)
csop_disqualifying_event (boolean, default false)
csop_three_year_date (date, nullable)
cost_basis_for_cgt (decimal 15,2, nullable)
```

**Group 7: SAYE-Specific (5 columns)**
```
saye_monthly_savings (decimal 10,2, nullable) - max 500
saye_current_savings_balance (decimal 15,2, nullable)
saye_maturity_date (date, nullable)
saye_option_discount_percent (decimal 5,2, nullable)
saye_bonus_amount (decimal 12,2, nullable)
```

**Group 8: Leaver Terms (4 columns)**
```
leaver_category (string 30, nullable)
post_termination_exercise_days (integer, nullable)
termination_date (date, nullable)
leaver_notes (text, nullable)
```

**Total: 61 new columns**

---

## Phase 2: Backend Updates

### Files to Modify:

**1. `app/Models/Investment/InvestmentAccount.php`**
- Add all 61 new fields to `$fillable` array
- Add date casts for date fields
- Add boolean casts for boolean fields
- Add integer casts for unit counts
- Add helper methods:
  - `isEmployeeShareScheme()` - check if account type is share scheme
  - `isOptionsScheme()` - check if options-based (vs RSUs)
  - `isTaxAdvantagedScheme()` - check if SAYE/CSOP/EMI
  - `getIntrinsicValueAttribute()` - calculate option intrinsic value
  - `getSchemeCurrentValueAttribute()` - calculate total vested value
  - `getUnvestedValueAttribute()` - calculate unvested potential value
  - `isInCsopTaxAdvantageWindow()` - check 3-10 year CSOP window

**2. `app/Http/Controllers/Api/InvestmentController.php`**
- Add `saye`, `csop`, `emi`, `unapproved_options`, `rsu` to account_type validation enum
- Add conditional validation rules for employee share scheme fields
- Auto-calculate `csop_three_year_date` (grant_date + 3 years) for CSOP
- Auto-calculate `saye_maturity_date` (scheme_start_date + scheme_duration_months) for SAYE

### Required Fields (when employee share scheme type):
- `employer_name`
- `grant_date`
- `units_granted`
- `exercise_price` (options only, not RSUs)

---

## Phase 3: Frontend Form Updates

### File: `resources/js/components/Investment/AccountForm.vue`

**1. Add Account Type Options:**
```html
<option value="saye">SAYE / Sharesave</option>
<option value="csop">CSOP (Company Share Option Plan)</option>
<option value="emi">EMI (Enterprise Management Incentives)</option>
<option value="unapproved_options">Unapproved Share Options</option>
<option value="rsu">RSUs (Restricted Stock Units)</option>
```

**2. Add Computed Properties:**
```javascript
isEmployeeShareScheme() {
  return ['saye', 'csop', 'emi', 'unapproved_options', 'rsu'].includes(this.formData.account_type);
}
isOptionsScheme() {
  return ['saye', 'csop', 'emi', 'unapproved_options'].includes(this.formData.account_type);
}
isSAYEScheme() {
  return this.formData.account_type === 'saye';
}
isCSOPScheme() {
  return this.formData.account_type === 'csop';
}
```

**3. Add Form Sections (v-if="isEmployeeShareScheme"):**

| Section | Key Fields |
|---------|------------|
| Employer Details | Name*, registration, ticker, is_listed, parent company |
| Grant Details | Date*, reference, units_granted*, exercise_price*, market value |
| SAYE-Specific (v-if="isSAYEScheme") | Monthly savings (max 500), savings balance, maturity date |
| Vesting Schedule | Type, cliff date/%, period, frequency, performance conditions |
| Current Status | Vested/unvested/exercised counts, current share price, scheme status |
| Exercise & Expiry (options only) | Window start/end, expiry, exercise history |
| Tax Treatment | Tax treatment type, RCA status, PAYE via payroll, CSOP 3-year date |
| Leaver Terms | Category, post-termination exercise period, notes |

*Required fields

**4. Auto-Calculations:**
- Intrinsic Value: `max(0, current_share_price - exercise_price) * units_vested`
- Unvested Value: `max(0, current_share_price - exercise_price) * units_unvested`
- CSOP 3-Year Date: `grant_date + 3 years`
- SAYE Maturity: `scheme_start_date + scheme_duration_months`

---

## Phase 4: Form Dropdown Options

| Field | Options |
|-------|---------|
| vesting_type | Cliff, Monthly, Quarterly, Annual, Performance, Immediate |
| scheme_status | Active, Vesting, Exercisable, Exercised, Expired, Forfeited, Cancelled |
| tax_treatment | Tax Advantaged, Unapproved, Mixed |
| leaver_category | Good Leaver, Bad Leaver, Death, Redundancy, Retirement, Unknown |

---

## Critical Files

| File | Changes |
|------|---------|
| `database/migrations/xxxx_add_employee_share_scheme_fields.php` | New migration (61 columns) |
| `app/Models/Investment/InvestmentAccount.php` | Fillable, casts, 7 helper methods |
| `app/Http/Controllers/Api/InvestmentController.php` | ~50 new validation rules |
| `resources/js/components/Investment/AccountForm.vue` | 8 form sections, computed properties |

---

## Verification

1. **Run migration:** `php artisan migrate`
2. **Test SAYE:** Add new SAYE account with monthly savings and maturity date
3. **Test CSOP:** Add CSOP, verify 3-year date auto-calculates
4. **Test RSUs:** Add RSU, verify exercise_price field is hidden
5. **Test Unapproved Options:** Add with US parent company details
6. **Test intrinsic value:** Enter current share price and verify calculation
7. **Test validation:** Submit with missing required fields
8. **Test existing accounts:** Verify ISA/GIA/Private Company still work

---

## Notes

- All new fields nullable for backward compatibility
- SAYE monthly savings capped at £500 (UK limit)
- CSOP tax advantage requires exercise 3-10 years from grant
- RCA (Readily Convertible Assets) determines PAYE treatment
- Form uses collapsible sections to avoid overwhelming user
- Hide standard fields (Provider, Platform) for employee share schemes
